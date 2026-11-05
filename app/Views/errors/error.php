<div class="card">
  <h1><?= match ((int) $status) { 403 => 'Acesso negado', 404 => 'Não encontrado', 419 => 'Sessão expirada', 429 => 'Muitas tentativas', 503 => 'Indisponível', 422 => 'Não foi possível concluir', default => 'Algo deu errado' } ?></h1>
  <p><?= e($message) ?></p>
  <p class="muted small">Código <?= (int) $status ?></p>
  <div class="actions">
    <a class="btn" href="<?= e(url('/')) ?>">Ir para o início</a>
  </div>
</div>
