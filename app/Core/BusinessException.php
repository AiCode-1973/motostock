<?php
declare(strict_types=1);

namespace App\Core;

/** Regra de negócio violada; a mensagem é segura para mostrar ao usuário. */
class BusinessException extends \RuntimeException
{
}
