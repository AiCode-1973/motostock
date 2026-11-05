<div class="card">
  <div class="alert alert--warn"><strong>Ambiente de testes.</strong> Esta tela simula o provedor de pagamento. Nenhum valor é cobrado e ela não existe em produção.</div>
  <h1>Pagamento simulado</h1>
  <p>Pedido nº <?= (int) $payment['number'] ?> · <strong><?= e(money((int) $payment['amount_cents'])) ?></strong> · situação atual: <?= badge('payment', $payment['status']) ?></p>
  <p class="small muted">Ao escolher, o “provedor” envia uma notificação assinada para a loja, que confere a assinatura, consulta o pagamento e só então atualiza o pedido.</p>
  <?php if ($payment['status'] === 'pending'): ?>
  <div class="actions">
    <form method="post" action="<?= e(store_url('/pagamento-simulado/' . $payment['uuid'])) ?>"><?= csrf_field() ?><input type="hidden" name="retorno" value="<?= e($return ?? '') ?>"><input type="hidden" name="decision" value="approve"><button class="btn" type="submit">Aprovar pagamento</button></form>
    <form method="post" action="<?= e(store_url('/pagamento-simulado/' . $payment['uuid'])) ?>"><?= csrf_field() ?><input type="hidden" name="retorno" value="<?= e($return ?? '') ?>"><input type="hidden" name="decision" value="reject"><button class="btn btn--ghost" type="submit">Recusar</button></form>
  </div>
  <?php elseif ($return): ?><a class="btn" href="<?= e($return) ?>">Voltar ao pedido</a><?php endif; ?>
</div>
