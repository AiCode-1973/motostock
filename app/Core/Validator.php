<?php
declare(strict_types=1);

namespace App\Core;

/**
 * Validação no servidor com normalização. Exemplo:
 *   Validator::check($input, ['price' => 'required|money', 'qty' => 'required|int|min:1'], ['price' => 'Preço']);
 * Devolve só os campos declarados, já convertidos (money → centavos, int → int, bool → 0/1, documentos → dígitos).
 */
final class Validator
{
    public static function check(array $input, array $rules, array $labels = []): array
    {
        $out = [];
        $errors = [];
        foreach ($rules as $field => $ruleString) {
            $label = $labels[$field] ?? ucfirst(str_replace('_', ' ', $field));
            $raw = $input[$field] ?? null;
            $value = is_string($raw) ? trim($raw) : $raw;
            $list = explode('|', $ruleString);
            $isBool = in_array('bool', $list, true);
            if ($isBool) {
                $out[$field] = in_array($value, ['1', 1, true, 'on', 'true', 'sim'], true) ? 1 : 0;
                continue;
            }
            if ($value === null || $value === '' || $value === []) {
                if (in_array('required', $list, true)) {
                    $errors[$field] = "Preencha o campo {$label}.";
                } else {
                    $out[$field] = null;
                }
                continue;
            }
            if (is_array($value) && !in_array('array', $list, true)) {
                $errors[$field] = "Valor inválido em {$label}.";
                continue;
            }
            foreach ($list as $rule) {
                [$name, $arg] = array_pad(explode(':', $rule, 2), 2, null);
                $error = null;
                switch ($name) {
                    case 'required':
                    case 'nullable':
                    case 'array':
                        break;
                    case 'max':
                        if (is_string($value) && mb_strlen($value) > (int) $arg) {
                            $error = "{$label} pode ter no máximo {$arg} caracteres.";
                        }
                        break;
                    case 'minlen':
                        if (mb_strlen((string) $value) < (int) $arg) {
                            $error = "{$label} precisa de pelo menos {$arg} caracteres.";
                        }
                        break;
                    case 'int':
                        if (!preg_match('/^-?\d{1,9}$/', (string) $value)) {
                            $error = "{$label} deve ser um número inteiro.";
                        } else {
                            $value = (int) $value;
                        }
                        break;
                    case 'money':
                        $cents = Money::parse((string) $value);
                        if ($cents === null) {
                            $error = "{$label} deve ser um valor em reais, por exemplo 129,90.";
                        } else {
                            $value = $cents;
                        }
                        break;
                    case 'min':
                        if (is_int($value) && $value < (int) $arg) {
                            $error = $name === 'min' && str_contains($ruleString, 'money')
                                ? "{$label} deve ser no mínimo " . Money::format((int) $arg) . '.'
                                : "{$label} deve ser no mínimo {$arg}.";
                        }
                        break;
                    case 'maxval':
                        if (is_int($value) && $value > (int) $arg) {
                            $error = "{$label} deve ser no máximo {$arg}.";
                        }
                        break;
                    case 'email':
                        $value = mb_strtolower((string) $value);
                        if (!filter_var($value, FILTER_VALIDATE_EMAIL)) {
                            $error = "Informe um e-mail válido em {$label}.";
                        }
                        break;
                    case 'in':
                        if (!in_array((string) $value, explode(',', (string) $arg), true)) {
                            $error = "Escolha uma opção válida em {$label}.";
                        }
                        break;
                    case 'cnpj':
                        $value = preg_replace('/\D/', '', (string) $value);
                        if (!self::validCnpj($value)) {
                            $error = "{$label}: CNPJ inválido.";
                        }
                        break;
                    case 'cpf_cnpj':
                        $value = preg_replace('/\D/', '', (string) $value);
                        if (!(strlen($value) === 11 ? self::validCpf($value) : self::validCnpj($value))) {
                            $error = "{$label}: CPF ou CNPJ inválido.";
                        }
                        break;
                    case 'slug':
                        $value = strtolower((string) $value);
                        if (!preg_match('/^[a-z0-9](?:[a-z0-9-]{1,38}[a-z0-9])$/', $value)) {
                            $error = "{$label}: use de 3 a 40 letras minúsculas, números ou hífen.";
                        }
                        break;
                    case 'date':
                        $d = \DateTime::createFromFormat('Y-m-d', (string) $value);
                        if (!$d || $d->format('Y-m-d') !== $value) {
                            $error = "{$label}: data inválida.";
                        }
                        break;
                    case 'uf':
                        $value = strtoupper((string) $value);
                        if (!in_array($value, self::UFS, true)) {
                            $error = "{$label}: UF inválida.";
                        }
                        break;
                    case 'cep':
                        $value = preg_replace('/\D/', '', (string) $value);
                        if (strlen($value) !== 8) {
                            $error = "{$label}: CEP deve ter 8 dígitos.";
                        }
                        break;
                    case 'phone':
                        $value = preg_replace('/\D/', '', (string) $value);
                        if (strlen($value) < 10 || strlen($value) > 13) {
                            $error = "{$label}: telefone com DDD, por exemplo (11) 98765-4321.";
                        }
                        break;
                    case 'password':
                        if (mb_strlen((string) $value) < 10) {
                            $error = 'A senha precisa ter pelo menos 10 caracteres.';
                        } elseif (mb_strlen((string) $value) > 200) {
                            $error = 'A senha é longa demais.';
                        }
                        break;
                    case 'year':
                        if (!preg_match('/^(19|20)\d{2}$/', (string) $value)) {
                            $error = "{$label}: ano com 4 dígitos.";
                        } else {
                            $value = (int) $value;
                        }
                        break;
                    default:
                        throw new \LogicException('Regra desconhecida: ' . $name);
                }
                if ($error !== null) {
                    $errors[$field] = $error;
                    break;
                }
            }
            if (!isset($errors[$field])) {
                $out[$field] = $value;
            }
        }
        if ($errors) {
            throw new ValidationException($errors);
        }
        return $out;
    }

    public const UFS = ['AC', 'AL', 'AP', 'AM', 'BA', 'CE', 'DF', 'ES', 'GO', 'MA', 'MT', 'MS', 'MG', 'PA', 'PB', 'PR', 'PE', 'PI', 'RJ', 'RN', 'RS', 'RO', 'RR', 'SC', 'SP', 'SE', 'TO'];

    public static function validCnpj(string $c): bool
    {
        if (strlen($c) !== 14 || preg_match('/^(\d)\1{13}$/', $c)) {
            return false;
        }
        foreach ([12, 13] as $len) {
            $sum = 0;
            $weights = $len === 12 ? [5, 4, 3, 2, 9, 8, 7, 6, 5, 4, 3, 2] : [6, 5, 4, 3, 2, 9, 8, 7, 6, 5, 4, 3, 2];
            for ($i = 0; $i < $len; $i++) {
                $sum += (int) $c[$i] * $weights[$i];
            }
            $d = $sum % 11 < 2 ? 0 : 11 - $sum % 11;
            if ((int) $c[$len] !== $d) {
                return false;
            }
        }
        return true;
    }

    public static function validCpf(string $c): bool
    {
        if (strlen($c) !== 11 || preg_match('/^(\d)\1{10}$/', $c)) {
            return false;
        }
        for ($t = 9; $t < 11; $t++) {
            $sum = 0;
            for ($i = 0; $i < $t; $i++) {
                $sum += (int) $c[$i] * (($t + 1) - $i);
            }
            $d = ((10 * $sum) % 11) % 10;
            if ((int) $c[$t] !== $d) {
                return false;
            }
        }
        return true;
    }
}
