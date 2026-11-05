<div class="empty">
  <strong><?= e($emptyTitle ?? 'Nada por aqui ainda') ?></strong>
  <?php if (!empty($emptyText)): ?><p><?= e($emptyText) ?></p><?php endif; ?>
  <?php if (!empty($emptyAction)): ?><a class="btn" href="<?= e($emptyAction[1]) ?>"><?= e($emptyAction[0]) ?></a><?php endif; ?>
</div>
