<div class="auth">
  <form class="card" method="post" action="<?= e(store_url('/conta/cadastro')) ?>" novalidate>
    <h1>Criar conta</h1>
    <p class="muted small">Pedimos só o necessário para acompanhar seus pedidos.</p>
    <?= csrf_field() ?>
    <div class="field"><label class="req" for="name">Nome</label><input id="name" name="name" value="<?= e(old('name')) ?>" required autocomplete="name"<?= field_attrs('name') ?>><?= field_error('name') ?></div><br>
    <div class="field"><label class="req" for="email">E-mail</label><input id="email" name="email" type="email" value="<?= e(old('email')) ?>" required autocomplete="email"<?= field_attrs('email') ?>><?= field_error('email') ?></div><br>
    <div class="field"><label for="phone">Celular</label><input id="phone" name="phone" type="tel" value="<?= e(old('phone')) ?>" autocomplete="tel"<?= field_attrs('phone') ?>><?= field_error('phone') ?></div><br>
    <div class="field"><label class="req" for="password">Senha</label><input id="password" name="password" type="password" minlength="10" required autocomplete="new-password"<?= field_attrs('password') ?>><span class="hint">Mínimo de 10 caracteres.</span><?= field_error('password') ?></div><br>
    <label class="check"><input type="checkbox" name="accept_privacy" value="1"<?= field_attrs('accept_privacy') ?>> <span>Li e aceito a <a href="<?= e(store_url('/privacidade')) ?>" target="_blank">política de privacidade</a>.</span></label><?= field_error('accept_privacy') ?>
    <label class="check"><input type="checkbox" name="marketing_opt_in" value="1"> <span>Quero receber ofertas (opcional).</span></label>
    <div class="actions"><button class="btn btn--block" type="submit">Criar conta</button></div>
  </form>
</div>
