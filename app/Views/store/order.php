<?php $o = $order; $steps = ['awaiting_payment', 'paid', 'picking', 'shipped', 'delivered']; $pos = array_search($o['status'], $steps, true); ?>
<h1>Pedido nº <?= (int) $o['number'] ?></h1>
<p class="muted">Feito em <?= e(dt($o['placed_at'])) ?> · <?= badge('order', $o['status']) ?></p>
<?php if ($pos !== false): ?>
  <div class="steps"><?php foreach ($steps as $i => $s): ?><span class="<?= $i < $pos ? 'done' : ($i === $pos ? 'current' : '') ?>"><?= e(label('order', $s)) ?></span><?php endforeach; ?></div>
<?php endif; ?>
<?php if ($o['status'] === 'awaiting_payment'): ?>
  <div class="alert alert--info">
    Aguardando a confirmação do pagamento. Se você já pagou, a confirmação do provedor pode levar alguns minutos: atualize esta página.
    <?php if ($o['expires_at']): ?>Os itens ficam reservados até <?= e(dt($o['expires_at'])) ?>.<?php endif; ?>
  </div>
  <?php if ($online): ?>
    <form method="post" action="<?= e(store_url('/pedido/' . $o['number'] . '/' . $token . '/pagar')) ?>"><?= csrf_field() ?><button class="btn" type="submit" data-loading="Abrindo pagamento…">Pagar agora com Pix ou cartão</button></form><br>
  <?php endif; ?>
<?php endif; ?>
<div class="grid grid-2">
  <div class="card">
    <h2>Itens</h2>
    <?php foreach ($items as $i): ?><div class="summary-line"><span><?= (int) $i['quantity'] ?>× <?= e($i['name']) ?></span><span><?= e(money((int) $i['total_cents'])) ?></span></div><?php endforeach; ?>
    <div class="summary-line"><span>Frete (<?= e($o['shipping_name'] ?? '') ?>)</span><span><?= e(money((int) $o['shipping_cents'])) ?></span></div>
    <?php if ($o['discount_cents'] > 0): ?><div class="summary-line"><span>Desconto</span><span>− <?= e(money((int) $o['discount_cents'])) ?></span></div><?php endif; ?>
    <div class="summary-line summary-total"><span>Total</span><span><?= e(money((int) $o['total_cents'])) ?></span></div>
  </div>
  <div class="card">
    <h2>Entrega</h2>
    <p><?= e($o['shipping_name'] ?? '') ?> · <?= e($o['shipping_days'] ?? '') ?></p>
    <?php if ($o['ship_street']): ?><p><?= e($o['ship_street'] . ', ' . $o['ship_number']) ?><br><?= e($o['ship_city'] . '/' . $o['ship_state']) ?></p><?php endif; ?>
    <?php if ($o['tracking_code']): ?><p>Código de rastreio: <strong class="mono"><?= e($o['tracking_code']) ?></strong></p><?php endif; ?>
    <h2>Andamento</h2>
    <ol class="timeline"><?php foreach ($history as $h): ?><li><strong><?= e(label('order', $h['to_status'])) ?></strong> <span class="small muted"><?= e(dt($h['created_at'])) ?></span></li><?php endforeach; ?></ol>
    <p class="small">Dúvidas ou desistência? <a href="<?= e(store_url('/contato')) ?>">Fale com a loja</a> informando o número do pedido.</p>
  </div>
</div>
