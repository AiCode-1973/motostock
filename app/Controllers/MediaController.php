<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Core\Tenant;
use App\Services\ProductService;

/** Serve imagens de produto apenas do tenant do contexto (loja pelo host, painel pela sessão). */
final class MediaController extends Controller
{
    public function show(Request $r, int $id): Response
    {
        $img = $this->db()->fetch(
            'SELECT i.* FROM product_images i JOIN products p ON p.tenant_id = i.tenant_id AND p.id = i.product_id
             WHERE i.tenant_id = ? AND i.id = ?' . (Tenant::source() === 'store' ? ' AND p.active = 1' : ''),
            [Tenant::id(), $id]
        ) ?? throw HttpException::notFound();
        $path = ProductService::uploadDir(Tenant::current()['uuid']) . '/' . basename($img['file_name']);
        if (!is_file($path)) {
            throw HttpException::notFound();
        }
        return new Response((string) file_get_contents($path), 200, [
            'Content-Type' => $img['mime'],
            'Cache-Control' => 'public, max-age=86400',
            'Content-Disposition' => 'inline',
        ]);
    }
}
