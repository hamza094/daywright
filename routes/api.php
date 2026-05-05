<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/

Route::name('api.v1.')->group(function (): void {
    require __DIR__.'/api/v1.php';
});

Route::name('api.v1.admin.')->group(__DIR__.'/api/admin/v1.php');

Route::fallback(fn () => response()->json(['message' => 'Not Found.'], 404));
