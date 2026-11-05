<?php
$o = $order;
$actionLabels = ['picking' => 'Iniciar separação', 'shipped' => 'Marcar como enviado', 'delivered' => 'Marcar como entregue', 'cancelled' => 'Cancelar pedido', 'refunded' => 'Registrar reembolso'];
$wasPaid = in_array($o['status'], \App\Services\OrderService::PAID_STATES, true);
?>
<div class="page-head">
  <div><p><a href="<?= e(url('/painel/pedidos')) ?>">← Pedidos</a></p>
    <h1>Pedido nº <?= (int) $o['number'] ?> <?= badge('order', $o['status']) ?></h1>
    <p><?= e(label('channel', $o['channel'])) ?> · <?= e(dt($o['placed_at'])) ?><?= $o['operator_name'] ? ' · operador: ' . e($o['operator_name']) : '' ?></p></div>
</div>
<?php if ($o['status'] === 'awaiting_payment' && $o['expires_at']): ?><div class="alert alert--warn">Estoque reservado. Se não houver pagamento até <?= e(dt($o['expires_at'])) ?>, o pedido é cancelado automaticamente e a reserva liberada.</div><?php endif; ?>

<div class="grid grid-2">
  <div class="card">
    <h2>Itens</h2>
    <div class="table-wrap"><table>
      <thead><tr><th>Produto</th><th class="num">Qtd.</th><th class="num">Unit.</th><th class="num">Total</th></tr></thead>
      <tbody><?php foreach ($items as $i): ?><tr><td><?= e($i['name']) ?> <span class="mono small muted"><?= e($i['sku']) ?></span><?= $i['fitment_note'] ? '<br><span class="small">Moto do cliente: ' . e($i['fitment_note']) . '</span>' : '' ?></td><td class="num"><?= (int) $i['quantity'] ?></td><td class="num"><?= e(money((int) $i['unit_price_cents'])) ?></td><td class="num"><?= e(money((int) $i['total_cents'])) ?></td></tr><?php endforeach; ?></tbody>
    </table></div>
    <div class="summary-line"><span>Subtotal</span><span><?= e(money((int) $o['subtotal_cents'])) ?></span></div>
    <?php if ($o['discount_cents'] > 0): ?><div class="summary-line"><span>Desconto</span><span>− <?= e(money((int) $o['discount_cents'])) ?></span></div><?php endif; ?>
    <div class="summary-line"><span>Frete<?= $o['shipping_name'] ? ' (' . e($o['shipping_name']) . ')' : '' ?></span><span><?= e(money((int) $o['shipping_cents'])) ?></span></div>
    <div class="summary-line summary-total"><span>Total</span><span><?= e(money((int) $o['total_cents'])) ?></span></div>
  </div>
  <div class="card">
    <h2>Cliente e entrega</h2>
    <dl class="dl">
      <dt>Cliente</dt><dd><?= $o['customer_id'] && can('customers.view') ? '<a href="' . e(url('/painel/clientes/' . $o['customer_id'])) . '">' . e($o['customer_name']) . '</a>' : e($o['customer_name']) ?></dd>
      <?php if ($o['customer_email']): ?><dt>E-mail</dt><dd><?= e($o['customer_email']) ?></dd><?php endif; ?>
      <?php if ($o['customer_phone']): ?><dt>Telefone</dt><dd><?= e(format_phone($o['customer_phone'])) ?></dd><?php endif; ?>
      <dt>Entrega</dt><dd><?= e($o['shipping_name'] ?? 'Retirada no balcão') ?><?= $o['shipping_days'] ? ' · ' . e($o['shipping_days']) : '' ?></dd>
      <?php if ($o['ship_street']): ?><dt>Endereço</dt><dd><?= e($o['ship_street'] . ', ' . $o['ship_number'] . ($o['ship_complement'] ? ' – ' . $o['ship_complement'] : '')) ?><br><?= e(($o['ship_district'] ? $o['ship_district'] . ' · ' : '') . $o['ship_city'] . '/' . $o['ship_state'] . ' · CEP ' . format_cep($o['ship_zip'])) ?></dd><?php endif; ?>
      <?php if ($o['tracking_code']): ?><dt>Rastreio</dt><dd class="mono"><?= e($o['tracking_code']) ?></dd><?php endif; ?>
      <?php if ($o['notes']): ?><dt>Observações</dt><dd><?= e($o['notes']) ?></dd><?php endif; ?>
    </dl>
    <?php if (can('orders.manage') && in_array($o['status'], ['paid', 'picking', 'shipped'], true)): ?>
      <form method="post" action="<?= e(url('/painel/pedidos/' . $o['id'] . '/rastreio')) ?>" class="filters">
        <?= csrf_field() ?><div class="field"><label for="tracking_code">Código de rastreio</label><input id="tracking_code" name="tracking_code" value="<?= e($o['tracking_code'] ?? '') ?>" maxlength="60"<?= field_attrs('tracking_code') ?>><?= field_error('tracking_code') ?></div><button class="btn btn--ghost" type="submit">Salvar</button>
      </form>
    <?php endif; ?>
  </div>

  <div class="card">
    <h2>Pagamentos</h2>
    <?php if (!$payments): ?><p class="muted">Nenhum pagamento registrado.</p><?php else: ?>
      <ul class="list-plain"><?php foreach ($payments as $p): ?><li><span><?= e($p['gateway'] === 'manual' ? label('pay_method', $p['method']) : ucfirst($p['gateway']) . ' (online)') ?><br><span class="small muted"><?= e(dt($p['created_at'])) ?><?= $p['recorded_by_name'] ? ' · ' . e($p['recorded_by_name']) : '' ?><?= $p['external_id'] ? ' · id ' . e($p['external_id']) : '' ?><?= $p['status_detail'] ? ' · ' . e($p['status_detail']) : '' ?></span></span><span><?= badge('payment', $p['status']) ?> <?= e(money((int) $p['amount_cents'])) ?></span></li><?php endforeach; ?></ul>
    <?php endif; ?>
    <?php if ($o['status'] === 'awaiting_payment' && can('orders.manage')): ?>
      <form method="post" action="<?= e(url('/painel/pedidos/' . $o['id'] . '/pagamento')) ?>" data-confirm="Confirmar que <?= e(money((int) $o['total_cents'])) ?> foi recebido?">
        <?= csrf_field() ?>
        <h3>Registrar pagamento recebido na loja</h3>
        <div class="form-grid">
          <div class="field"><label for="method">Forma</label><select id="method" name="method" required><?php foreach (['cash', 'pix_manual', 'card_pos', 'transfer'] as $m): ?><option value="<?= $m ?>"><?= e(label('pay_method', $m)) ?></option><?php endforeach; ?></select></div>
          <div class="field"><label for="pnote">Observação</label><input id="pnote" name="note" maxlength="120" placeholder="Ex.: NSU da maquininha"></div>
        </div>
        <div class="actions"><button class="btn" type="submit">Registrar <?= e(money((int) $o['total_cents'])) ?></button></div>
      </form>
      <p class="small muted">Pagamentos online são confirmados automaticamente pela notificação do provedor.</p>
    <?php endif; ?>
  </div>

  <div class="card">
    <h2>Andamento</h2>
    <ol class="timeline">
      <?php foreach ($history as $h): ?><li><strong><?= e(label('order', $h['to_status'])) ?></strong> <span class="small muted"><?= e(dt($h['created_at'])) ?> · <?= e($h['user_name'] ?? match ($h['actor']) { 'gateway' => 'Provedor de pagamento', 'customer' => 'Cliente', 'system' => 'Sistema', default => 'Equipe' }) ?></span><?= $h['note'] ? '<br><span class="small">' . e($h['note']) . '</span>' : '' ?></li><?php endforeach; ?>
    </ol>
    <?php $actions = array_values(array_filter($next, fn($s) => $s !== 'paid' && ($s !== 'refunded' || can('orders.refund')))); ?>
    <?php if ($actions && can('orders.manage')): ?>
      <form method="post" action="<?= e(url('/painel/pedidos/' . $o['id'] . '/status')) ?>">
        <?= csrf_field() ?>
        <div class="form-grid">
          <div class="field"><label for="to">Próximo passo</label><select id="to" name="to"><?php foreach ($actions as $s): ?><option value="<?= e($s) ?>"><?= e($actionLabels[$s] ?? label('order', $s)) ?></option><?php endforeach; ?></select></div>
          <div class="field"><label for="note">Observação</label><input id="note" name="note" maxlength="255"></div>
        </div>
        <?php if (in_array('refunded', $actions, true) && in_array($o['status'], ['shipped', 'delivered'], true)): ?><label class="check"><input type="checkbox" name="restock" value="1"> No reembolso, a peça voltou para a loja (devolver ao estoque)</label><?php endif; ?>
        <?php if ($wasPaid): ?><p class="small muted">Cancelar ou reembolsar não estorna automaticamente no provedor: faça o estorno no painel do Mercado Pago ou devolva o valor ao cliente.</p><?php endif; ?>
        <div class="actions"><button class="btn" type="submit">Atualizar pedido</button></div>
      </form>
    <?php endif; ?>
  </div>
</div>
