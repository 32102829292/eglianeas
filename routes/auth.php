<?php

use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\NewPasswordController;
use App\Http\Controllers\Auth\PasswordController;
use App\Http\Controllers\Auth\PasswordResetLinkController;
use App\Http\Controllers\Auth\PinLoginController;
use App\Http\Controllers\Auth\RegisteredUserController;
use App\Http\Controllers\Auth\WebauthnLoginController;
use App\Http\Controllers\Client\ForgotPasswordController;
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

    // Code-based password reset (clients)
    Route::get('forgot-password/code', [ForgotPasswordController::class, 'showEmailForm'])
        ->name('client.password.forgot');

    Route::post('forgot-password/code', [ForgotPasswordController::class, 'sendCode'])
        ->name('client.password.send');

    Route::get('forgot-password/code/verify', [ForgotPasswordController::class, 'showVerifyForm'])
        ->name('client.password.verify');

    Route::post('forgot-password/code/verify', [ForgotPasswordController::class, 'verifyCode'])
        ->name('client.password.verify.post');

    Route::get('forgot-password/code/reset', [ForgotPasswordController::class, 'showResetForm'])
        ->name('client.password.reset');

    Route::post('forgot-password/code/reset', [ForgotPasswordController::class, 'resetPassword'])
        ->name('client.password.reset.post');
});

Route::middleware('auth')->group(function () {
    Route::post('logout', [AuthenticatedSessionController::class, 'destroy'])
        ->name('logout');

    Route::get('confirm-password', [PasswordController::class, 'show'])
        ->name('password.confirm');

    Route::post('confirm-password', [PasswordController::class, 'store']);
});
