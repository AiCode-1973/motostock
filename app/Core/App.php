<?php
declare(strict_types=1);

namespace App\Core;

/** Kernel HTTP: resolve tenant, rota, CSRF, guardas de área e permissão, erros e cabeçalhos de segurança. */
final class App
{
    public function __construct(private Router $router)
    {
    }

    public static function build(): self
    {
        $router = new Router();
        (require BASE_PATH . '/app/routes.php')($router);
        return new self($router);
    }

    public function handle(Request $req): Response
    {
        Tenant::clear();
        Auth::reset();
        Url::setStorePrefix('');
        View::$shared = [];
        Audit::$ip = $req->ip;
        $area = 'main';
        try {
            [$response, $area] = $this->dispatch($req);
        } catch (ValidationException $e) {
            $response = $this->failBack($req, $e->getMessage(), $e->errors);
        } catch (BusinessException $e) {
            $response = $this->failBack($req, $e->getMessage(), []);
        } catch (HttpException $e) {
            $response = $this->errorPage($req, $e->status, $e->getMessage());
        } catch (\Throwable $e) {
            error_log('[MotoStock] ' . get_class($e) . ': ' . $e->getMessage() . ' em ' . $e->getFile() . ':' . $e->getLine());
            if (getenv('MOTOSTOCK_TESTING')) {
                throw $e;
            }
            $msg = Env::bool('APP_DEBUG') ? $e->getMessage() . ' (' . basename($e->getFile()) . ':' . $e->getLine() . ')' : '';
            $response = $this->errorPage($req, 500, $msg);
        }
        return $this->secureHeaders($response, $req, $area);
    }

    /** @return array{0: Response, 1: string} */
    private function dispatch(Request $req): array
    {
        $db = Database::connect();
        $store = str_starts_with($req->path, '/webhooks/') ? null : TenantResolver::resolve($db, $req);
        $path = $req->path;
        if ($store) {
            $path = '@store' . ($store['path'] === '' ? '/' : $store['path']);
            Url::setStorePrefix($store['prefix']);
        }
        $match = $this->router->match($req->method, $path);
        if ($match === null) {
            throw HttpException::notFound();
        }
        if ($match === false) {
            throw new HttpException(405, 'Método não permitido.');
        }
        [$route, $params] = $match;
        $o = $route['opts'];

        if ($req->isPost() && ($o['csrf'] ?? true) && !Csrf::verify($req)) {
            throw new HttpException(419);
        }

        $state = Session::takeFormState();
        View::$shared = [
            'old' => $state['old'],
            'errors' => $state['errors'],
            'flash' => Session::takeFlash(),
            'query' => $req->query,
            'request' => $req,
        ];

        $guard = match ($o['area']) {
            'store' => $this->guardStore($store),
            'panel' => $this->guardPanel($db, $req, $o),
            'platform' => $this->guardPlatform($req, $o),
            default => $this->guardMain($req, $o),
        };
        if ($guard instanceof Response) {
            return [$guard, $o['area']];
        }

        foreach ($params as $k => $v) {
            if ($k === 'id' || str_ends_with($k, 'Id')) {
                $params[$k] = (int) $v;
            }
        }
        [$class, $method] = $route['handler'];
        $controller = new $class();
        return [$controller->$method($req, ...$params), $o['area']];
    }

    private function guardMain(Request $req, array $o): ?Response
    {
        if (!empty($o['auth']) && !Auth::user()) {
            return Response::redirect(Url::to('/entrar'));
        }
        if (!empty($o['guest']) && Auth::user()) {
            return Response::redirect(Url::to(Auth::user()['platform_role'] ? '/plataforma' : '/painel'));
        }
        View::$shared['user'] = Auth::user();
        return null;
    }

    private function guardStore(?array $store): ?Response
    {
        if (!$store) {
            throw HttpException::notFound();
        }
        $tenant = $store['tenant'];
        if (!Tenant::isOperational($tenant)) {
            throw new HttpException(503, 'Esta loja está temporariamente indisponível.');
        }
        Tenant::set($tenant, null, 'store');
        return null;
    }

