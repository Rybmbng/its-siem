package tailer

import (
    "bytes"
    "encoding/json"
    "fmt"
    "net/http"
    "time"
    "github.com/hpcloud/tail"
)

func Watch(path string, logType string, apiURL string, apiKey string, hostname string) {
    fmt.Printf("🔍 [DEBUG] Mencoba baca file: %s\n", path)

    // Kita set Location ke nil dulu biar dia baca dari awal (buat ngetes aja)
    t, err := tail.TailFile(path, tail.Config{
        Follow:    true,
        ReOpen:    true,
        MustExist: true, // Kasih error kalau file gak ketemu
    })

    if err != nil {
        fmt.Printf("❌ [DEBUG] Gagal akses file: %v. Coba jalankan pakai sudo!\n", err)
        return
    }

    fmt.Printf("🚀 [DEBUG] Berhasil 'nempel' di file %s. Menunggu baris baru...\n", path)

    for line := range t.Lines {
        if line.Text == "" {
            continue
        }
        fmt.Printf("📩 [DEBUG] Ada log baru: %.50s...\n", line.Text)
        sendToLaravel(apiURL, apiKey, logType, line.Text)
    }
}

func sendToLaravel(url string, key string, logType string, message string) {
    payload, _ := json.Marshal(map[string]string{
        "log_type": logType,
        "message":  message,
        "severity": "info",
    })

    req, _ := http.NewRequest("POST", url, bytes.NewBuffer(payload))
    req.Header.Set("Content-Type", "application/json")
    req.Header.Set("X-API-KEY", key)

    client := &http.Client{Timeout: 5 * time.Second}
    resp, err := client.Do(req)
    
    if err != nil {
        fmt.Printf("📡 [DEBUG] Network Error: %v\n", err)
        return
    }
    defer resp.Body.Close()
    fmt.Printf("📤 [DEBUG] Laravel Response: %d\n", resp.StatusCode)
}