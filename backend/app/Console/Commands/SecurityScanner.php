<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\IpPolicy;
use ClickHouseDB\Client;

class SecurityScanner extends Command
{
    protected $signature = 'siem:scan';
    protected $description = 'Scan ClickHouse logs for malicious activity';

    public function handle()
    {
        $config = config('database.connections.clickhouse');
        $db = new Client($config);

        $sql = "SELECT src_ip, count() as total 
                FROM siem_logs.agent_logs 
                WHERE (message LIKE '%UNION SELECT%' OR message LIKE '%OR 1=1%') 
                AND event_time > now() - INTERVAL 1 MINUTE 
                GROUP BY src_ip HAVING total > 0";

        $rows = $db->select($sql)->rows();

        foreach ($rows as $row) {
            $ip = $row['src_ip'];
            
            // Masukkan ke MySQL agar Agent bisa tarik policy ini
            IpPolicy::updateOrCreate(
                ['ip_address' => $ip],
                [
                    'action' => 'BAN',
                    'reason' => 'Otomatis: Deteksi serangan SQL Injection',
                    'created_at' => now()
                ]
            );

            $this->info("🛡️ IP $ip telah diblokir!");
        }
    }
}