<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AgentController; 

Route::post('/agent/register', [AgentController::class, 'register']);
Route::post('/agent/log', [AgentController::class, 'storeLog']);
Route::get('/dashboard/stats', [AgentController::class, 'getDashboardStats']);
Route::get('/dashboard/logs', [AgentController::class, 'getLatestLogs']);