<?php

use Illuminate\Support\Facades\Route;
use Neocom\JWK\Http\Controllers\V1\Keys\EncryptionKeyController;
use Neocom\JWK\Http\Controllers\V1\Keys\KeyController;

/*
|--------------------------------------------------------------------------
| API V1 Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/

// Key endpoints
Route::group(['prefix' => 'keys', 'as' => 'keys.'], function() {

    // Get key
    Route::get('/{key_type}', [KeyController::class, 'listKeys'])->where('key_type', 'all|private|public')->name('listKeys');

    // Generate key
    Route::post('/generate', [KeyController::class, 'generateKey'])->name('generateKey');

    // Cleanup keys
    Route::post('/cleanup', [KeyController::class, 'cleanupKeys'])->name('cleanupKeys');

    // Commands for an individual key
    Route::group(['prefix' => '/{key_id}', 'where' => ['[A-Za-z0-9\-_]{32,}']], function() {
        Route::get('/', [KeyController::class, 'getSingleKey'])->name('getKey');
        Route::post('/rotate', [KeyController::class, 'rotateKey'])->name('rotateKey'); // Revoke's and create's a new key with the same type and length
        Route::post('/revoke', [KeyController::class, 'revokeKey'])->name('revokeKey');
        Route::post('/delete', [KeyController::class, 'deleteKey'])->name('deleteKey');
        Route::delete('/', [KeyController::class, 'deleteKey'])->name('deleteKeyMethod');
    });
});

// Encryption Key endpoints
Route::group(['prefix' => 'encryption_keys', 'as' => 'encryptionKeys.'], function () {

    // Register
    Route::post('/register', [EncryptionKeyController::class, 'register'])->name('register');
});
