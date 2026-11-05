<?php
$columns = $data['columns'];
if (!$canSeeCost) {
    unset($columns['cost_cents'], $columns['margin_cents'], $columns['value_cents'], $columns['cost_cents']);
}
$fmt = function (string $c, $v) use ($key): string {
    if (str_ends_with($c, '_cents')) return e(money((int) $v));
    if (in_array($c, ['placed_at', 'paid_at', 'created_at'], true)) return e(dt($v));
    if ($c === 'status') return badge($key === 'pagamentos' ? 'payment' : 'order', $v);
    if ($c === 'channel') return e(label('channel', $v));
    return e($v ?? '—');
};
$numeric = fn(string $c) => str_ends_with($c, '_cents') || in_array($c, ['qty', 'orders', 'stock_qty', 'reserved_qty', 'available', 'min_stock', 'number', 'avg_delay_days'], true);
?>
<div class="page-head">
  <div><p><a href="<?= e(url('/painel/relatorios')) ?>">← Relatórios</a></p><h1><?= e($title) ?></h1><p><?= $key === 'estoque' ? 'Posição atual' : e($period->label()) ?> · <?= count($data['rows']) ?> linha(s)</p></div>
  <?php if (can('reports.export') && $data['rows']): ?><a class="btn btn--ghost" href="<?= e(url('/painel/relatorios/' . $key . '/csv', array_filter(['de' => $period->from, 'ate' => $period->to, 'situacao' => $query['situacao'] ?? '']))) ?>">Exportar CSV</a><?php endif; ?>
</div>
<?php if ($key !== 'estoque'): ?>
<form class="filters card" method="get" action="<?= e(url('/painel/relatorios/' . $key)) ?>">
  <div class="field"><label for="de">De</label><input type="date" id="de" name="de" value="<?= e($period->from) ?>"></div>
  <div class="field"><label for="ate">Até</label><input type="date" id="ate" name="ate" value="<?= e($period->to) ?>"></div>
  <?php if ($key === 'vendas'): ?><div class="field"><label for="situacao">Situação</label><select id="situacao" name="situacao"><option value="">Todas</option><?php foreach (\App\Support\Labels::MAP['order'] as $k => $l): ?><option value="<?= e($k) ?>"<?= selected($query['situacao'] ?? '', $k) ?>><?= e($l) ?></option><?php endforeach; ?></select></div><?php endif; ?>
  <button class="btn btn--ghost" type="submit">Aplicar</button>
</form>
<?php endif; ?>
<p class="small muted"><strong>Regra:</strong> <?= e($data['note']) ?> Período máximo: 366 dias.</p>
<div class="card">
  <?php if (!$data['rows']): ?>
    <?php $emptyTitle = 'Sem dados no período'; $emptyText = 'Ajuste as datas ou registre movimentações.'; $emptyAction = null; include __DIR__ . '/../../partials/empty.php'; ?>
  <?php else: ?>
  <div class="table-wrap"><table class="stack">
    <thead><tr><?php foreach ($columns as $c => $l): ?><th class="<?= $numeric($c) ? 'num' : '' ?>"><?= e($l) ?></th><?php endforeach; ?></tr></thead>
    <tbody><?php foreach ($data['rows'] as $row): ?><tr><?php foreach ($columns as $c => $l): ?><td data-label="<?= e($l) ?>" class="<?= $numeric($c) ? 'num' : '' ?>"><?= $fmt($c, $row[$c] ?? null) ?></td><?php endforeach; ?></tr><?php endforeach; ?></tbody>
    <tfoot><tr><?php $first = true; foreach ($columns as $c => $l): ?><td class="<?= $numeric($c) ? 'num' : '' ?>"><?php if ($first) { echo 'Total'; $first = false; } elseif (isset($data['totals'][$c])) { echo str_ends_with($c, '_cents') ? e(money((int) $data['totals'][$c])) : (int) $data['totals'][$c]; } ?></td><?php endforeach; ?></tr></tfoot>
  </table></div>
  <?php endif; ?>
</div>
