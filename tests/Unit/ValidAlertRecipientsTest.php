<?php

namespace Tests\Unit;

use App\Rules\ValidAlertRecipients;
use Tests\TestCase;

class ValidAlertRecipientsTest extends TestCase
{
    public function test_accepts_comma_separated_valid_emails(): void
    {
        $this->assertNull($this->validate('alpha@example.com, beta@example.com'));
    }

    public function test_rejects_invalid_addresses(): void
    {
        $this->assertSame(
            'L\'indirizzo "not-an-email" non è valido.',
            $this->validate('not-an-email, valid@ok.com'),
        );
    }

    public function test_rejects_only_separators(): void
    {
        $this->assertSame(
            'Inserisci almeno un indirizzo email valido.',
            $this->validate('  ,  '),
        );
    }

    private function validate(string $value): ?string
    {
        $message = null;

        (new ValidAlertRecipients)->validate('alert_recipients', $value, function (string $fail) use (&$message): void {
            $message = $fail;
        });

        return $message;
    }
}
