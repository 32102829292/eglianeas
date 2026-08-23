<?php

use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\NewPasswordController;
use App\Http\Controllers\Auth\PasswordController;
use App\Http\Controllers\Auth\PasswordResetLinkController;
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

    Route::get('forgot-password', [PasswordResetLinkController::class, 'create'])
        ->name('password.request');

    Route::post('forgot-password', [PasswordResetLinkController::class, 'store'])
        ->name('password.email');

    Route::get('reset-password/{token}', [NewPasswordController::class, 'create'])
        ->name('password.reset');

    Route::post('reset-password', [NewPasswordController::class, 'store'])
        ->name('password.store');
});

Route::middleware('auth')->group(function () {
    Route::post('logout', [AuthenticatedSessionController::class, 'destroy'])
        ->name('logout');

    Route::get('confirm-password', [PasswordController::class, 'show'])
        ->name('password.confirm');

    Route::post('confirm-password', [PasswordController::class, 'store']);
});