    private function guardPanel(Database $db, Request $req, array $o): ?Response
    {
        $user = Auth::user();
        if (!$user) {
            if ($req->method === 'GET') {
                Session::set('intended', $req->path);
            }
            return Response::redirect(Url::to('/entrar'));
        }
        $sql = 'SELECT tu.role AS member_role, t.* FROM tenant_users tu JOIN tenants t ON t.id = tu.tenant_id
                WHERE tu.user_id = ? AND tu.status = \'active\'';
        $wanted = Session::get('tenant_id');
        $row = is_int($wanted) ? $db->fetch($sql . ' AND tu.tenant_id = ?', [$user['id'], $wanted]) : null;
        if (!$row) {
            $row = $db->fetch($sql . ' AND t.status <> \'cancelled\' ORDER BY tu.id LIMIT 1', [$user['id']])
                ?? $db->fetch($sql . ' ORDER BY tu.id LIMIT 1', [$user['id']]);
            if (!$row) {
                if ($user['platform_role']) {
                    return Response::redirect(Url::to('/plataforma'));
                }
                throw HttpException::forbidden('Sua conta não está vinculada a nenhuma loja ativa.');
            }
            Session::set('tenant_id', (int) $row['id']);
        }
        $role = $row['member_role'];
        unset($row['member_role']);
        Tenant::set($row, $role, 'panel');

        if (!Tenant::isOperational($row) && empty($o['inactive'])) {
            return Response::redirect(Url::to('/painel/assinatura'));
        }
        if (isset($o['perm']) && !Tenant::can($o['perm'])) {
            throw HttpException::forbidden();
        }
        View::$shared['user'] = $user;
        View::$shared['tenant'] = $row;
        View::$shared['role'] = $role;
        View::$shared['memberships'] = $db->fetchAll(
            'SELECT t.id, t.trade_name FROM tenant_users tu JOIN tenants t ON t.id = tu.tenant_id
             WHERE tu.user_id = ? AND tu.status = \'active\' ORDER BY t.trade_name',
            [$user['id']]
        );
        return null;
    }

    private function guardPlatform(Request $req, array $o): ?Response
    {
        $user = Auth::user();
        if (!$user) {
            return Response::redirect(Url::to('/entrar'));
        }
        if (!$user['platform_role']) {
            throw HttpException::forbidden();
        }
        if (Env::bool('PLATFORM_REQUIRE_MFA', true) && !$user['mfa_enabled']) {
            Session::flash('info', 'Ative a verificação em duas etapas para acessar o painel da plataforma.');
            return Response::redirect(Url::to('/conta/seguranca'));
        }
        if (($o['perm'] ?? '') === 'platform.write' && $user['platform_role'] !== 'admin') {
            throw HttpException::forbidden('Seu perfil de suporte é somente leitura.');
        }
        View::$shared['user'] = $user;
        return null;
    }

    private function failBack(Request $req, string $message, array $errors): Response
    {
        if ($req->method !== 'POST') {
            return $this->errorPage($req, 422, $message);
        }
        Session::flashInput($req->post, $errors);
        Session::flash('error', $message);
        return Response::redirect($this->backUrl($req));
    }

    /** Volta para a página anterior somente se for do mesmo host (evita redirecionamento aberto). */
    private function backUrl(Request $req): string
    {
        $ref = (string) $req->header('referer');
        $host = strtolower((string) parse_url($ref, PHP_URL_HOST));
        if ($ref !== '' && $host === $req->host) {
            $p = (string) parse_url($ref, PHP_URL_PATH);
            $q = parse_url($ref, PHP_URL_QUERY);
            if (str_starts_with($p, '/')) {
                return $p . ($q ? '?' . $q : '');
            }
        }
        return Url::to('/');
    }

    private function errorPage(Request $req, int $status, string $message): Response
    {
        if (str_starts_with($req->path, '/webhooks/')) {
            return Response::json(['error' => $message ?: 'erro'], $status);
        }
        $ex = new HttpException($status, $message);
        $html = View::render('errors/error', ['status' => $status, 'message' => $ex->getMessage()], 'layouts/minimal');
        return Response::html($html, $status);
    }

    private function secureHeaders(Response $r, Request $req, string $area): Response
    {
        $mp = 'https://*.mercadopago.com.br https://*.mercadopago.com https://www.mercadopago.com.br';
        $r->headers += [
            'X-Content-Type-Options' => 'nosniff',
            'X-Frame-Options' => 'DENY',
            'Referrer-Policy' => 'strict-origin-when-cross-origin',
            'Permissions-Policy' => 'camera=(), microphone=(), geolocation=(), payment=()',
            'Content-Security-Policy' => "default-src 'self'; img-src 'self' data:; style-src 'self'; script-src 'self'; "
                . "font-src 'self'; connect-src 'self'; object-src 'none'; base-uri 'self'; frame-ancestors 'none'; "
                . "form-action 'self' {$mp}",
        ];
        if ($req->secure) {
            $r->headers['Strict-Transport-Security'] = 'max-age=31536000; includeSubDomains';
        }
        if (in_array($area, ['panel', 'platform'], true) || Auth::id() !== null) {
            $r->headers['Cache-Control'] ??= 'no-store';
        }
        return $r;
    }
}
