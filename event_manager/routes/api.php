<?php


use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\EventController;

Route::middleware('validate.jwt')->group(function () {
    Route::post('/events', [EventController::class, 'store']);
    Route::get('/events', [EventController::class, 'index']);
    Route::delete('/events/{id}', [EventController::class, 'destroy']);
    Route::put('/events/{id}', [EventController::class, 'update']);
    Route::post('/events/{id}/join', [EventController::class, 'join']);
    Route::get('/events/{id}/participants', [EventController::class, 'participants']);
    Route::get('/events/search', [EventController::class, 'search']);
    Route::get('/events/mine', [EventController::class, 'userEvents']);
    Route::get('/admin/events', [EventController::class, 'listAllEvents']);
    Route::delete('/admin/events/{id}', [EventController::class, 'deleteEventAsAdmin']);
    Route::delete('/events/{id}/leave', [EventController::class, 'leave']);
    Route::put('/admin/events/{id}/conclude', [EventController::class, 'concludeEvent']);
    Route::delete('/events/{event_id}/participants/{user_id}', [EventController::class, 'kickParticipant']);
    Route::post('/events/{event_id}/rate', [EventController::class, 'rateUser']);
});