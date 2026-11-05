<?php foreach (($flash ?? []) as $f): ?>
  <div class="alert alert--<?= e($f['type']) ?>" role="<?= $f['type'] === 'error' ? 'alert' : 'status' ?>"><?= e($f['message']) ?></div>
<?php endforeach; ?>
