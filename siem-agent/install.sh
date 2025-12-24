#!/bin/bash
GREEN='\033[0;32m'
BLUE='\033[0;34m'
RED='\033[0;31m'
NC='\033[0m'

echo -e "${BLUE}🛡️  SIEM AGENT PRO - Installer${NC}"

if [[ $EUID -ne 0 ]]; then
   echo -e "${RED}❌ Harap jalankan script ini dengan sudo!${NC}"
   exit 1
fi

if [ -f "main.go" ]; then
    echo -e "${BLUE}📦 Compiling Agent...${NC}"
    go build -o bin/siem-agent main.go
else
    echo -e "${BLUE}⏩ Skipping compilation, using existing binary in bin/...${NC}"
fi

echo -e "${BLUE}📁 Setting up directories...${NC}"
mkdir -p /etc/siem-agent/config
mkdir -p /var/log/siem-agent

cp bin/siem-agent /usr/local/bin/siem-agent
chmod +x /usr/local/bin/siem-agent

if [ ! -f "/etc/siem-agent/config/config.yaml" ]; then
    cp config/config.yaml /etc/siem-agent/config/config.yaml
    echo -e "${GREEN}✅ Config file created.${NC}"
else
    echo -e "${BLUE}ℹ️  Config file already exists, skipping...${NC}"
fi

echo -e "${BLUE}⚙️  Creating systemd service...${NC}"
cat <<EOF > /etc/systemd/system/siem-agent.service
[Unit]
Description=SIEM Agent Pro Service
After=network.target

[Service]
Type=simple
User=root
WorkingDirectory=/etc/siem-agent
ExecStart=/usr/local/bin/siem-agent
Restart=always
RestartSec=5
StandardOutput=append:/var/log/siem-agent/output.log
StandardError=append:/var/log/siem-agent/error.log

[Install]
WantedBy=multi-user.target
EOF

# 6. Reload & Start
echo -e "${BLUE}🔄 Restarting systemd services...${NC}"
systemctl daemon-reload
systemctl enable siem-agent
systemctl restart siem-agent

echo -e "------------------------------------------------"
echo -e "${GREEN}✅ SIEM AGENT INSTALLED SUCCESSFULLY!${NC}"
echo -e "${BLUE}📊 Status: ${NC}"
systemctl status siem-agent --no-pager
echo -e "------------------------------------------------"
echo -e "Cek log pendaftaran di: ${BLUE}tail -f /var/log/siem-agent/output.log${NC}"