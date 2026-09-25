<?php

use App\Http\Controllers\PosController;
use Illuminate\Support\Facades\Route;

Route::get('/', [PosController::class, 'dashboard'])->name('pos.index');
Route::get('/dashboard', [PosController::class, 'dashboard'])->name('dashboard');
Route::get('/inventory', [PosController::class, 'inventory'])->name('inventory.index');
Route::get('/sales', [PosController::class, 'sales'])->name('sales.index');
Route::post('/inventory', [PosController::class, 'store'])->name('products.store');
Route::patch('/inventory/{product}', [PosController::class, 'update'])->name('products.update');
Route::delete('/inventory/{product}', [PosController::class, 'destroy'])->name('products.destroy');
Route::post('/sales', [PosController::class, 'sell'])->name('sales.store');
