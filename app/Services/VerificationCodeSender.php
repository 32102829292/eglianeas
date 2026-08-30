<?php

namespace App\Services;

use App\Mail\VerificationCodeMail;
use App\Models\User;
use App\Models\VerificationCode;
use Illuminate\Support\Facades\Mail;

class VerificationCodeSender
{
    public function send(User $user, int $ttlMinutes = 15): VerificationCode
    {
        $code = (string) random_int(100000, 999999);

        $record = VerificationCode::issue($user, $code, $ttlMinutes);

        Mail::to($user->email)->send(new VerificationCodeMail($code, $user->name, $ttlMinutes));

        return $record;
    }
}