<?php
declare(strict_types=1);

namespace App\Core;

final class Request
{
    public function __construct(
        public string $method = 'GET',
        public string $path = '/',
        public array $query = [],
        public array $post = [],
        public array $files = [],
        public array $headers = [],
        public string $ip = '127.0.0.1',
        public string $host = 'localhost',
        public string $body = '',
        public bool $secure = false,
        public string $userAgent = '',
    ) {
        $this->method = strtoupper($this->method);
        $this->headers = array_change_key_case($this->headers, CASE_LOWER);
        $this->host = strtolower(preg_replace('/:\d+$/', '', $this->host) ?? $this->host);
    }

    public static function fromGlobals(): self
    {
        $uri = (string) parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
        $base = self::basePath($uri);
        $path = '/' . ltrim(substr($uri, strlen($base)), '/');
        $headers = [];
        foreach ($_SERVER as $k => $v) {
            if (str_starts_with($k, 'HTTP_')) {
                $headers[str_replace('_', '-', strtolower(substr($k, 5)))] = (string) $v;
            }
        }
        if (isset($_SERVER['CONTENT_TYPE'])) {
            $headers['content-type'] = (string) $_SERVER['CONTENT_TYPE'];
        }
        $secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || (Env::bool('TRUST_PROXY', false) && ($headers['x-forwarded-proto'] ?? '') === 'https');
        $ip = (string) ($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0');
        if (Env::bool('TRUST_PROXY', false) && !empty($headers['x-forwarded-for'])) {
            $ip = trim(explode(',', $headers['x-forwarded-for'])[0]);
        }
        $r = new self(
            $_SERVER['REQUEST_METHOD'] ?? 'GET',
            rawurldecode($path),
            $_GET,
            $_POST,
            $_FILES,
            $headers,
            $ip,
            (string) ($_SERVER['HTTP_HOST'] ?? 'localhost'),
            (string) file_get_contents('php://input'),
            $secure,
            substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255),
        );
        Url::setBase($base);
        return $r;
    }

    /** Descobre o prefixo quando o sistema roda numa subpasta (ex.: /motostock). */
    private static function basePath(string $uri): string
    {
        $configured = Env::get('APP_BASE_PATH');
        if ($configured !== null && $configured !== '') {
            return rtrim($configured, '/');
        }
        $dir = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/')), '/');
        if ($dir !== '' && !str_starts_with($uri, $dir . '/') && $uri !== $dir && str_ends_with($dir, '/public')) {
            $dir = substr($dir, 0, -7);
        }
        return str_starts_with($uri, $dir) ? $dir : '';
    }

    public function input(string $key, mixed $default = null): mixed
    {
        return $this->post[$key] ?? $this->query[$key] ?? $default;
    }

    public function str(string $key, string $default = ''): string
    {
        $v = $this->input($key, $default);
        return is_string($v) ? trim($v) : $default;
    }

    public function int(string $key, int $default = 0): int
    {
        $v = $this->input($key);
        return is_numeric($v) ? (int) $v : $default;
    }

    public function header(string $name): ?string
    {
        return $this->headers[strtolower($name)] ?? null;
    }

    public function isPost(): bool
    {
        return $this->method === 'POST';
    }
}
