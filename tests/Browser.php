<?php
declare(strict_types=1);

namespace Tests;

use App\Core\App;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Core\Url;

/** "Navegador" de teste: passa pelo kernel real (rotas, CSRF, guardas, controllers, views) com sessão própria. */
final class Browser
{
    public array $session = [];
    private static ?App $app = null;

    public function __construct(public string $host = 'localhost')
    {
        self::$app ??= App::build();
    }

    public function get(string $path, array $query = []): Response
    {
        return $this->send(new Request('GET', $path, $query, [], [], [], '10.0.0.1', $this->host));
    }

    public function post(string $path, array $data = [], bool $withCsrf = true, array $headers = [], string $body = '', array $files = []): Response
    {
        Session::useArray($this->session);
        if ($withCsrf) {
            $data['_csrf'] = \App\Core\Csrf::token();
        }
        return $this->send(new Request('POST', $path, [], $data, $files, $headers, '10.0.0.1', $this->host, $body));
    }

    public function send(Request $r): Response
    {
        Url::setBase('');
        Session::useArray($this->session);
        return self::$app->handle($r);
    }

    /** Login real pela tela, com e-mail e senha. */
    public function login(string $email, string $password = 'senha-forte-123'): Response
    {
        return $this->post('/entrar', ['email' => $email, 'password' => $password]);
    }

    public function html(Response $r): string
    {
        return $r->body;
    }
}
