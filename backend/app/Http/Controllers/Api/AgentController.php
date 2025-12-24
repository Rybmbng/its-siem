<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Agent;
use App\Models\IpPolicy;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon; // PENTING: Wajib ada ini supaya tidak Error 500

class AgentController extends Controller
{
    private $chUrl;
    private $chAuth;

    public function __construct()
    {
        $host = env('CLICKHOUSE_HOST', '127.0.0.1');
        $port = env('CLICKHOUSE_PORT', '8123');
        $this->chUrl  = "http://{$host}:{$port}/";
        
        $this->chAuth = [
            'X-ClickHouse-User' => env('CLICKHOUSE_USERNAME', 'default'),
            'X-ClickHouse-Key'  => env('CLICKHOUSE_PASSWORD', ''),
        ];
    }

    /**
     * Registrasi Agent Baru / Update Heartbeat
     */
    public function register(Request $request)
    {
        $agentName = $request->agent_name ?? 'unknown';
        $apiKey    = \Illuminate\Support\Str::random(40);

        try {
            DB::table('agents')->updateOrInsert(
                ['hostname' => $agentName],
                [
                    'api_key'        => $apiKey,
                    'ip_address'     => $request->ip(),
                    'os_type'        => 'linux',
                    'status'         => 'active',
                    'last_heartbeat' => now(),
                    'updated_at'     => now(),
                ]
            );

            return response()->json(['status' => 'success', 'api_key' => $apiKey]);
        } catch (\Exception $e) {
            Log::error("Agent Registration Failed: " . $e->getMessage());
            return response()->json(['status' => 'error', 'message' => 'Database error'], 500);
        }
    }

    /**
     * Data untuk Dashboard Stats & Heartbeat
     */
    public function getDashboardStats()
    {
        try {
            // 1. Ambil Stats dari ClickHouse dengan Timeout Proteksi
            $query = "SELECT count() as total_logs, countIf(message LIKE '%Failed%' OR message LIKE '%error%') as attacks FROM siem_logs.security_logs FORMAT JSON";
            
            $stats = ['total_logs' => 0, 'attacks' => 0];
            
            try {
                $response = Http::withHeaders($this->chAuth)
                    ->timeout(3)
                    ->withBody($query, 'text/plain')
                    ->post($this->chUrl);

                if ($response->successful()) {
                    $chData = $response->json();
                    $stats = $chData['data'][0] ?? $stats;
                }
            } catch (\Exception $e) {
                Log::warning("ClickHouse unreachable in stats: " . $e->getMessage());
            }

            // 2. Ambil Data Agent dari MySQL untuk Heartbeat
            $agents = DB::table('agents')
                ->select('hostname', 'last_heartbeat', 'ip_address')
                ->get()
                ->map(function($agent) {
                    // Cek jika last_heartbeat null
                    if (!$agent->last_heartbeat) {
                        return [
                            'hostname'  => $agent->hostname,
                            'ip'        => $agent->ip_address,
                            'is_online' => false,
                            'last_seen' => 'Never',
                        ];
                    }

                    $lastSeen = Carbon::parse($agent->last_heartbeat);
                    $isOnline = $lastSeen->diffInSeconds(now()) < 40; // Toleransi 40 detik

                    return [
                        'hostname'  => $agent->hostname,
                        'ip'        => $agent->ip_address,
                        'is_online' => $isOnline,
                        'last_seen' => $lastSeen->diffForHumans(),
                    ];
                });

            return response()->json([
                'stats'        => $stats,
                'agents'       => $agents,
                'active_count' => $agents->where('is_online', true)->count()
            ]);

        } catch (\Exception $e) {
            Log::error("Dashboard Stats Error: " . $e->getMessage());
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Simpan Log ke ClickHouse
     */
    public function storeLog(Request $request)
    {
        $apiKey = $request->header('X-API-KEY');
        $agent  = DB::table('agents')->where('api_key', $apiKey)->first();

        if (!$agent) {
            return response()->json(['status' => 'error', 'message' => 'Unauthorized'], 401);
        }

        $message = str_replace("'", "''", $request->message);
        $severity = $request->severity ?? 'info';

        $query = "INSERT INTO siem_logs.security_logs 
                  (agent_id, hostname, log_type, message, severity, event_time) 
                  VALUES ({$agent->id}, '{$agent->hostname}', '{$request->log_type}', '{$message}', '{$severity}', now())";

        try {
            $this->executeClickHouse($query);
            
            // Update Heartbeat saat kirim log
            DB::table('agents')->where('id', $agent->id)->update(['last_heartbeat' => now()]);
            
            return response()->json(['status' => 'success']);
        } catch (\Exception $e) {
            Log::error("ClickHouse Store Error: " . $e->getMessage());
            return response()->json(['status' => 'error', 'message' => 'Failed to store log'], 500);
        }
    }

    /**
     * Data untuk Tabel Dashboard
     */
    public function getLatestLogs(Request $request)
        {
            $hostname = $request->query('hostname');
            $logType = $request->query('log_type');

            $query = "SELECT formatDateTime(event_time, '%Y-%m-%d %H:%i:%s') as event_time, 
                    hostname, log_type, message 
                    FROM siem_logs.security_logs WHERE 1=1";

            if ($hostname) {
                $query .= " AND hostname = '{$hostname}'";
            }

            // PERBAIKAN: Gunakan LIKE agar 'nginx' bisa menangkap 'nginx-access'
            if ($logType) {
                $query .= " AND log_type LIKE '%{$logType}%'";
            }

            $query .= " ORDER BY event_time DESC LIMIT 50 FORMAT JSON";
            
            $response = Http::withHeaders($this->chAuth)->withBody($query, 'text/plain')->post($this->chUrl);
            return response()->json($response->json());
        }

    private function executeClickHouse($query)
    {
        $response = Http::withHeaders($this->chAuth)
            ->timeout(5)
            ->withBody($query, 'text/plain')
            ->post($this->chUrl);

        if ($response->failed()) {
            throw new \Exception("ClickHouse Execute Failed: " . $response->body());
        }
    }
}