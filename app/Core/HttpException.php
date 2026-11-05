<?php
declare(strict_types=1);

namespace App\Core;

final class HttpException extends \RuntimeException
{
    public function __construct(public readonly int $status, string $message = '')
    {
        parent::__construct($message !== '' ? $message : self::defaultMessage($status), $status);
    }

    public static function notFound(string $message = ''): self
    {
        return new self(404, $message);
    }

    public static function forbidden(string $message = ''): self
    {
        return new self(403, $message);
    }

    private static function defaultMessage(int $status): string
    {
        return match ($status) {
            400 => 'Requisição inválida.',
            401 => 'Não autorizado.',
            403 => 'Você não tem permissão para fazer isso.',
            404 => 'Página não encontrada.',
            419 => 'Sua sessão expirou. Recarregue a página e tente de novo.',
            429 => 'Muitas tentativas. Aguarde alguns minutos e tente de novo.',
            503 => 'Serviço indisponível no momento.',
            default => 'Ocorreu um erro inesperado.',
        };
    }
}
