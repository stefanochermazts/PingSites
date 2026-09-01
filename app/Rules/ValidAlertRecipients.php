<?php

namespace App\Rules;

use App\Models\StatusPage;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class ValidAlertRecipients implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value)) {
            $fail('Inserisci almeno un indirizzo email valido.');

            return;
        }

        $emails = StatusPage::parseRecipients($value);

        if ($emails === []) {
            $fail('Inserisci almeno un indirizzo email valido.');

            return;
        }

        foreach ($emails as $email) {
            if (filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
                $fail("L'indirizzo \"{$email}\" non è valido.");

                return;
            }
        }
    }
}
