<?php

namespace App\Support;

use Closure;

class PasswordPolicy
{
    public static function rules(): array
    {
        return [
            'string',
            'min:8',
            function (string $attribute, mixed $value, Closure $fail): void {
                if (is_string($value) && (strlen($value) > 72 || str_contains($value, "\0"))) {
                    $fail('The password must not contain a null character or exceed 72 bytes.');
                }
            },
        ];
    }
}
