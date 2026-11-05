<?php /** @var array $result */ if (($result['pages'] ?? 1) > 1): ?>
<nav class="pagination" aria-label="Paginação">
  <?php if ($result['page'] > 1): ?><a class="btn btn--ghost btn--sm" href="<?= e(query_with(['pagina' => $result['page'] - 1])) ?>">← Anterior</a><?php endif; ?>
  <span class="muted small">Página <?= (int) $result['page'] ?> de <?= (int) $result['pages'] ?> · <?= (int) $result['total'] ?> registro(s)</span>
  <?php if ($result['page'] < $result['pages']): ?><a class="btn btn--ghost btn--sm" href="<?= e(query_with(['pagina' => $result['page'] + 1])) ?>">Próxima →</a><?php endif; ?>
</nav>
<?php endif; ?>
