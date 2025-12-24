BINARY_NAME=siem-agent
BIN_DIR=bin
CONFIG_DIR=/etc/siem-agent
.PHONY: all build clean run install
all: build
build:
	@echo "Building SIEM Agent for Linux..."
	@mkdir -p $(BIN_DIR)
	GOOS=linux GOARCH=amd64 go build -o $(BIN_DIR)/$(BINARY_NAME) main.go
	@echo "Build selesai! Binary ada di folder $(BIN_DIR)/"
run:
	go run main.go
clean:
	@echo "Cleaning up..."
	rm -rf $(BIN_DIR)
	rm -rf $(CONFIG_DIR)
	@echo "Cleaned!"
install:
	chmod +x install.sh
	sudo ./install.sh