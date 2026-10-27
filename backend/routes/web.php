<?php

use Illuminate\Support\Facades\Route;

Route::get('/', fn () => response()->json(['name' => 'PETTEL', 'status' => 'api']))->name('home');

require __DIR__.'/auth.php';
