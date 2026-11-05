<?php
declare(strict_types=1);

namespace App\Core;

final class View
{
    /** Dados compartilhados com todos os templates (usuário, tenant, flash...). */
    public static array $shared = [];

    public static function render(string $template, array $data = [], ?string $layout = null): string
    {
        $data = array_merge(self::$shared, $data);
        $html = self::capture($template, $data);
        if ($layout === null) {
            return $html;
        }
        // O conteúdo renderizado sempre vence uma variável "content" vinda dos dados.
        return self::capture($layout, array_merge($data, ['content' => $html]));
    }

    private static function capture(string $template, array $vars): string
    {
        $file = BASE_PATH . '/app/Views/' . $template . '.php';
        if (!preg_match('#^[a-z0-9_/-]+$#', $template) || !is_file($file)) {
            throw new \RuntimeException('Template inexistente: ' . $template);
        }
        return (static function (string $__file, array $__vars): string {
            extract($__vars, EXTR_SKIP);
            ob_start();
            try {
                include $__file;
            } catch (\Throwable $e) {
                ob_end_clean();
                throw $e;
            }
            return (string) ob_get_clean();
        })($file, $vars);
    }
}
