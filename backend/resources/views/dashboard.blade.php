<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>TOR MONITOR KETUA</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script defer src="https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <link href="https://fonts.googleapis.com/css2?family=JetBrains+Mono:wght@400;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'JetBrains Mono', monospace; background-color: #05070a; color: #94a3b8; overflow: hidden; }
        .cyber-card { background: rgba(13, 17, 23, 0.9); border: 1px solid #1f2937; border-left: 3px solid #3b82f6; }
        ::-webkit-scrollbar { width: 5px; }
        ::-webkit-scrollbar-thumb { background: #1f2937; }
        [x-cloak] { display: none !important; }
    </style>
</head>
<body x-data="siemDashboard()">

    <div class="flex h-screen flex-col">
        <header class="h-16 border-b border-gray-800 bg-[#080a0f] flex items-center justify-between px-8">
            <h1 class="text-lg font-bold text-white uppercase tracking-tighter">SIEM_CORE <span class="text-blue-500 text-sm">v0.1_BETA</span></h1>
            <div class="text-[10px] font-mono text-right">
                <div class="text-gray-500">SYSTEM_STATUS</div>
                <div :class="apiStatus === 'ONLINE' ? 'text-green-500' : 'text-red-500'" x-text="apiStatus + ' | ' + currentTime"></div>
            </div>
        </header>

        <main class="flex-1 overflow-y-auto p-6 space-y-6">
            <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                <div class="cyber-card p-5 rounded">
                    <p class="text-[9px] text-gray-500 font-bold mb-1">TOTAL_LOGS</p>
                    <h3 class="text-2xl font-bold text-white" x-text="stats.total_logs || 0"></h3>
                </div>
                <div class="cyber-card p-5 rounded border-l-red-600">
                    <p class="text-[9px] text-red-500 font-bold mb-1">ALERTS</p>
                    <h3 class="text-2xl font-bold text-white" x-text="stats.attacks || 0"></h3>
                </div>
                <div class="cyber-card p-5 rounded border-l-green-600">
                    <p class="text-[9px] text-green-500 font-bold mb-1">AGENTS_UP</p>
                    <h3 class="text-2xl font-bold text-white" x-text="stats.active_agents || 0"></h3>
                </div>
                <div class="cyber-card p-5 rounded border-l-yellow-600">
                    <p class="text-[9px] text-yellow-500 font-bold mb-1">EPS_DELTA</p>
                    <h3 class="text-2xl font-bold text-white" x-text="eps"></h3>
                </div>
            </div>

            <div class="cyber-card p-6 rounded bg-black/50">
                <h4 class="text-[10px] font-bold text-gray-500 mb-4 uppercase">Activity Monitoring</h4>
                <div class="h-48 w-full"><canvas id="siemChart"></canvas></div>
            </div>

            <div class="cyber-card rounded flex flex-col border-l-0 border-t-2 border-t-blue-600">
                <div class="px-6 py-3 border-b border-gray-800 flex justify-between items-center bg-[#0d1117]">
                    <span class="text-xs font-bold text-white">LIVE_INGEST_STREAM</span>
                    <input type="text" x-model="search" placeholder="Search..." class="bg-black border border-gray-700 px-3 py-1 text-xs outline-none text-blue-400 w-64">
                </div>
                <div class="overflow-x-auto max-h-80">
                    <table class="w-full text-left">
                        <thead class="bg-black/80 text-[10px] text-gray-500 uppercase sticky top-0">
                            <tr>
                                <th class="px-6 py-3">Time</th>
                                <th class="px-6 py-3">Host</th>
                                <th class="px-6 py-3">Type</th>
                                <th class="px-6 py-3">Payload</th>
                            </tr>
                        </thead>
                        <tbody class="text-[11px] font-mono divide-y divide-gray-800/30">
                            <template x-for="log in filteredLogs" :key="Math.random()">
                                <tr class="hover:bg-blue-500/5">
                                    <td class="px-6 py-2 text-gray-500" x-text="log.event_time"></td>
                                    <td class="px-6 py-2 text-blue-400 font-bold" x-text="log.hostname"></td>
                                    <td class="px-6 py-2 text-gray-400" x-text="log.log_type"></td>
                                    <td class="px-6 py-2 text-gray-400 italic" x-text="log.message"></td>
                                </tr>
                            </template>
                        </tbody>
                    </table>
                </div>
            </div>
        </main>
    </div>

    <script>
        // TRICK: Simpan chart di luar variabel Alpine (Global Scope)
        // Ini untuk mencegah "Maximum call stack size exceeded"
        let myChartInstance = null;

        function siemDashboard() {
            return {
                stats: { total_logs: 0, attacks: 0, active_agents: 0 },
                logs: [],
                eps: 0,
                lastTotal: 0,
                currentTime: '',
                search: '',
                apiStatus: 'ONLINE',

                init() {
                    this.setupChart();
                    this.updateData();
                    setInterval(() => this.updateData(), 3000);
                },

                setupChart() {
                    const ctx = document.getElementById('siemChart');
                    if (!ctx) return;
                    
                    myChartInstance = new Chart(ctx, {
                        type: 'line',
                        data: {
                            labels: Array(20).fill(''),
                            datasets: [{
                                data: Array(20).fill(0),
                                borderColor: '#3b82f6',
                                backgroundColor: 'rgba(59, 130, 246, 0.1)',
                                fill: true,
                                tension: 0.4,
                                pointRadius: 0,
                                borderWidth: 2
                            }]
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            animation: false, // Penting agar tidak berat
                            plugins: { legend: { display: false } },
                            scales: {
                                y: { beginAtZero: true, grid: { color: '#111827' } },
                                x: { display: false }
                            }
                        }
                    });
                },

                async updateData() {
                    this.currentTime = new Date().toLocaleTimeString();
                    try {
                        const base = window.location.origin;
                        const [sReq, lReq] = await Promise.all([
                            fetch(`${base}/api/dashboard/stats`),
                            fetch(`${base}/api/dashboard/logs`)
                        ]);

                        const sRes = await sReq.json();
                        const lRes = await lReq.json();

                        if (sRes?.data?.[0]) {
                            const d = sRes.data[0];
                            const currentTotal = parseInt(d.total_logs);
                            if (this.lastTotal > 0) {
                                this.eps = currentTotal - this.lastTotal;
                            }
                            this.lastTotal = currentTotal;
                            this.stats = d;

                            // Update chart menggunakan variabel global
                            if (myChartInstance) {
                                myChartInstance.data.datasets[0].data.push(this.eps);
                                myChartInstance.data.datasets[0].data.shift();
                                myChartInstance.update('none');
                            }
                        }

                        if (lRes?.data) {
                            this.logs = lRes.data;
                        }
                        this.apiStatus = 'ONLINE';
                    } catch (e) {
                        this.apiStatus = 'OFFLINE';
                        console.error("Dashboard Fetch Error");
                    }
                },

                get filteredLogs() {
                    if (!this.logs || !Array.isArray(this.logs)) return [];
                    const q = this.search.toLowerCase();
                    return this.logs.filter(l => !q || l.message?.toLowerCase().includes(q) || l.hostname?.toLowerCase().includes(q));
                }
            }
        }
    </script>
</body>
</html>
