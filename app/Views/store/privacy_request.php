<div class="auth auth--wide">
  <form class="card" method="post" action="<?= e(store_url('/privacidade/solicitar')) ?>" novalidate>
    <h1>Solicitar meus dados (LGPD)</h1>
    <p class="muted">Você pode pedir confirmação, acesso, correção, portabilidade ou eliminação dos seus dados pessoais tratados por <?= e($store['trade_name']) ?>. A loja vai confirmar sua identidade antes de responder.</p>
    <?= csrf_field() ?>
    <div class="form-grid">
      <div class="field"><label class="req" for="email">E-mail usado nas compras</label><input id="email" name="email" type="email" value="<?= e(old('email')) ?>" required<?= field_attrs('email') ?>><?= field_error('email') ?></div>
      <div class="field"><label class="req" for="type">O que você deseja</label><select id="type" name="type"><?php foreach (\App\Support\Labels::MAP['data_request'] as $k => $l): ?><option value="<?= e($k) ?>"<?= selected(old('type'), $k) ?>><?= e($l) ?></option><?php endforeach; ?></select></div>
      <div class="field span-2"><label for="message">Detalhes (opcional)</label><textarea id="message" name="message" maxlength="2000"><?= e(old('message')) ?></textarea><span class="hint">Não envie documentos por aqui.</span></div>
    </div>
    <div class="actions"><button class="btn" type="submit">Enviar solicitação</button></div>
  </form>
</div>
