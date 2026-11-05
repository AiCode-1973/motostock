<?php
declare(strict_types=1);

require dirname(__DIR__) . '/app/bootstrap.php';

use App\Core\App;
use App\Core\Request;
use App\Core\Session;

$request = Request::fromGlobals();
// Webhooks não usam sessão.
if (!str_starts_with($request->path, '/webhooks/')) {
    Session::startNative($request->secure);
}
App::build()->handle($request)->send();
