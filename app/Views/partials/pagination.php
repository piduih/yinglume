<?php
if (!isset($pagination) || $pagination['total_pages'] <= 1) {
    return;
}

$page = (int)$pagination['page'];
$totalPages = (int)$pagination['total_pages'];
$urlPattern = $pagination['url_pattern'] ?? '?page=%d';
?>

<nav aria-label="Table pagination" class="mt-4">
  <ul class="pagination pagination-sm justify-content-center mb-0">
    <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
      <a class="page-link" href="<?= sprintf($urlPattern, $page - 1) ?>" tabindex="-1">Previous</a>
    </li>

    <?php for ($i = 1; $i <= $totalPages; $i++): ?>
      <?php if ($i == 1 || $i == $totalPages || ($i >= $page - 2 && $i <= $page + 2)): ?>
        <li class="page-item <?= $i === $page ? 'active' : '' ?>">
          <a class="page-link" href="<?= sprintf($urlPattern, $i) ?>"><?= $i ?></a>
        </li>
      <?php elseif ($i == 2 || $i == $totalPages - 1): ?>
        <li class="page-item disabled"><span class="page-link">...</span></li>
      <?php endif; ?>
    <?php endfor; ?>

    <li class="page-item <?= $page >= $totalPages ? 'disabled' : '' ?>">
      <a class="page-link" href="<?= sprintf($urlPattern, $page + 1) ?>">Next</a>
    </li>
  </ul>
</nav>

