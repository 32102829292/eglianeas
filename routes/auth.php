<?php

use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\ConfirmablePasswordController;
use App\Http\Controllers\Auth\ForgotPinController;
use App\Http\Controllers\Auth\PinLoginController;
use App\Http\Controllers\Auth\RegisteredUserController;
use App\Http\Controllers\Auth\WebauthnLoginController;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function () {
    Route::get('register', [RegisteredUserController::class, 'create'])
        ->name('register');

    Route::post('register', [RegisteredUserController::class, 'store']);

    Route::get('register/check-email', [RegisteredUserController::class, 'checkEmail'])
        ->name('register.checkEmail')
        ->middleware('throttle:30,1');

    Route::post('register/resume-verify', [RegisteredUserController::class, 'resumeVerify'])
        ->name('register.resumeVerify')
        ->middleware('throttle:10,1');

    Route::get('verify-account', [RegisteredUserController::class, 'verifyForm'])
        ->name('verify.account');

    Route::post('verify-account', [RegisteredUserController::class, 'verifyStore']);

    Route::post('verify-account/resend', [RegisteredUserController::class, 'resendCode'])
        ->name('verify.resend');

    Route::get('login', [AuthenticatedSessionController::class, 'create'])
        ->name('login');

    Route::post('login/pin', [PinLoginController::class, 'store'])
        ->name('login.pin');

    Route::post('login/webauthn/options', [WebauthnLoginController::class, 'options'])
        ->name('login.webauthn.options');

    Route::post('login/webauthn/verify', [WebauthnLoginController::class, 'verify'])
        ->name('login.webauthn.verify');

    Route::get('forgot-pin', [ForgotPinController::class, 'showEmailForm'])
        ->name('forgot-pin');

    Route::post('forgot-pin', [ForgotPinController::class, 'sendCode'])
        ->name('forgot-pin.send')
        ->middleware('throttle:10,1');

    Route::post('forgot-pin/resend', [ForgotPinController::class, 'resendCode'])
        ->name('forgot-pin.resend')
        ->middleware('throttle:10,1');

    Route::get('forgot-pin/verify', [ForgotPinController::class, 'showVerifyForm'])
        ->name('forgot-pin.verify');

    Route::post('forgot-pin/verify', [ForgotPinController::class, 'verifyCode'])
        ->name('forgot-pin.verify.post')
        ->middleware('throttle:20,1');

    Route::get('forgot-pin/reset', [ForgotPinController::class, 'showResetForm'])
        ->name('forgot-pin.reset');

    Route::post('forgot-pin/reset', [ForgotPinController::class, 'resetPin'])
        ->name('forgot-pin.reset.post');
});

Route::middleware('auth')->group(function () {
    Route::post('logout', [AuthenticatedSessionController::class, 'destroy'])
        ->name('logout');

    Route::get('confirm-password', [ConfirmablePasswordController::class, 'show'])
        ->name('password.confirm');

    Route::post('confirm-password', [ConfirmablePasswordController::class, 'store']);
});
