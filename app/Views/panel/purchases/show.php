<?php $canReceive = can('purchases.manage') && in_array($po['status'], ['ordered', 'partial'], true); ?>
<div class="page-head">
  <div><p><a href="<?= e(url('/painel/compras')) ?>">← Compras</a></p><h1>Compra nº <?= (int) $po['number'] ?> <?= badge('purchase', $po['status']) ?></h1>
    <p><?= e($po['supplier_name']) ?> · criada em <?= e(dt($po['created_at'])) ?><?= $po['expected_at'] ? ' · previsão ' . e(date('d/m/Y', strtotime($po['expected_at']))) : '' ?><?= $po['received_at'] ? ' · recebida em ' . e(dt($po['received_at'])) : '' ?></p></div>
  <?php if (can('purchases.manage')): ?><div class="actions">
    <?php if ($po['status'] === 'draft'): ?><form method="post" action="<?= e(url('/painel/compras/' . $po['id'] . '/enviar')) ?>"><?= csrf_field() ?><button class="btn" type="submit">Marcar como enviada ao fornecedor</button></form><?php endif; ?>
    <?php if (in_array($po['status'], ['draft', 'ordered'], true)): ?><form method="post" action="<?= e(url('/painel/compras/' . $po['id'] . '/cancelar')) ?>" data-confirm="Cancelar esta compra?"><?= csrf_field() ?><button class="btn btn--ghost" type="submit">Cancelar compra</button></form><?php endif; ?>
  </div><?php endif; ?>
</div>
<?php if ($po['notes']): ?><div class="alert alert--info"><?= e($po['notes']) ?></div><?php endif; ?>
<form method="post" action="<?= e(url('/painel/compras/' . $po['id'] . '/receber')) ?>" class="card">
  <?= csrf_field() ?>
  <h2>Itens</h2>
  <div class="table-wrap"><table class="stack">
    <thead><tr><th>Produto</th><th class="num">Pedido</th><th class="num">Recebido</th><th class="num">Custo unit.</th><th class="num">Total</th><?php if ($canReceive): ?><th>Receber agora</th><?php endif; ?></tr></thead>
    <tbody><?php foreach ($items as $i): $pending = (int) $i['quantity'] - (int) $i['quantity_received']; ?><tr>
      <td data-label="Produto"><?= e($i['name']) ?> <span class="mono small muted"><?= e($i['sku']) ?></span></td>
      <td data-label="Pedido" class="num"><?= (int) $i['quantity'] ?></td>
      <td data-label="Recebido" class="num"><?= (int) $i['quantity_received'] ?></td>
      <td data-label="Custo unit." class="num"><?= e(money((int) $i['unit_cost_cents'])) ?></td>
      <td data-label="Total" class="num"><?= e(money((int) $i['unit_cost_cents'] * (int) $i['quantity'])) ?></td>
      <?php if ($canReceive): ?><td data-label="Receber agora"><?php if ($pending > 0): ?><label class="sr-only" for="rec<?= (int) $i['id'] ?>">Quantidade recebida</label><input id="rec<?= (int) $i['id'] ?>" type="number" min="0" max="<?= $pending ?>" name="received[<?= (int) $i['id'] ?>]" value="<?= $pending ?>"><?php else: ?>Completo<?php endif; ?></td><?php endif; ?>
    </tr><?php endforeach; ?></tbody>
    <tfoot><tr><td colspan="4">Total da compra</td><td class="num"><?= e(money((int) $po['total_cents'])) ?></td><?php if ($canReceive): ?><td></td><?php endif; ?></tr></tfoot>
  </table></div>
  <?php if ($canReceive): ?><div class="actions"><button class="btn" type="submit" data-loading="Registrando…">Registrar recebimento</button><span class="muted small">Gera entrada no estoque para cada item e atualiza o custo do produto.</span></div><?php endif; ?>
  <?php if ($po['status'] === 'draft'): ?><p class="muted small">Envie a compra ao fornecedor para poder registrar o recebimento.</p><?php endif; ?>
</form>
