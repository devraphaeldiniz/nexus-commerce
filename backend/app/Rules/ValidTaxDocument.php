<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class ValidTaxDocument implements ValidationRule
{
    public function __construct(private ?string $expectedType = null) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $cleaned = preg_replace('/\D/', '', (string) $value);

        if ($this->expectedType === 'CPF' || strlen($cleaned) === 11) {
            if (! $this->isValidCpf($cleaned)) {
                $fail('O CPF informado é inválido.');
            }
            return;
        }

        if ($this->expectedType === 'CNPJ' || strlen($cleaned) === 14) {
            if (! $this->isValidCnpj($cleaned)) {
                $fail('O CNPJ informado é inválido.');
            }
            return;
        }

        $fail('O documento deve ser um CPF (11 dígitos) ou CNPJ (14 dígitos) válido.');
    }

    private function isValidCpf(string $cpf): bool
    {
        if (strlen($cpf) !== 11 || preg_match('/^(\d)\1{10}$/', $cpf)) {
            return false;
        }

        for ($t = 9; $t < 11; $t++) {
            $d = 0;
            for ($c = 0; $c < $t; $c++) {
                $d += $cpf[$c] * (($t + 1) - $c);
            }
            $d = ((10 * $d) % 11) % 10;
            if ($cpf[$c] != $d) {
                return false;
            }
        }
        return true;
    }

    private function isValidCnpj(string $cnpj): bool
    {
        if (strlen($cnpj) !== 14 || preg_match('/^(\d)\1{13}$/', $cnpj)) {
            return false;
        }

        $weights1 = [5, 4, 3, 2, 9, 8, 7, 6, 5, 4, 3, 2];
        $weights2 = [6, 5, 4, 3, 2, 9, 8, 7, 6, 5, 4, 3, 2];

        for ($i = 0, $sum = 0; $i < 12; $i++) {
            $sum += $cnpj[$i] * $weights1[$i];
        }
        $rest = $sum % 11;
        $digit1 = ($rest < 2) ? 0 : 11 - $rest;
        if ($cnpj[12] != $digit1) {
            return false;
        }

        for ($i = 0, $sum = 0; $i < 13; $i++) {
            $sum += $cnpj[$i] * $weights2[$i];
        }
        $rest = $sum % 11;
        $digit2 = ($rest < 2) ? 0 : 11 - $rest;

        return $cnpj[13] == $digit2;
    }
}
