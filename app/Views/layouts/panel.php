<?php
$path = $request->path ?? '';
$nav = static function (string $href, string $label, string $perm) use ($path): string {
    if (!can($perm)) {
        return '';
    }
    $active = $href === '/painel' ? $path === '/painel' : str_starts_with($path, $href);
    return '<a href="' . e(url($href)) . '"' . ($active ? ' aria-current="page"' : '') . '>' . e($label) . '</a>';
};
$statusMsg = match ($tenant['status'] ?? '') {
    'trial' => 'Período de teste até ' . dt($tenant['trial_ends_at'], 'd/m/Y') . '.',
    'past_due' => 'Assinatura com pagamento pendente. ' . ($tenant['status_reason'] ?? ''),
    'suspended' => 'Loja suspensa: a loja virtual está fora do ar. ' . ($tenant['status_reason'] ?? ''),
    'cancelled' => 'Conta cancelada. Seus dados ficam disponíveis para exportação até ' . dt($tenant['retention_until'], 'd/m/Y') . '.',
    default => '',
};
?>
<!doctype html>
<html lang="pt-BR">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= e($title ?? 'Painel') ?> · <?= e($tenant['trade_name'] ?? '') ?> · MotoStock</title>
  <link rel="stylesheet" href="<?= e(asset('css/app.css')) ?>">
  <link rel="icon" href="<?= e(url('/assets/img/logo.svg')) ?>">
  <meta name="robots" content="noindex">
</head>
<body>
<a class="skip" href="#conteudo">Pular para o conteúdo</a>
<?php if (!empty($tenant['is_demo'])): ?><div class="demo-ribbon">Loja de DEMONSTRAÇÃO · dados fictícios, não use para operação real</div><?php endif; ?>
<div class="shell">
  <aside class="sidebar" aria-label="Menu do painel">
    <a class="brand" href="<?= e(url('/painel')) ?>"><img src="<?= e(url('/assets/img/logo-light.svg')) ?>" alt="MotoStock" width="137" height="30"></a>
    <?php if (count($memberships ?? []) > 1): ?>
      <form class="tenant-switch" method="post" action="<?= e(url('/conta/trocar-loja')) ?>">
        <?= csrf_field() ?>
        <label class="sr-only" for="tenant_id">Loja</label>
        <select id="tenant_id" name="tenant_id" data-autosubmit>
          <?php foreach ($memberships as $m): ?><option value="<?= (int) $m['id'] ?>"<?= selected($m['id'], $tenant['id']) ?>><?= e($m['trade_name']) ?></option><?php endforeach; ?>
        </select>
      </form>
    <?php endif; ?>
    <nav>
      <?= $nav('/painel', 'Visão geral', 'dashboard.view') ?>
      <div class="nav-group">Vendas</div>
      <?= $nav('/painel/pedidos', 'Pedidos', 'orders.view') ?>
      <?= can('orders.create') ? '<a href="' . e(url('/painel/pedidos/novo')) . '"' . ($path === '/painel/pedidos/novo' ? ' aria-current="page"' : '') . '>+ Venda no balcão</a>' : '' ?>
      <?= $nav('/painel/clientes', 'Clientes', 'customers.view') ?>
      <div class="nav-group">Catálogo e estoque</div>
      <?= $nav('/painel/produtos', 'Produtos', 'products.view') ?>
      <?= $nav('/painel/catalogo', 'Categorias e marcas', 'products.view') ?>
      <?= $nav('/painel/estoque', 'Estoque', 'stock.view') ?>
      <div class="nav-group">Compras</div>
      <?= $nav('/painel/fornecedores', 'Fornecedores', 'suppliers.view') ?>
      <?= $nav('/painel/compras', 'Pedidos de compra', 'purchases.view') ?>
      <div class="nav-group">Gestão</div>
      <?= $nav('/painel/relatorios', 'Relatórios', 'reports.view') ?>
      <?= $nav('/painel/usuarios', 'Equipe', 'users.manage') ?>
      <?= $nav('/painel/privacidade', 'Privacidade (LGPD)', 'privacy.manage') ?>
      <?= $nav('/painel/configuracoes', 'Configurações', 'settings.manage') ?>
      <?= $nav('/painel/auditoria', 'Auditoria', 'users.manage') ?>
      <a href="<?= e(url('/painel/assinatura')) ?>"<?= str_starts_with($path, '/painel/assinatura') ? ' aria-current="page"' : '' ?>>Assinatura</a>
    </nav>
  </aside>
  <div class="main">
    <header class="topbar">
      <button class="btn btn--ghost btn--sm nav-toggle" type="button" data-nav-toggle aria-expanded="false" aria-label="Abrir menu">☰ Menu</button>
      <a href="<?= e(\App\Core\Url::storefrontFor($tenant['slug'])) ?>" target="_blank" rel="noopener" class="small">Ver loja virtual ↗</a>
      <div class="who">
        <span class="hide-sm"><?= e($user['name']) ?> · <span class="muted"><?= e(label('role', $role)) ?></span></span>
        <a class="small" href="<?= e(url('/conta/seguranca')) ?>">Segurança</a>
        <form method="post" action="<?= e(url('/sair')) ?>" class="inline-form"><?= csrf_field() ?><button class="btn btn--ghost btn--sm" type="submit" data-no-loading>Sair</button></form>
      </div>
    </header>
    <?php if ($statusMsg !== ''): ?>
      <div class="status-banner<?= in_array($tenant['status'], ['suspended', 'cancelled'], true) ? ' status-banner--err' : '' ?>" role="status"><?= e($statusMsg) ?> <a href="<?= e(url('/painel/assinatura')) ?>">Ver assinatura</a></div>
    <?php endif; ?>
    <main class="content" id="conteudo">
      <?php include __DIR__ . '/../partials/flash.php'; ?>
      <?= $content ?>
    </main>
  </div>
</div>
<script src="<?= e(asset('js/app.js')) ?>" defer></script>
</body>
</html>
