<?php

use App\Http\Controllers\Api\V1\Order\CancelOrderController;
use App\Http\Controllers\Api\V1\Order\OpenOrdersController;
use App\Http\Controllers\Api\V1\Order\PlaceOrderController;
use App\Http\Controllers\Api\V1\Profile\ProfileController;
use App\Http\Controllers\Api\V1\User\MeController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

Route::group(['prefix' => 'v1', 'as' => 'api.v1.', 'middleware' => ['auth:sanctum', 'verified']], function () {
    Route::get('/me', MeController::class);

    Route::group(['prefix' => 'profile', 'as' => 'profile.'], function () {
        Route::get('/', ProfileController::class)->name('index');
    });

    Route::group(['prefix' => 'orders', 'as' => 'orders.'], function () {
        Route::get('/', OpenOrdersController::class)->name('index');

        Route::post('/', PlaceOrderController::class)->name('place');

        Route::post('/{order}/cancel', CancelOrderController::class)->name('cancel');
    });
});
