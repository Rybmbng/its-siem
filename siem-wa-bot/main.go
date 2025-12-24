package main

import (
	"context"
	"database/sql"
	"fmt"
	"os"
	"os/signal"
	"syscall"
	"time"

	_ "github.com/go-sql-driver/mysql"
	_ "modernc.org/sqlite" // <--- DRIVER SQLITE MURNI GO (ANTI ERROR CGO)
	"github.com/skip2/go-qrcode"
	"go.mau.fi/whatsmeow"
	waProto "go.mau.fi/whatsmeow/binary/proto"
	"go.mau.fi/whatsmeow/store/sqlstore"
	"go.mau.fi/whatsmeow/types"
	waLog "go.mau.fi/whatsmeow/util/log"
	"google.golang.org/protobuf/proto"
)

func main() {
	// 1. Tetap konek ke MariaDB untuk ambil data SIEM
	dbSIEM, err := sql.Open("mysql", "rey:rey@tcp(127.0.0.1:3306)/siem_management?parseTime=true")
	if err != nil {
		panic(err)
	}

	// 2. Simpan Session WA di file lokal saja pakai driver sqlite murni
	dbLog := waLog.Stdout("Database", "ERROR", true)
	// Kita pakai "sqlite" (bukan sqlite3) karena driver modernc pakai nama itu
	container, err := sqlstore.New(context.Background(), "sqlite", "file:wa_session.db?_pragma=foreign_keys(1)", dbLog)
	if err != nil {
		panic(fmt.Sprintf("Gagal inisialisasi store: %v", err))
	}

	deviceStore, err := container.GetFirstDevice(context.Background())
	if err != nil {
		panic(err)
	}

	client := whatsmeow.NewClient(deviceStore, waLog.Stdout("Client", "ERROR", true))

	// 3. Login QR
	if client.Store.ID == nil {
		qrChan, _ := client.GetQRChannel(context.Background())
		err = client.Connect()
		if err != nil {
			panic(err)
		}
		for evt := range qrChan {
			if evt.Event == "code" {
				fmt.Println("\nSCAN QR INI DI WHATSAPP:")
				q, _ := qrcode.New(evt.Code, qrcode.Medium)
				fmt.Println(q.ToSmallString(false))
			}
		}
	} else {
		err = client.Connect()
		if err != nil {
			panic(err)
		}
	}

	fmt.Println("✅ Bot WA Aktif!")

	// 4. Monitoring Loop
	lastSentID := 0
	_ = dbSIEM.QueryRow("SELECT id FROM ip_policies ORDER BY id DESC LIMIT 1").Scan(&lastSentID)

	go func() {
		for {
			var id int
			var ip, reason string
			err := dbSIEM.QueryRow("SELECT id, ip_address, reason FROM ip_policies WHERE id > ? ORDER BY id ASC LIMIT 1", lastSentID).Scan(&id, &ip, &reason)
			
			if err == nil {
				msg := fmt.Sprintf("⚠️ *SIEM ALERT* ⚠️\n\n🛡️ *IP BANNED*\n📍 IP: %s\n📝 Reason: %s\n⏰ Time: %s", ip, reason, time.Now().Format("15:04:05"))
				targetJID := types.NewJID("6281510019837", types.DefaultUserServer) 
				
				_, err := client.SendMessage(context.Background(), targetJID, &waProto.Message{
					Conversation: proto.String(msg),
				})

				if err == nil {
					fmt.Println("🚀 Alert terkirim ke WA untuk IP:", ip)
					lastSentID = id
				}
			}
			time.Sleep(5 * time.Second)
		}
	}()

	c := make(chan os.Signal, 1)
	signal.Notify(c, os.Interrupt, syscall.SIGTERM)
	<-c
	client.Disconnect()
}