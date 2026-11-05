<?php
declare(strict_types=1);

namespace App\Controllers\Panel;

use App\Controllers\Controller;
use App\Core\HttpException;
use App\Core\Tenant;

abstract class PanelController extends Controller
{
    protected string $layout = 'layouts/panel';

    protected function requirePermission(string $permission): void
    {
        if (!Tenant::can($permission)) {
            throw HttpException::forbidden();
        }
    }
}
