<?php
declare(strict_types=1);

namespace App\Core;

/** Erros de formulário: o App volta à tela anterior com mensagens por campo. */
final class ValidationException extends \RuntimeException
{
    public function __construct(public readonly array $errors, string $message = 'Confira os campos destacados.')
    {
        parent::__construct($message);
    }

    public static function field(string $field, string $message): self
    {
        return new self([$field => $message]);
    }
}
