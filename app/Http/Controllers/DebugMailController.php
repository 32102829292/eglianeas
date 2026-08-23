<?php

namespace App\Http\Controllers;

use App\Mail\VerificationCodeMail;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Mail;

class DebugMailController extends Controller
{
    public function __invoke(): JsonResponse
    {
        $smtp = config('mail.mailers.smtp');
        $code = (string) random_int(100000, 999999);

        $summary = [
            'mailer' => config('mail.default'),
            'default_transport' => config('mail.mailers.'.config('mail.default').'.transport'),
            'brevo_key_set' => ! empty(config('services.brevo.key')),
            'smtp' => [
                'host' => $smtp['host'] ?? null,
                'port' => $smtp['port'] ?? null,
                'username' => $smtp['username'] ?? null,
                'password_set' => ! empty($smtp['password']),
                'from_address' => config('mail.from.address'),
                'from_name' => config('mail.from.name'),
            ],
        ];

        try {
            Mail::to('prttypriyaa@gmail.com')->send(new VerificationCodeMail($code, 'Debug Test'));

            return response()->json([
                'ok' => true,
                'sent_to' => 'prttypriyaa@gmail.com',
                'code' => $code,
            ] + $summary);
        } catch (\Throwable $e) {
            $chain = [];
            $current = $e;

            while ($current !== null) {
                $chain[] = [
                    'class' => get_class($current),
                    'message' => $current->getMessage(),
                    'at' => $current->getFile().':'.$current->getLine(),
                    'trace' => array_slice(explode("\n", $current->getTraceAsString()), 0, 25),
                ];
                $current = $current->getPrevious();
            }

            return response()->json(['ok' => false] + $summary + ['exceptions' => $chain], 500);
        }
    }
}
