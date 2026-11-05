<?php
$max = max(1, ...array_map(fn($r) => (int) $r['revenue'], $d['daily'] ?: [['revenue' => 1]]));
$pending = array_column($d['pending'], 'n', 'status');
?>
<div class="page-head">
  <div><h1>Visão geral</h1><p><?= e($period->label()) ?> · fuso <?= e($period->tz) ?></p></div>
  <form class="filters" method="get" action="<?= e(url('/painel')) ?>">
    <div class="field"><label for="de">De</label><input type="date" id="de" name="de" value="<?= e($period->from) ?>"></div>
    <div class="field"><label for="ate">Até</label><input type="date" id="ate" name="ate" value="<?= e($period->to) ?>"></div>
    <button class="btn btn--ghost" type="submit">Aplicar</button>
  </form>
</div>

<?php if ($productsCount === 0): ?>
  <div class="card">
    <h2>Primeiros passos</h2>
    <ol>
      <li><a href="<?= e(url('/painel/catalogo')) ?>">Crie categorias e marcas</a> (ex.: Freios, Transmissão; Cobreq, Riffel).</li>
      <li><a href="<?= e(url('/painel/produtos/novo')) ?>">Cadastre seus produtos</a> ou <a href="<?= e(url('/painel/catalogo/importar')) ?>">importe uma planilha</a>.</li>
      <li><a href="<?= e(url('/painel/configuracoes')) ?>">Revise dados da loja, políticas e formas de entrega</a>.</li>
      <li>Abra a loja virtual e faça um pedido de teste.</li>
    </ol>
  </div>
<?php endif; ?>

<div class="kpis">
  <div class="kpi"><div class="kpi-label">Faturamento</div><div class="kpi-value"><?= e(money($d['revenue'])) ?></div><div class="kpi-note">pagamentos confirmados no período</div></div>
  <div class="kpi"><div class="kpi-label">Pedidos pagos</div><div class="kpi-value"><?= (int) $d['paid_orders'] ?></div><div class="kpi-note"><?= (int) $d['placed_orders'] ?> pedido(s) recebido(s)</div></div>
  <div class="kpi"><div class="kpi-label">Ticket médio</div><div class="kpi-value"><?= e(money($d['avg_ticket'])) ?></div><div class="kpi-note">faturamento ÷ pedidos pagos</div></div>
  <div class="kpi"><div class="kpi-label">Precisam de ação</div><div class="kpi-value"><?= array_sum($pending) ?></div><div class="kpi-note"><?= (int) ($pending['awaiting_payment'] ?? 0) ?> aguardando pagamento · <?= (int) (($pending['paid'] ?? 0) + ($pending['picking'] ?? 0)) ?> para separar/enviar</div></div>
</div>

