<?php

use Illuminate\Support\Facades\Route;

Route::get('/', fn () => response()->json(['name' => 'PETTEL', 'status' => 'api']))->name('home');
