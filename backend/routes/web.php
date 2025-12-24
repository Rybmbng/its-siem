<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/test-ch', function () {
    try {
        $data = DB::connection('clickhouse')->select('SELECT 1');
        return "Koneksi ClickHouse AMAN, Bang!";
    } catch (\Exception $e) {
        return "Gagal konek ClickHouse: " . $e->getMessage();
    }
});

Route::get('/dashboard', function () {
    return view('dashboard');
});