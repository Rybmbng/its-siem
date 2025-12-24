package utils

import (
	"bytes"
	"encoding/json"
	"fmt"
	"net/http"
)

type RegResponse struct {
	Status string `json:"status"`
	APIKey string `json:"api_key"`
}

func RegisterAgent(serverURL, regToken, agentName string) (string, error) {
	fmt.Println("🔑 [Auth] Registering agent to backend...")
	
	payload, _ := json.Marshal(map[string]string{
		"agent_name": agentName,
		"reg_token": regToken,
	})

	resp, err := http.Post(serverURL+"/api/agent/register", "application/json", bytes.NewBuffer(payload))
	if err != nil {
		return "", err
	}
	defer resp.Body.Close()

	if resp.StatusCode != http.StatusOK {
		return "", fmt.Errorf("auth failed: %d", resp.StatusCode)
	}

	var res RegResponse
	json.NewDecoder(resp.Body).Decode(&res)
	
	return res.APIKey, nil
}