package main

import (
    "fmt"
    "os"
    "net/http" 
    "time"     
    "siem-agent/internal/firewall"
    "siem-agent/internal/tailer"
    "siem-agent/internal/utils"
    "gopkg.in/yaml.v2"
)

type Config struct {
    AgentName    string `yaml:"agent_name"`
    ServerURL    string `yaml:"server_url"`
    APIURL       string `yaml:"api_url"`
    BlacklistURL string `yaml:"blacklist_url"`
    RegToken     string `yaml:"reg_token"`
    APIKey       string `yaml:"api_key"`
    Inputs []struct {
        Name string `yaml:"name"`
        Path string `yaml:"path"`
    } `yaml:"inputs"`
    Features struct {
        SyncInterval int `yaml:"sync_interval"`
    } `yaml:"features"`
}

func main() {
    conf := loadConfig("config/config.yaml")
    hostname, _ := os.Hostname()

    // 1. Auto-Registration
    if conf.APIKey == "" {
        newKey, err := utils.RegisterAgent(conf.ServerURL, conf.RegToken, conf.AgentName)
        if err != nil {
            fmt.Printf("❌ Registration failed: %v\n", err)
            return
        }
        conf.APIKey = newKey
        saveConfig("config/config.yaml", conf)
    }

    fmt.Printf("🛡️  Agent [%s] Active. Forwarding to: %s\n", conf.AgentName, conf.APIURL)

    // 2. Start Services (Running in Background)
    go heartbeatLoop(conf.ServerURL, conf.APIKey)
    go firewall.Sync(conf.BlacklistURL, conf.APIKey, conf.Features.SyncInterval)

    // 3. Watch logs from config
    for _, input := range conf.Inputs {
        fmt.Printf("📂 Watching: %s [%s]\n", input.Path, input.Name)
        go tailer.Watch(input.Path, input.Name, conf.APIURL, conf.APIKey, hostname)
    }

    // Keep the agent running
    select {}
}

func loadConfig(path string) Config {
    var c Config
    f, err := os.ReadFile(path)
    if err != nil {
        fmt.Println("❌ Error reading config:", err)
    }
    yaml.Unmarshal(f, &c)
    return c
}

func saveConfig(path string, c Config) {
    data, _ := yaml.Marshal(c)
    os.WriteFile(path, data, 0644)
}

func heartbeatLoop(serverURL, apiKey string) {
    for {
        client := &http.Client{Timeout: 5 * time.Second}
        req, _ := http.NewRequest("POST", serverURL+"/api/agent/heartbeat", nil)
        req.Header.Set("X-API-KEY", apiKey)

        resp, err := client.Do(req)
        if err == nil {
            resp.Body.Close()
        }
        time.Sleep(30 * time.Second) 
    }
}