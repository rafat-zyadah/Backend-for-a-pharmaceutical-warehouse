<?php

namespace App\Support\Users;

use App\Mail\SupervisorPasswordRecoveryMail;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;

class SupervisorPasswordRecoveryService
{
    /** @return list<string> */
    public function send(User $supervisor): array
    {
        $channels = [];
        $password = $supervisor->password;

        if ($supervisor->email) {
            Mail::to($supervisor->email)->send(
                new SupervisorPasswordRecoveryMail($supervisor, $password),
            );
            $channels[] = 'email';
        }

        if ($supervisor->phone) {
            Log::info('Supervisor password recovery WhatsApp dispatch.', [
                'phone' => $supervisor->phone,
                'username' => $supervisor->username,
                'message' => sprintf(
                    'Your Pharmacy Warehouse supervisor password is: %s (username: %s)',
                    $password,
                    $supervisor->username,
                ),
            ]);
            $channels[] = 'whatsapp';
        }

        if ($channels === []) {
            throw ValidationException::withMessages([
                'login' => ['Supervisor account has no registered email or phone for recovery.'],
            ]);
        }

        return $channels;
    }
}
