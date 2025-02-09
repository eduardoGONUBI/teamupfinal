<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\NotificationController;

// Route to fetch all notifications for authenticated user
Route::get('/notifications', [NotificationController::class, 'getNotifications']);

// Route to delete all notifications for authenticated user
Route::delete('/notifications', [NotificationController::class, 'deleteNotifications']);
