package config

import (
	"database/sql"
	_ "github.com/go-sql-driver/mysql"
	"fmt"
)

func ConnectMySQL() *sql.DB {
	dsn := "rey:rey@tcp(127.0.0.1:3306)/siem_management"
	db, err := sql.Open("mysql", dsn)
	if err != nil {
		panic(err)
	}
	fmt.Println("MySQL Connected!")
	return db
}