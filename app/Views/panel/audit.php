<div class="page-head"><div><h1>Auditoria da loja</h1><p>Últimas 200 ações sensíveis. Senhas, tokens e dados de cartão nunca são registrados.</p></div></div>
<div class="card">
  <?php if (!$rows): ?><p class="muted">Nenhum evento.</p><?php else: ?>
  <div class="table-wrap"><table class="stack"><thead><tr><th>Data</th><th>Quem</th><th>Ação</th><th>Registro</th><th>Detalhes</th><th>IP</th></tr></thead><tbody>
    <?php foreach ($rows as $a): ?><tr>
      <td data-label="Data" class="nowrap"><?= e(dt($a['created_at'])) ?></td>
      <td data-label="Quem"><?= e($a['user_name'] ?? ($a['area'] === 'store' ? 'Cliente/loja' : 'Sistema')) ?></td>
      <td data-label="Ação" class="mono"><?= e($a['action']) ?></td>
      <td data-label="Registro"><?= e(trim(($a['entity'] ?? '') . ' ' . ($a['entity_id'] ?? ''))) ?: '—' ?></td>
      <td data-label="Detalhes" class="small mono"><?= e($a['meta'] ?? '') ?></td>
      <td data-label="IP" class="small"><?= e($a['ip'] ?? '') ?></td>
    </tr><?php endforeach; ?>
  </tbody></table></div>
  <?php endif; ?>
</div>
