<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>🛡️ SIEM COMMAND CENTER v4.0</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script defer src="https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <link href="https://fonts.googleapis.com/css2?family=JetBrains+Mono:wght@400;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'JetBrains Mono', monospace; background-color: #05070a; color: #94a3b8; overflow: hidden; }
        .cyber-card { background: rgba(13, 17, 23, 0.9); border: 1px solid #1f2937; border-left: 3px solid #3b82f6; }
        .node-active { border-color: #3b82f6; background: rgba(59, 130, 246, 0.1); }
        [x-cloak] { display: none !important; }
    </style>
</head>
<body x-data="siemDashboard()">

    <div class="flex h-screen flex-col">
        <header class="h-14 border-b border-gray-800 bg-[#080a0f] flex items-center justify-between px-6">
            <div class="flex items-center gap-4">
                <span class="text-blue-500 font-bold text-xl tracking-tighter">SIEM_V4</span>
                <div class="h-4 w-px bg-gray-800"></div>
                <div class="flex gap-2">
                    <button @click="selectedType = ''; refresh()" 
                            :class="selectedType === '' ? 'bg-blue-600 text-white' : 'bg-gray-800 text-gray-400'" 
                            class="px-3 py-1 text-[10px] font-bold rounded transition-colors">ALL_LOGS</button>
                            
                    <button @click="selectedType = 'ssh'; refresh()" 
                            :class="selectedType === 'ssh' ? 'bg-orange-600 text-white' : 'bg-gray-800 text-gray-400'" 
                            class="px-3 py-1 text-[10px] font-bold rounded">SSH_AUTH</button>
                            
                    <button @click="selectedType = 'nginx'; refresh()" 
                            :class="selectedType === 'nginx' ? 'bg-green-600 text-white' : 'bg-gray-800 text-gray-400'" 
                            class="px-3 py-1 text-[10px] font-bold rounded">WEB_NGINX</button>
                            
                    <button @click="selectedType = 'apache'; refresh()" 
                            :class="selectedType === 'apache' ? 'bg-red-600 text-white' : 'bg-gray-800 text-gray-400'" 
                            class="px-3 py-1 text-[10px] font-bold rounded">WEB_APACHE</button>
                </div>
            </div>
            <div class="text-[10px] font-mono flex gap-4">
                <span class="text-gray-500">API_STATUS: <span :class="apiStatus === 'ONLINE' ? 'text-green-500' : 'text-red-500'" x-text="apiStatus"></span></span>
                <span class="text-white" x-text="currentTime"></span>
            </div>
        </header>

        <div class="flex flex-1 overflow-hidden">
            <aside class="w-64 border-r border-gray-800 bg-[#06080c] p-4 flex flex-col gap-2 overflow-y-auto">
                <h4 class="text-[9px] font-bold text-gray-600 uppercase mb-2">Live Nodes</h4>
                <template x-for="agent in agents" :key="agent.hostname">
                    <div @click="selectedHost = agent.hostname; refresh()" 
                         :class="selectedHost === agent.hostname ? 'node-active border-blue-500' : 'border-gray-800'"
                         class="p-2 border rounded cursor-pointer transition-all hover:bg-blue-900/10">
                        <div class="flex items-center gap-2">
                            <div class="relative flex h-2 w-2">
                                <span :class="agent.is_online ? 'bg-green-500 animate-ping' : 'bg-red-500'" class="absolute h-full w-full rounded-full opacity-75"></span>
                                <span :class="agent.is_online ? 'bg-green-600' : 'bg-red-600'" class="relative rounded-full h-2 w-2"></span>
                            </div>
                            <span class="text-[11px] font-bold text-white uppercase" x-text="agent.hostname"></span>
                        </div>
                        <div class="flex justify-between mt-1 text-[8px] text-gray-500 italic">
                            <span x-text="agent.ip"></span>
                            <span x-text="agent.last_seen"></span>
                        </div>
                    </div>
                </template>
            </aside>

            <main class="flex-1 overflow-y-auto p-6 space-y-4">
                
                <div x-show="selectedHost" x-cloak class="flex items-center justify-between p-2 bg-blue-500/10 border border-blue-500/30 rounded">
                    <span class="text-[10px] text-blue-400 font-bold">FILTERED BY NODE: <span x-text="selectedHost"></span></span>
                    <button @click="selectedHost = ''; refresh()" class="text-[9px] bg-red-600 text-white px-2 py-1 rounded font-bold">RESET FOCUS</button>
                </div>

                <div class="grid grid-cols-3 gap-4">
                    <div class="cyber-card p-4 rounded">
                        <p class="text-[9px] text-gray-500 font-bold mb-1">INGESTED_LOGS</p>
                        <h3 class="text-2xl font-bold text-white" x-text="stats.total_logs || 0"></h3>
                    </div>
                    <div class="cyber-card p-4 rounded border-l-red-600">
                        <p class="text-[9px] text-red-500 font-bold mb-1">THREATS</p>
                        <h3 class="text-2xl font-bold text-white" x-text="stats.attacks || 0"></h3>
                    </div>
                    <div class="cyber-card p-4 rounded border-l-green-600">
                        <p class="text-[9px] text-green-500 font-bold mb-1">EPS_RATE</p>
                        <h3 class="text-2xl font-bold text-white" x-text="eps"></h3>
                    </div>
                </div>

                <div class="cyber-card p-4 rounded h-48">
                    <canvas id="siemChart"></canvas>
                </div>

                <div class="cyber-card rounded flex flex-col border-l-0 border-t-2 border-t-blue-600">
                    <div class="px-4 py-2 border-b border-gray-800 bg-[#0d1117] flex justify-between">
                        <span class="text-[10px] font-bold text-white tracking-widest">LIVE_STREAM</span>
                        <input type="text" x-model="search" placeholder="Search message..." class="bg-black border border-gray-800 px-2 py-0.5 text-[10px] text-blue-400 outline-none w-48">
                    </div>
                    <div class="overflow-x-auto max-h-64">
                        <table class="w-full text-left text-[10px] font-mono">
                            <thead class="bg-black text-gray-600 uppercase sticky top-0">
                                <tr>
                                    <th class="px-4 py-2">Timestamp</th>
                                    <th class="px-4 py-2">Host</th>
                                    <th class="px-4 py-2">Type</th>
                                    <th class="px-4 py-2">Payload</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-800/30">
                                <template x-for="log in filteredLogs" :key="Math.random()">
                                    <tr class="hover:bg-blue-500/5">
                                        <td class="px-4 py-1.5 text-gray-500" x-text="log.event_time"></td>
                                        <td class="px-4 py-1.5 text-blue-400 font-bold" x-text="log.hostname"></td>
                                        <td class="px-4 py-1.5">
                                            <span :class="{'text-orange-500': log.log_type === 'ssh', 'text-green-500': log.log_type === 'nginx'}" x-text="log.log_type"></span>
                                        </td>
                                        <td class="px-4 py-1.5 text-gray-400 truncate max-w-xs" x-text="log.message"></td>
                                    </tr>
                                </template>
                            </tbody>
                        </table>
                    </div>
                </div>
            </main>
        </div>
    </div>
<script>
    let myChart = null;

    function siemDashboard() {
        return {
            stats: { total_logs: 0, attacks: 0 },
            agents: [],
            logs: [],
            eps: 0,
            lastTotal: 0,
            selectedHost: '',
            selectedType: '',
            currentTime: '',
            apiStatus: 'ONLINE',
            search: '',

            init() {
                this.initChart();
                this.refresh();
                // Interval tetap memanggil refresh yang sudah membawa parameter filter
                setInterval(() => this.refresh(), 3000);
            },

            initChart() {
                const ctx = document.getElementById('siemChart').getContext('2d');
                myChart = new Chart(ctx, {
                    type: 'line',
                    data: {
                        labels: Array(30).fill(''),
                        datasets: [{
                            data: Array(30).fill(0),
                            borderColor: '#3b82f6',
                            backgroundColor: 'rgba(59, 130, 246, 0.05)',
                            fill: true,
                            tension: 0.4,
                            pointRadius: 0
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        animation: false,
                        plugins: { legend: { display: false } },
                        scales: {
                            y: { beginAtZero: true, grid: { color: '#111827' } },
                            x: { display: false }
                        }
                    }
                });
            },

            async refresh() {
                this.currentTime = new Date().toLocaleTimeString('en-GB');
                const base = window.location.origin;

                try {
                    // PENTING: Gunakan URLSearchParams agar parameter filter tidak hilang saat refresh
                    const params = new URLSearchParams();
                    if (this.selectedHost) params.append('hostname', this.selectedHost);
                    if (this.selectedType) params.append('log_type', this.selectedType);

                    // 1. Ambil Stats (Biar grafik juga terfilter sesuai node/host yang dipilih)
                    const sReq = await fetch(`${base}/api/dashboard/stats?${params.toString()}`).then(r => r.json());
                    
                    if (sReq) {
                        this.stats = sReq.stats;
                        this.agents = sReq.agents;

                        let total = parseInt(this.stats.total_logs);
                        // Hitung EPS (Log Per Detik)
                        this.eps = this.lastTotal > 0 ? Math.max(0, total - this.lastTotal) : 0;
                        this.lastTotal = total;

                        if (myChart) {
                            myChart.data.datasets[0].data.push(this.eps);
                            myChart.data.datasets[0].data.shift();
                            myChart.update('none');
                        }
                    }

                    // 2. Ambil Logs (Membawa filter hostname & log_type)
                    const lReq = await fetch(`${base}/api/dashboard/logs?${params.toString()}`).then(r => r.json());
                    if (lReq?.data) { 
                        this.logs = lReq.data; 
                    }
                    
                    this.apiStatus = 'ONLINE';
                } catch (e) {
                    this.apiStatus = 'OFFLINE';
                    console.error("REFRESH_ERROR", e);
                }
            },

            // Fungsi untuk reset filter tanpa reload halaman
            resetFilter() {
                this.selectedHost = '';
                this.selectedType = '';
                this.refresh();
            },

            get filteredLogs() {
                if (!this.logs) return [];
                const q = this.search.toLowerCase();
                // Filter tambahan di client-side untuk pencarian cepat di kolom Payload
                return this.logs.filter(l => !q || l.message.toLowerCase().includes(q));
            }
        }
    }
</script>
</body>
</html>