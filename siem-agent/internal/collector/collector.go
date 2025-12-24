package collector

import (
	"bytes"
	"encoding/json"
	"net/http"
	"siem-agent/internal/models" // Import yang baru
)

func Send(url string, apiKey string, logs []models.LogPayload) {
	data, _ := json.Marshal(logs)
	req, _ := http.NewRequest("POST", url, bytes.NewBuffer(data))
	req.Header.Set("X-API-KEY", apiKey)
	req.Header.Set("Content-Type", "application/json")

	client := &http.Client{}
	resp, err := client.Do(req)
	if err == nil {
		defer resp.Body.Close()
	}
}