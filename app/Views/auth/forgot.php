<div class="auth">
  <form method="post" action="<?= e(url('/esqueci-senha')) ?>" class="card">
    <h1>Recuperar senha</h1>
    <p class="muted">Informe o e-mail da sua conta. Se ele estiver cadastrado, enviaremos um link válido por 1 hora.</p>
    <?= csrf_field() ?>
    <div class="field"><label for="email">E-mail</label><input id="email" name="email" type="email" required autocomplete="email"></div>
    <div class="actions"><button class="btn btn--block" type="submit" data-loading="Enviando…">Enviar link</button></div>
    <p class="small"><a href="<?= e(url('/entrar')) ?>">Voltar para o login</a></p>
  </form>
</div>
