<?php
declare(strict_types=1);

namespace Tests;

/** Abre todas as telas com os dados de demonstração e confere que nenhuma quebra. */
final class SmokeTest extends TestCase
{
    public function testEveryScreenRendersWithDemoData(): void
    {
        $argvBackup = $GLOBALS['argv'] ?? [];
        $GLOBALS['argv'] = ['seed-demo.php', '--force'];
        $argv = $GLOBALS['argv'];
        ob_start();
        require BASE_PATH . '/bin/seed-demo.php';
        ob_end_clean();
        $GLOBALS['argv'] = $argvBackup;

        $tid = (int) $this->db->value("SELECT id FROM tenants WHERE slug = 'ze-motopecas'");
        $owner = $this->browser();
        $this->assertRedirectTo('/painel', $owner->login('dono@ze.demo.test', 'demo-motostock-2026'));
        $ids = fn(string $table) => (int) $this->db->value("SELECT MIN(id) FROM {$table} WHERE tenant_id = ?", [$tid]);
        $pages = ['/painel', '/painel?de=2026-01-01&ate=2026-12-31', '/painel/produtos', '/painel/produtos?baixo=1', '/painel/produtos/novo', '/painel/produtos/' . $ids('products'),
            '/painel/catalogo', '/painel/catalogo/importar', '/painel/estoque', '/painel/estoque/movimentar', '/painel/estoque/historico',
            '/painel/fornecedores', '/painel/fornecedores/novo', '/painel/fornecedores/' . $ids('suppliers'), '/painel/compras', '/painel/compras/nova',
            '/painel/compras/' . $ids('purchase_orders'), '/painel/clientes', '/painel/clientes/novo', '/painel/clientes/' . $ids('customers'),
            '/painel/pedidos', '/painel/pedidos/novo', '/painel/relatorios', '/painel/usuarios', '/painel/auditoria', '/painel/configuracoes',
            '/painel/privacidade', '/painel/assinatura', '/conta/seguranca'];
        foreach ($this->db->fetchAll('SELECT id FROM orders WHERE tenant_id = ?', [$tid]) as $o) {
            $pages[] = '/painel/pedidos/' . $o['id'];
        }
        foreach (array_keys(\App\Services\ReportService::REPORTS) as $k) {
            $pages[] = '/painel/relatorios/' . $k;
            $pages[] = '/painel/relatorios/' . $k . '/csv';
        }
        foreach ($pages as $p) {
            [$path, $qs] = array_pad(explode('?', $p, 2), 2, '');
            parse_str($qs, $q);
            $r = $owner->get($path, $q);
            $this->assertStatus(200, $r, $p);
        }

        $seller = $this->browser();
        $seller->login('vendedor@ze.demo.test', 'demo-motostock-2026');
        foreach (['/painel', '/painel/pedidos', '/painel/produtos/' . $ids('products'), '/painel/estoque', '/painel/clientes', '/painel/assinatura'] as $p) {
            $this->assertStatus(200, $seller->get($p), 'vendedor ' . $p);
        }

        $admin = $this->browser();
        $this->assertRedirectTo('/plataforma', $admin->login('plataforma@demo.test', 'demo-motostock-2026'));
        foreach (['/plataforma', '/plataforma/lojas', '/plataforma/lojas/' . $tid, '/plataforma/planos', '/plataforma/planos/novo', '/plataforma/planos/1', '/plataforma/auditoria', '/plataforma/lojas/' . $tid . '/exportar'] as $p) {
            $this->assertStatus(200, $admin->get($p), $p);
        }

        $shop = $this->browser();
        $slug = $this->db->value('SELECT slug FROM products WHERE tenant_id = ? AND sku = ?', [$tid, 'KT-CG160']);
        foreach (['/', '/produto/' . $slug, '/carrinho', '/contato', '/privacidade', '/trocas-e-devolucoes', '/entrega', '/sobre', '/privacidade/solicitar', '/conta/entrar', '/conta/cadastro'] as $p) {
            $this->assertStatus(200, $shop->get('/loja/ze-motopecas' . $p), 'loja ' . $p);
        }
        $this->assertStatus(200, $shop->get('/loja/ze-motopecas/', ['moto' => 'Honda', 'modelo' => 'CG 160 Titan', 'ano' => '2020', 'ordem' => 'price_asc']));
        $this->assertStatus(404, $shop->get('/loja/ze-motopecas/pagina-que-nao-existe'));
        foreach (['/', '/entrar', '/cadastro', '/esqueci-senha'] as $p) {
            $this->assertStatus(200, $this->browser()->get($p), $p);
        }
    }
}
