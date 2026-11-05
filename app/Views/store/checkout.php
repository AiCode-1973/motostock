<?php $c = $customer ?? null; ?>
<h1>Finalizar compra</h1>
<div class="steps"><span class="done">Carrinho</span><span class="current">Dados e entrega</span><span>Pagamento</span><span>Confirmação</span></div>
<?php if (!$methods): ?>
  <div class="alert alert--error">A loja ainda não tem formas de entrega ativas. Entre em contato pelo <a href="<?= e(store_url('/contato')) ?>">atendimento</a>.</div>
<?php else: ?>
<form method="post" action="<?= e(store_url('/checkout')) ?>" class="grid grid-2" novalidate>
  <?= csrf_field() ?>
  <div>
    <div class="card">
      <h2>Seus dados</h2>
      <?php if (!$c): ?><p class="small">Já tem conta? <a href="<?= e(store_url('/conta/entrar')) ?>">Entrar</a>. Ou compre sem cadastro.</p><?php endif; ?>
      <div class="form-grid">
        <div class="field span-2"><label class="req" for="name">Nome completo</label><input id="name" name="name" autocomplete="name" value="<?= e(old('name', $c['name'] ?? '')) ?>" required<?= field_attrs('name') ?>><?= field_error('name') ?></div>
        <div class="field"><label class="req" for="email">E-mail</label><input id="email" name="email" type="email" autocomplete="email" value="<?= e(old('email', $c['email'] ?? '')) ?>" required<?= field_attrs('email') ?>><span class="hint">Enviamos o link de acompanhamento para ele.</span><?= field_error('email') ?></div>
        <div class="field"><label class="req" for="phone">Celular</label><input id="phone" name="phone" type="tel" autocomplete="tel" value="<?= e(old('phone', $c['phone'] ?? '')) ?>" required<?= field_attrs('phone') ?>><?= field_error('phone') ?></div>
      </div>
    </div>
    <div class="card">
      <h2>Entrega</h2>
      <?php foreach ($methods as $i => $m): ?>
        <label class="ship-option"><input type="radio" name="shipping_method_id" value="<?= (int) $m['id'] ?>" data-kind="<?= e($m['kind']) ?>"<?= checked((int) old('shipping_method_id', $methods[0]['id']) === (int) $m['id']) ?>>
          <span><strong><?= e($m['name']) ?></strong> · <?= $m['cost'] === 0 ? 'grátis' : e(money($m['cost'])) ?><br><span class="small muted"><?= e($m['days']) ?><?= $m['regions'] ? ' · somente ' . e($m['regions']) : '' ?></span><br><span class="small">Total do pedido com esta entrega: <strong><?= e(money($cart['subtotal'] + $m['cost'])) ?></strong></span></span></label>
      <?php endforeach; ?>
      <?= field_error('shipping_method_id') ?>
      <div class="form-grid" data-address>
        <div class="field"><label class="req" for="zip">CEP</label><input id="zip" name="zip" inputmode="numeric" autocomplete="postal-code" value="<?= e(old('zip')) ?>"<?= field_attrs('zip') ?>><?= field_error('zip') ?></div>
        <div class="field span-2"><label class="req" for="street">Rua</label><input id="street" name="street" autocomplete="address-line1" value="<?= e(old('street')) ?>"></div>
        <div class="field"><label class="req" for="number">Número</label><input id="number" name="number" value="<?= e(old('number')) ?>"></div>
        <div class="field"><label for="complement">Complemento</label><input id="complement" name="complement" autocomplete="address-line2" value="<?= e(old('complement')) ?>"></div>
        <div class="field"><label for="district">Bairro</label><input id="district" name="district" value="<?= e(old('district')) ?>"></div>
        <div class="field"><label class="req" for="city">Cidade</label><input id="city" name="city" autocomplete="address-level2" value="<?= e(old('city')) ?>"></div>
        <div class="field"><label class="req" for="state">UF</label><select id="state" name="state"<?= field_attrs('state') ?>><option value="">—</option><?php foreach (\App\Core\Validator::UFS as $uf): ?><option<?= selected(old('state'), $uf) ?>><?= $uf ?></option><?php endforeach; ?></select><?= field_error('state') ?></div>
      </div>
      <div class="field"><label for="notes">Observações para a loja</label><input id="notes" name="notes" maxlength="500" value="<?= e(old('notes')) ?>" placeholder="Ponto de referência, horário para entrega…"></div>
    </div>
  </div>
  <div>
    <div class="card">
      <h2>Resumo do pedido</h2>
      <?php foreach ($cart['lines'] as $l): ?><div class="summary-line"><span><?= (int) $l['qty'] ?>× <?= e($l['name']) ?><?= $l['fitment'] ? '<br><span class="small muted">para ' . e($l['fitment']) . '</span>' : '' ?></span><span class="nowrap"><?= e(money((int) $l['total'])) ?></span></div><?php endforeach; ?>
      <div class="summary-line summary-total"><span>Subtotal</span><span><?= e(money($cart['subtotal'])) ?></span></div>
      <p class="small muted">Some o frete da forma de entrega escolhida: o total de cada opção aparece ao lado dela. Não há outras taxas.</p>
    </div>
    <div class="card">
      <h2>Pagamento</h2>
      <?php if ($online): ?>
        <label class="ship-option"><input type="radio" name="payment" value="online"<?= checked(old('payment', 'online') === 'online') ?>><span><strong>Pix ou cartão</strong><br><span class="small muted">Você será levado ao ambiente seguro do provedor de pagamento. A loja não vê os dados do cartão.</span></span></label>
      <?php endif; ?>
      <label class="ship-option"><input type="radio" name="payment" value="offline"<?= checked(!$online || old('payment') === 'offline') ?>><span><strong>Combinar com a loja</strong><br><span class="small muted">A loja entra em contato para o pagamento. O estoque fica reservado por até 48 horas.</span></span></label>
      <?php if ($needsFitment): ?>
        <label class="check"><input type="checkbox" name="accept_fitment" value="1"<?= checked(old('accept_fitment')) ?><?= field_attrs('accept_fitment') ?>> <span>Conferi a compatibilidade das peças com a minha moto.</span></label><?= field_error('accept_fitment') ?>
      <?php endif; ?>
      <label class="check"><input type="checkbox" name="accept_privacy" value="1"<?= checked(old('accept_privacy')) ?><?= field_attrs('accept_privacy') ?>> <span>Li e aceito a <a href="<?= e(store_url('/privacidade')) ?>" target="_blank">política de privacidade</a> e as <a href="<?= e(store_url('/trocas-e-devolucoes')) ?>" target="_blank">condições de troca</a>.</span></label><?= field_error('accept_privacy') ?>
      <label class="check"><input type="checkbox" name="marketing_opt_in" value="1"<?= checked(old('marketing_opt_in')) ?>> <span>Quero receber ofertas da loja (opcional).</span></label>
      <div class="actions"><button class="btn btn--block" type="submit" data-loading="Enviando pedido…">Confirmar pedido</button></div>
      <p class="small muted">Ao confirmar, você faz um pedido com obrigação de pagamento. Você pode desistir em até 7 dias após o recebimento.</p>
    </div>
  </div>
</form>
<?php endif; ?>
