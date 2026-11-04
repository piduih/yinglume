<div class="d-flex justify-content-between align-items-center mb-4">
  <div>
    <div class="d-flex align-items-center gap-2 mb-1">
      <h4 class="fw-bold mb-0">Journal Entry: <?= h($entry['reference'] ?: ('JE-' . $entry['id'])) ?></h4>
      <?php if ($entry['is_void']): ?>
        <span class="badge bg-danger-subtle text-danger">Voided</span>
      <?php elseif ($entry['is_posted']): ?>
        <span class="badge bg-success-subtle text-success">Posted to Ledger</span>
      <?php else: ?>
        <span class="badge bg-secondary-subtle text-secondary">Draft</span>
      <?php endif; ?>
    </div>
    <p class="text-secondary small mb-0">Created on <?= date_fmt($entry['created_at']) ?> by <?= h($entry['author_name'] ?? 'System') ?></p>
  </div>
  <div class="d-flex gap-2">
    <a href="<?= url('/app/journal') ?>" class="btn btn-outline-secondary btn-sm">
      <i class="bi bi-arrow-left me-1"></i> Back to List
    </a>
    <button onclick="window.print()" class="btn btn-light border btn-sm">
      <i class="bi bi-printer me-1"></i> Print
    </button>
    <?php if (!$entry['is_posted'] && !$entry['is_void']): ?>
      <form method="POST" action="<?= url('/app/journal/' . $entry['id'] . '/post') ?>" class="d-inline">
        <?= csrf_field() ?>
        <button type="submit" class="btn btn-primary btn-sm fw-semibold">
          <i class="bi bi-check2-circle me-1"></i> Post Entry
        </button>
      </form>
    <?php endif; ?>
    <?php if ($entry['is_posted'] && !$entry['is_void']): ?>
      <button type="button" class="btn btn-outline-danger btn-sm" data-bs-toggle="modal" data-bs-target="#voidModal">
        <i class="bi bi-x-circle me-1"></i> Void Entry
      </button>
    <?php endif; ?>
  </div>
</div>

<div class="card border-0 shadow-sm mb-4">
  <div class="card-body p-4">
    <div class="row mb-4">
      <div class="col-md-4">
        <span class="text-secondary small d-block">Transaction Date</span>
        <span class="fw-bold"><?= date_fmt($entry['entry_date']) ?></span>
      </div>
      <div class="col-md-4">
        <span class="text-secondary small d-block">Source Origin</span>
        <span class="badge bg-light text-secondary border text-uppercase"><?= h($entry['source']) ?></span>
      </div>
      <div class="col-md-4">
        <span class="text-secondary small d-block">Narration</span>
        <span class="fw-semibold text-dark"><?= h($entry['description']) ?></span>
      </div>
    </div>

    <?php if ($entry['is_void']): ?>
      <div class="alert alert-danger py-2 mb-4 small">
        <strong>Void Reason:</strong> <?= h($entry['void_reason'] ?: 'No reason stated') ?>
      </div>
    <?php endif; ?>

    <div class="table-responsive">
      <table class="table table-bordered align-middle mb-0">
        <thead class="table-light small">
          <tr>
            <th style="width: 140px;">Code</th>
            <th>Account &amp; Classification</th>
            <th>Line Description</th>
            <th class="text-end" style="width: 160px;">Debit (Dr)</th>
            <th class="text-end" style="width: 160px;">Credit (Cr)</th>
          </tr>
        </thead>
        <tbody class="small">
          <?php
            $totDeb = 0.0;
            $totCred = 0.0;
            foreach ($lines as $l):
              $totDeb += (float)$l['debit'];
              $totCred += (float)$l['credit'];
          ?>
            <tr>
              <td class="font-monospace fw-semibold"><?= h($l['code']) ?></td>
              <td>
                <span class="fw-semibold"><?= h($l['account_name']) ?></span>
                <span class="badge bg-light text-secondary border ms-1"><?= h($l['type_name']) ?></span>
              </td>
              <td class="text-secondary"><?= h($l['description'] ?: '—') ?></td>
              <td class="text-end font-monospace"><?= (float)$l['debit'] > 0 ? money((float)$l['debit']) : '—' ?></td>
              <td class="text-end font-monospace"><?= (float)$l['credit'] > 0 ? money((float)$l['credit']) : '—' ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
        <tfoot class="table-light fw-bold">
          <tr>
            <td colspan="3" class="text-end text-uppercase small">Totals:</td>
            <td class="text-end font-monospace text-primary"><?= money($totDeb) ?></td>
            <td class="text-end font-monospace text-primary"><?= money($totCred) ?></td>
          </tr>
        </tfoot>
      </table>
    </div>
  </div>
</div>

<!-- Void Confirmation Modal -->
<div class="modal fade" id="voidModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <form method="POST" action="<?= url('/app/journal/' . $entry['id'] . '/void') ?>">
        <?= csrf_field() ?>
        <div class="modal-header">
          <h5 class="modal-title text-danger fw-bold"><i class="bi bi-exclamation-triangle me-2"></i> Void Journal Entry</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <p class="small text-secondary">Voiding an entry neutralizes its effect on the general ledger and financial reports.</p>
          <div class="mb-3">
            <label class="form-label small fw-semibold">Reason for voiding:</label>
            <input type="text" name="void_reason" class="form-control" placeholder="e.g. Inadvertent duplicate entry" required>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-light border" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-danger">Confirm Void</button>
        </div>
      </form>
    </div>
  </div>
</div>
