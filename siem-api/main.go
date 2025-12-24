package main

import (
	"bytes"
	"encoding/json"
	"fmt"
	"log"
	"net/http"
	"runtime"
	"strings"
	"time"
)

const (
	ListenPort     = ":9001"
	LaravelAPI     = "http://192.168.2.242:8001/api/report-threat" 
	MasterToken    = "MASTER-REG-TOKEN-2025"
	MaxWorker      = 5
	QueueSize      = 1000
)

type LogPayload struct {
	Source   string    `json:"source"`
	ClientIP string    `json:"client_ip"`
	Message  string    `json:"message"`
	Hostname string    `json:"hostname"`
	Time     time.Time `json:"timestamp"`
}

type ThreatReport struct {
	IP       string `json:"ip"`
	Hostname string `json:"hostname"`
	Reason   string `json:"reason"`
	LogData  string `json:"log_data"`
}

var logQueue = make(chan LogPayload, QueueSize)

func main() {
	workers := runtime.NumCPU()
	if workers > MaxWorker { workers = MaxWorker }
	
	for i := 1; i <= workers; i++ {
		go logWorker(i)
	}

	http.HandleFunc("/ingest", authMiddleware(ingestHandler))

	fmt.Printf("🚀 SIEM Collector Pro Aktif di %s dengan %d Worker\n", ListenPort, workers)
	log.Fatal(http.ListenAndServe(ListenPort, nil))
}

func authMiddleware(next http.HandlerFunc) http.HandlerFunc {
	return func(w http.ResponseWriter, r *http.Request) {
		key := r.Header.Get("X-API-KEY")
		if key == "" {
			http.Error(w, "Unauthorized", http.StatusUnauthorized)
			return
		}
		next.ServeHTTP(w, r)
	}
}

func ingestHandler(w http.ResponseWriter, r *http.Request) {
	if r.Method != http.MethodPost {
		http.Error(w, "Method not allowed", http.StatusMethodNotAllowed)
		return
	}

	var logs []LogPayload
	if err := json.NewDecoder(r.Body).Decode(&logs); err != nil {
		http.Error(w, "Invalid JSON", http.StatusBadRequest)
		return
	}

	for _, l := range logs {
		l.Time = time.Now()
		select {
		case logQueue <- l:
		default:
			fmt.Println("⚠️  Antrean penuh, log di-drop!")
		}
	}

	w.WriteHeader(http.StatusAccepted)
	w.Write([]byte(`{"status":"queued"}`))
}

func logWorker(id int) {
	fmt.Printf("👷 Worker-%d siap memantau...\n", id)
	for l := range logQueue {
		fmt.Printf("[%d][%s] %s: %s\n", id, l.Hostname, l.Source, l.ClientIP)

		if detectThreat(l.Message) {
			fmt.Printf("🔥 BAHAYA TERDETEKSI: %s dari IP %s\n", l.Message, l.ClientIP)
			go reportToLaravel(l)
		}
	}
}

func detectThreat(msg string) bool {
	msg = strings.ToUpper(msg)
	threats := []string{
		"SELECT", "UNION", "DROP TABLE", "INSERT INTO",
		"<SCRIPT>", "ALERT(", "ONERROR=", "JAVASCRIPT:",
		"OR 1=1", "' OR '", "--",
	}

	for _, t := range threats {
		if strings.Contains(msg, t) {
			return true
		}
	}
	return false
}

func reportToLaravel(l LogPayload) {
	report := ThreatReport{
		IP:       l.ClientIP,
		Hostname: l.Hostname,
		Reason:   "Pola Serangan Terdeteksi",
		LogData:  l.Message,
	}

	data, _ := json.Marshal(report)
	req, err := http.NewRequest("POST", LaravelAPI, bytes.NewBuffer(data))
	if err != nil {
		return
	}

	req.Header.Set("Content-Type", "application/json")
	req.Header.Set("Authorization", "Bearer "+MasterToken)

	client := &http.Client{Timeout: 5 * time.Second}
	resp, err := client.Do(req)
	if err == nil {
		defer resp.Body.Close()
		fmt.Printf("📢 Laporan serangan dikirim ke Laravel untuk IP: %s\n", l.ClientIP)
	}
}