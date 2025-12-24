<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Agent;
use App\Models\IpPolicy;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class AgentController extends Controller
{
    // Properti untuk mempermudah manajemen koneksi
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
                ]
            );

            return response()->json(['status' => 'success', 'api_key' => $apiKey]);
        } catch (\Exception $e) {
            Log::error("Agent Registration Failed: " . $e->getMessage());
            return response()->json(['status' => 'error', 'message' => 'Database error'], 500);
        }
    }

    /**
     * Ambil Blacklist IP untuk Agent
     */
    public function blacklist(Request $request)
    {
        $agent = Agent::where('api_key', $request->header('X-API-KEY'))->first();
        
        if (!$agent) {
            return response()->json(['message' => 'Invalid API Key'], 403);
        }

        $agent->update(['last_seen_at' => now()]);
        $ips = IpPolicy::pluck('ip_address'); 

        return response()->json($ips);
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

        // Sanitasi pesan agar tidak merusak query SQL ClickHouse
        $message = str_replace("'", "''", $request->message);
        $severity = $request->severity ?? 'info';

        $query = "INSERT INTO siem_logs.security_logs 
                  (agent_id, hostname, log_type, message, severity, event_time) 
                  VALUES (
                      {$agent->id}, 
                      '{$agent->hostname}', 
                      '{$request->log_type}', 
                      '{$message}', 
                      '{$severity}', 
                      now()
                  )";

        try {
            $this->executeClickHouse($query);
            return response()->json(['status' => 'success']);
        } catch (\Exception $e) {
            Log::error("ClickHouse Store Error: " . $e->getMessage());
            return response()->json(['status' => 'error', 'message' => 'Failed to store log'], 500);
        }
    }

    /**
     * Data untuk Dashboard Stats
     */
    public function getDashboardStats()
    {
        // Tambahkan FORMAT JSON agar ClickHouse mengerti
        $query = "SELECT 
                    count() as total_logs,
                    countIf(message LIKE '%Failed%' OR message LIKE '%error%') as attacks,
                    uniq(hostname) as active_agents
                  FROM siem_logs.security_logs 
                  FORMAT JSON"; 
        
        return $this->queryClickHouse($query);
    }

    /**
     * Data untuk Tabel Dashboard
     */
    public function getLatestLogs()
    {
        // Ganti toFormat dengan format standar ClickHouse
        $query = "SELECT 
                    formatDateTime(event_time, '%Y-%m-%d %H:%i:%s') as event_time, 
                    hostname, 
                    log_type, 
                    message 
                  FROM siem_logs.security_logs 
                  ORDER BY event_time DESC 
                  LIMIT 50 
                  FORMAT JSON";
        
        return $this->queryClickHouse($query);
    }

    /**
     * Private Helper: Jalankan Query SELECT yang Aman
     */
    private function queryClickHouse($query)
    {
        try {
            // Berikan timeout agar tidak membuat browser "hang" menunggu
            $response = Http::withHeaders($this->chAuth)
                ->timeout(5) 
                ->withBody($query, 'text/plain')
                ->post($this->chUrl);

            if ($response->successful()) {
                return response()->json($response->json());
            }

            // Jika ClickHouse error (tabel belum ada dsb)
            Log::error("ClickHouse Error Response: " . $response->body());
            return response()->json(['data' => [], 'error' => 'Database error'], 500);

        } catch (\Exception $e) {
            // Jika koneksi ke ClickHouse mati total
            Log::error("ClickHouse Connection Failed: " . $e->getMessage());
            return response()->json(['data' => [], 'error' => 'Connection failed'], 500);
        }
    }

    /**
     * Private Helper: Jalankan Query INSERT
     */
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