<div class="grid grid-2">
  <div class="card">
    <h2>Faturamento por dia</h2>
    <?php if (!$d['daily']): ?>
      <?php $emptyTitle = 'Sem vendas pagas no período'; $emptyText = 'As vendas aparecem aqui quando o pagamento é confirmado.'; $emptyAction = null; include __DIR__ . '/../partials/empty.php'; ?>
    <?php else: ?>
      <div class="bars" role="img" aria-label="Faturamento diário no período">
        <?php foreach ($d['daily'] as $day): $h = (int) round(((int) $day['revenue'] / $max) * 10) * 10; ?>
          <span class="h-<?= max(10, $h) ?>" title="<?= e(date('d/m', strtotime($day['day'])) . ': ' . money((int) $day['revenue']) . ' · ' . $day['orders'] . ' pedido(s)') ?>"></span>
        <?php endforeach; ?>
      </div>
      <p class="small muted">Passe o mouse nas barras para ver o valor de cada dia. Detalhes em <a href="<?= e(url('/painel/relatorios/vendas', ['de' => $period->from, 'ate' => $period->to])) ?>">Relatórios › Vendas</a>.</p>
    <?php endif; ?>
  </div>
  <div class="card">
    <h2>Mais vendidos</h2>
    <?php if (!$d['top_products']): ?>
      <?php $emptyTitle = 'Nenhum produto vendido no período'; $emptyText = null; include __DIR__ . '/../partials/empty.php'; ?>
    <?php else: ?>
      <ul class="list-plain">
        <?php foreach ($d['top_products'] as $p): ?><li><span><?= e($p['name']) ?> <span class="muted small mono"><?= e($p['sku']) ?></span></span><span class="nowrap"><?= (int) $p['qty'] ?> un. · <?= e(money((int) $p['revenue'])) ?></span></li><?php endforeach; ?>
      </ul>
    <?php endif; ?>
  </div>
  <div class="card">
    <div class="card-head"><h2>Estoque baixo</h2><a class="small" href="<?= e(url('/painel/estoque', ['baixo' => 1])) ?>">Ver todos</a></div>
    <?php if (!$d['low_stock']): ?>
      <?php $emptyTitle = 'Tudo acima do mínimo'; $emptyText = 'Nenhum produto ativo está com disponível igual ou abaixo do estoque mínimo.'; include __DIR__ . '/../partials/empty.php'; ?>
    <?php else: ?>
      <ul class="list-plain">
        <?php foreach ($d['low_stock'] as $p): ?><li><span><?= e($p['name']) ?></span><span class="nowrap"><span class="badge badge--low"><?= (int) $p['available'] ?> disp.</span> <span class="muted small">mín. <?= (int) $p['min_stock'] ?></span>
          <?php if (can('purchases.manage')): ?> · <a class="small" href="<?= e(url('/painel/compras/nova')) ?>">Comprar</a><?php endif; ?></span></li><?php endforeach; ?>
      </ul>
    <?php endif; ?>
  </div>
  <div class="card">
    <div class="card-head"><h2>Últimos pedidos</h2><a class="small" href="<?= e(url('/painel/pedidos')) ?>">Ver todos</a></div>
    <?php if (!$d['recent']): ?>
      <?php $emptyTitle = 'Nenhum pedido ainda'; $emptyText = 'Os pedidos da loja virtual e do balcão aparecem aqui.'; $emptyAction = can('orders.create') ? ['Registrar venda no balcão', url('/painel/pedidos/novo')] : null; include __DIR__ . '/../partials/empty.php'; ?>
    <?php else: ?>
      <ul class="list-plain">
        <?php foreach ($d['recent'] as $o): ?><li><a href="<?= e(url('/painel/pedidos/' . $o['id'])) ?>">nº <?= (int) $o['number'] ?> · <?= e($o['customer_name']) ?></a><span class="nowrap"><?= badge('order', $o['status']) ?> <?= e(money((int) $o['total_cents'])) ?></span></li><?php endforeach; ?>
      </ul>
    <?php endif; ?>
  </div>
</div>

<details class="card">
  <summary><strong>Como calculamos estes números</strong></summary>
  <ul class="small">
    <li><strong>Faturamento</strong>: soma do total dos pedidos com pagamento confirmado (pago, separando, enviado ou entregue), pela data do pagamento, no fuso da loja. Cancelados e reembolsados não entram.</li>
    <li><strong>Pedidos recebidos</strong>: todos os pedidos criados no período, em qualquer situação.</li>
    <li><strong>Ticket médio</strong>: faturamento dividido pela quantidade de pedidos pagos no período.</li>
    <li><strong>Estoque baixo</strong>: produtos ativos com disponível (físico − reservado) igual ou menor que o mínimo.</li>
    <li><strong>Uso do plano <?= e($plan['name']) ?></strong>: <?= (int) $usage['products'] ?> produto(s), <?= (int) $usage['users'] ?> usuário(s), <?= (int) $usage['orders'] ?> pedido(s) neste mês.</li>
  </ul>
</details>
