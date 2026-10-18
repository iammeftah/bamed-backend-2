<?php

use Illuminate\Support\Facades\Route;

// API only: no HTML page, no framework version.
Route::get('/', fn () => response()->json(['name' => config('app.name')]));