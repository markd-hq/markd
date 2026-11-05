<?php

use App\Http\Controllers\AdminController;
use App\Http\Controllers\AskController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\CollectorController;
use App\Http\Controllers\MarkController;
use App\Http\Controllers\PassportController;
use App\Http\Controllers\VerifyController;
use App\Http\Controllers\PayoutController;
use App\Http\Controllers\SaverController;
use App\Http\Controllers\WalletController;
use Illuminate\Support\Facades\Route;

Route::get('health', fn () => ['ok' => true, 'app' => 'markd']);

Route::middleware('throttle:30,1')->group(function () {   // public, no login needed
    Route::get('verify/{ref}', [VerifyController::class, 'show']);
    Route::get('passport/{token}', [PassportController::class, 'show']);
});

Route::prefix('auth')->middleware('throttle:10,1')->group(function () {
    Route::post('register', [AuthController::class, 'register']);
    Route::post('login', [AuthController::class, 'login']);
});

Route::middleware(['auth:sanctum', 'throttle:120,1'])->group(function () {
    Route::get('me', [AuthController::class, 'me']);
    Route::post('logout', [AuthController::class, 'logout']);

    Route::post('wallet/challenge', [WalletController::class, 'challenge']);
    Route::post('wallet/link', [WalletController::class, 'link']);

    Route::post('collector', [CollectorController::class, 'store']);
    Route::get('collector/me', [CollectorController::class, 'me']);
    Route::get('collector/attention', [CollectorController::class, 'attention']);

    Route::get('savers', [SaverController::class, 'index']);
    Route::post('savers', [SaverController::class, 'store']);
    Route::post('savers/claim', [SaverController::class, 'claim']);
    Route::get('savers/{saver}', [SaverController::class, 'show']);
    Route::post('savers/{saver}/nudge', [SaverController::class, 'nudge']);
    Route::get('savers/{saver}/payout-preview', [PayoutController::class, 'preview']);
    Route::get('my/savings', [SaverController::class, 'mine']);
    Route::get('my/passport', [PassportController::class, 'mine']);
    Route::post('my/passport/share', [PassportController::class, 'share']);
    Route::post('my/passport/revoke', [PassportController::class, 'revoke']);

    Route::post('marks', [MarkController::class, 'store']);
    Route::post('payouts', [PayoutController::class, 'store']);

    Route::post('ask', [AskController::class, 'ask'])->middleware('throttle:20,1');

    Route::get('admin/stats', [AdminController::class, 'stats']);
});
