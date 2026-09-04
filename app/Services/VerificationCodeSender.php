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

        try {
            Mail::to($user->email)->send(new VerificationCodeMail($code, $user->name, $ttlMinutes));
        } catch (\Throwable $e) {
            // The code is still stored on the record, so the reset/verify flow
            // remains usable. Report so delivery failures aren't silent, but
            // never let an SMTP outage surface as a raw 500 to the user.
            report($e);
        }

        return $record;
    }
}