<?php
declare(strict_types=1);

namespace App\Core;

/**
 * Envio de e-mail.
 *  - MAIL_DRIVER=log  (padrão): grava em storage/logs/mail.log — desenvolvimento e homologação.
 *  - MAIL_DRIVER=mail: função mail() do PHP (depende do servidor da hospedagem).
 * SMTP autenticado com provedor transacional está listado em docs/06 como dependência externa.
 */
final class Mailer
{
    public static array $sent = []; // usado pelos testes

    public static function send(string $to, string $subject, string $text): bool
    {
        $driver = Env::get('MAIL_DRIVER', 'log');
        self::$sent[] = ['to' => $to, 'subject' => $subject, 'text' => $text];
        if ($driver === 'mail') {
            $from = (string) Env::get('MAIL_FROM', 'nao-responda@localhost');
            $headers = 'From: MotoStock <' . $from . ">\r\nContent-Type: text/plain; charset=UTF-8\r\n";
            $to = str_replace(["\r", "\n"], '', $to);
            return mail($to, '=?UTF-8?B?' . base64_encode($subject) . '?=', $text, $headers);
        }
        if ($driver === 'null') {
            return true;
        }
        $line = sprintf("[%s] Para: %s | Assunto: %s\n%s\n%s\n", Clock::now(), $to, $subject, $text, str_repeat('-', 60));
        return file_put_contents(BASE_PATH . '/storage/logs/mail.log', $line, FILE_APPEND | LOCK_EX) !== false;
    }
}
