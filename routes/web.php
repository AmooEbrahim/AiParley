<?php

use App\Http\Controllers\AgentController;
use App\Http\Controllers\ChatAgentController;
use App\Http\Controllers\ChatController;
use App\Http\Controllers\ChatMessageController;
use App\Http\Controllers\SettingController;
use Illuminate\Support\Facades\Route;

// Dashboard
Route::get('/', [ChatController::class, 'index'])->name('chats.index');

// Agents
Route::get('/agents', [AgentController::class, 'index'])->name('agents.index');
Route::post('/agents', [AgentController::class, 'store'])->name('agents.store');
Route::get('/agents/{agent}/edit', [AgentController::class, 'edit'])->name('agents.edit');
Route::put('/agents/{agent}', [AgentController::class, 'update'])->name('agents.update');
Route::delete('/agents/{agent}', [AgentController::class, 'destroy'])->name('agents.destroy');
Route::post('/agents/{agent}/test', [AgentController::class, 'test'])->name('agents.test');

// Chats
Route::get('/chats/create', [ChatController::class, 'create'])->name('chats.create');
Route::post('/chats', [ChatController::class, 'store'])->name('chats.store');
Route::get('/chats/{chat}', [ChatController::class, 'show'])->name('chats.show');
Route::put('/chats/{chat}', [ChatController::class, 'update'])->name('chats.update');
Route::delete('/chats/{chat}', [ChatController::class, 'destroy'])->name('chats.destroy');
Route::post('/chats/{chat}/start', [ChatController::class, 'start'])->name('chats.start');
Route::post('/chats/{chat}/stop', [ChatController::class, 'stop'])->name('chats.stop');
Route::post('/chats/{chat}/next', [ChatController::class, 'next'])->name('chats.next');
Route::post('/chats/{chat}/reset', [ChatController::class, 'reset'])->name('chats.reset');

// Chat agents
Route::post('/chats/{chat}/agents', [ChatAgentController::class, 'store'])->name('chat-agents.store');
Route::put('/chats/{chat}/agents/{chatAgent}', [ChatAgentController::class, 'update'])->name('chat-agents.update');
Route::delete('/chats/{chat}/agents/{chatAgent}', [ChatAgentController::class, 'destroy'])->name('chat-agents.destroy');

// Settings
Route::get('/settings', [SettingController::class, 'edit'])->name('settings.edit');
Route::put('/settings', [SettingController::class, 'update'])->name('settings.update');

// Chat messages (user-composed) + edit/delete
Route::post('/chats/{chat}/messages', [ChatMessageController::class, 'store'])->name('chat-messages.store');
Route::put('/messages/{message}', [ChatMessageController::class, 'update'])->name('messages.update');
Route::delete('/messages/{message}', [ChatMessageController::class, 'destroy'])->name('messages.destroy');

// JSON polling
Route::get('/api/chats/{chat}/messages', [ChatMessageController::class, 'index'])->name('api.chats.messages');
