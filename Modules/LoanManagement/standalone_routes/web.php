<?php

use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\SellController;
use Illuminate\Support\Facades\Route;

Route::get('/', fn () => redirect('/loan-management/dashboard'));
Route::get('/login', [LoginController::class, 'showLoginForm'])->name('login');
Route::post('/login', [LoginController::class, 'login']);
Route::post('/logout', [LoginController::class, 'logout'])->name('logout');
Route::middleware('auth')->group(function () {
    Route::get('/users/export', fn () => redirect()->route('loan-management.users.export'))->name('users.export');
    Route::get('/users/import-template', fn () => redirect()->route('loan-management.users.import-template'))->name('users.import-template');
    Route::post('/users/import', fn () => redirect()->route('loan-management.users.index'))->name('users.import');
    Route::get('/users', fn () => redirect()->route('loan-management.users.index'))->name('users.index');
    Route::get('/users/create', fn () => redirect()->route('loan-management.users.create'))->name('users.create');
    Route::post('/users', fn () => redirect()->route('loan-management.users.index'))->name('users.store');
    Route::get('/users/{user}/edit', fn ($user) => redirect()->route('loan-management.users.edit', $user))->name('users.edit');
    Route::put('/users/{user}', fn ($user) => redirect()->route('loan-management.users.edit', $user))->name('users.update');
    Route::delete('/users/{user}', fn () => redirect()->route('loan-management.users.index'))->name('users.destroy');
    Route::post('/users/{user}/toggle-status', fn ($user) => redirect()->route('loan-management.users.show', $user))->name('users.toggle-status');
    Route::post('/users/{user}/reset-password', fn ($user) => redirect()->route('loan-management.users.show', $user))->name('users.reset-password');
    Route::get('/roles/export', fn () => redirect()->route('loan-management.roles.export'))->name('roles.export');
    Route::get('/roles/import-template', fn () => redirect()->route('loan-management.roles.import-template'))->name('roles.import-template');
    Route::post('/roles/import', fn () => redirect()->route('loan-management.roles.index'))->name('roles.import');
    Route::get('/roles', fn () => redirect()->route('loan-management.roles.index'))->name('roles.index');
    Route::get('/roles/create', fn () => redirect()->route('loan-management.roles.create'))->name('roles.create');
    Route::post('/roles', fn () => redirect()->route('loan-management.roles.index'))->name('roles.store');
    Route::get('/roles/{role}/edit', fn ($role) => redirect()->route('loan-management.roles.edit', $role))->name('roles.edit');
    Route::put('/roles/{role}', fn ($role) => redirect()->route('loan-management.roles.edit', $role))->name('roles.update');
    Route::delete('/roles/{role}', fn () => redirect()->route('loan-management.roles.index'))->name('roles.destroy');
});
Route::redirect('/products', '/loan-management/products')->name('products.index');
Route::get('/sells/{sell}', [SellController::class, 'show'])->name('sells.show');
