package firewall

import (
	"encoding/json"
	"fmt"
	"io"
	"net/http"
	"os/exec"
	"time"
)

func Sync(url, apiKey string, interval int) {
	for {
		client := &http.Client{Timeout: 10 * time.Second}
		req, _ := http.NewRequest("GET", url, nil)
		req.Header.Set("X-API-KEY", apiKey) // Kirim API Key unik kita

		resp, err := client.Do(req)
		if err == nil {
			body, _ := io.ReadAll(resp.Body)
			var ips []string
			json.Unmarshal(body, &ips)
			for _, ip := range ips {
				exec.Command("sudo", "ufw", "deny", "from", ip).Run()
			}
			fmt.Printf("🛡️  [Firewall] Blacklist synced: %d IPs\n", len(ips))
			resp.Body.Close()
		}
		time.Sleep(time.Duration(interval) * time.Second)
	}
}