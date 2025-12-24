package models

import "time"

type LogPayload struct {
	Source   string    `json:"source"`
	ClientIP string    `json:"client_ip"`
	Message  string    `json:"message"`
	Hostname string    `json:"hostname"`
	Time     time.Time `json:"timestamp"`
}