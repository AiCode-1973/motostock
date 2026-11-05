<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Database;
use App\Core\Response;
use App\Core\Session;
use App\Core\Tenant;
use App\Core\Url;
use App\Core\View;

abstract class Controller
{
    protected string $layout = 'layouts/main';

    protected function db(): Database
    {
        return Database::connect();
    }

    protected function view(string $template, array $data = [], ?string $layout = null): Response
    {
        return Response::html(View::render($template, $data, $layout ?? $this->layout));
    }

    protected function redirect(string $path, array $query = []): Response
    {
        return Response::redirect(Url::to($path, $query));
    }

    protected function flash(string $message, string $type = 'success'): void
    {
        Session::flash($type, $message);
    }

    protected function tid(): int
    {
        return Tenant::id();
    }

    protected function uid(): ?int
    {
        return Auth::id();
    }
}
