<div class="page-head"><div><h1>Lojas</h1><p><?= count($rows) ?> resultado(s)</p></div></div>
<form class="filters card" method="get" action="<?= e(url('/plataforma/lojas')) ?>">
  <div class="field field--wide"><label for="q">Buscar</label><input id="q" type="search" name="q" value="<?= e($query['q'] ?? '') ?>" placeholder="Nome, endereço ou CNPJ"></div>
  <div class="field"><label for="situacao">Situação</label><select id="situacao" name="situacao" data-autosubmit><option value="">Todas</option><?php foreach (\App\Support\Labels::MAP['tenant'] as $k => $l): ?><option value="<?= e($k) ?>"<?= selected($query['situacao'] ?? '', $k) ?>><?= e($l) ?></option><?php endforeach; ?></select></div>
  <button class="btn btn--ghost" type="submit">Filtrar</button>
</form>
<div class="card">
  <?php if (!$rows): ?><?php $emptyTitle = 'Nenhuma loja encontrada'; $emptyText = null; $emptyAction = null; include __DIR__ . '/../partials/empty.php'; ?><?php else: ?>
  <div class="table-wrap"><table class="stack"><thead><tr><th>Loja</th><th>CNPJ</th><th>Plano</th><th>Situação</th><th>Criada</th></tr></thead><tbody>
    <?php foreach ($rows as $t): ?><tr>
      <td data-label="Loja"><a href="<?= e(url('/plataforma/lojas/' . $t['id'])) ?>"><?= e($t['trade_name']) ?></a> <span class="mono small muted"><?= e($t['slug']) ?></span><?= $t['is_demo'] ? ' <span class="badge badge--demo">demo</span>' : '' ?></td>
      <td data-label="CNPJ"><?= e(format_doc($t['document'])) ?></td>
      <td data-label="Plano"><?= e($t['plan_name']) ?></td>
      <td data-label="Situação"><?= badge('tenant', $t['status']) ?></td>
      <td data-label="Criada"><?= e(dt($t['created_at'], 'd/m/Y', 'America/Sao_Paulo')) ?></td>
    </tr><?php endforeach; ?>
  </tbody></table></div>
  <?php endif; ?>
</div>
