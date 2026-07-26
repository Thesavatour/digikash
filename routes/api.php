<?php

use App\Http\Controllers\Api\PaymentController;
use App\Http\Controllers\Api\StripeController;
use App\Http\Controllers\Api\V1\BeneficiaryController as ApiBeneficiaryController;
use App\Http\Controllers\Api\V1\RemittanceController as ApiRemittanceController;
use Illuminate\Support\Facades\Route;

Route::middleware('merchant.auth')->group(function () {
    Route::group(['prefix' => 'v1'], function () {

        // Create a payment (stateless; no DB record is stored)
        Route::post('initiate-payment', [PaymentController::class, 'initiatePayment']);

        // Payment Verification
        Route::get('verify-payment/{trxId}', [PaymentController::class, 'verifyPayment']);

        // site info
        Route::get('site-info', [PaymentController::class, 'siteInfo']);
    });
});

Route::middleware('auth:sanctum')
    ->post('/stripe/issuing/ephemeral-key', [StripeController::class, 'createEphemeralKey'])->name('stripe.issuing.ephemeral-key');

/*
|--------------------------------------------------------------------------
| End-user API (Sanctum personal access tokens)
|--------------------------------------------------------------------------
|
| Mobile app + third-party integrations call these endpoints with a
| personal-access-token issued via the user dashboard.
|
*/
Route::middleware(['auth:sanctum'])->prefix('v1')->group(function () {

    Route::prefix('remittance')->controller(ApiRemittanceController::class)->group(function () {
        Route::get('corridors', 'corridors');
        Route::post('quote', 'quote');
        Route::post('send', 'send');
        Route::get('transfers', 'history');
        Route::get('transfers/{reference}', 'track');
    });

    Route::apiResource('beneficiaries', ApiBeneficiaryController::class);
});
