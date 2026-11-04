<div class="row justify-content-center">
  <div class="col-lg-8">
    <div class="card border-0 shadow-sm">
      <div class="card-header bg-white py-3 border-0">
        <h5 class="fw-bold mb-0"><?= $contact ? 'Edit Contact' : 'New Contact' ?></h5>
      </div>
      <div class="card-body p-4">
        <form method="POST" action="<?= $contact ? url('/app/contacts/' . $contact['id'] . '/edit') : url('/app/contacts/create') ?>">
          <?= csrf_field() ?>

          <div class="row g-3 mb-3">
            <div class="col-md-8">
              <label for="name" class="form-label small fw-semibold text-secondary">Company / Contact Name <span class="text-danger">*</span></label>
              <input type="text" class="form-control" id="name" name="name" value="<?= h($contact['name'] ?? '') ?>" placeholder="e.g. Acme Corporation" required>
            </div>
            <div class="col-md-4">
              <label for="type" class="form-label small fw-semibold text-secondary">Contact Classification <span class="text-danger">*</span></label>
              <select class="form-select" id="type" name="type" required>
                <option value="customer" <?= (isset($contact['type']) && $contact['type'] === 'customer') ? 'selected' : '' ?>>Customer (Debtor)</option>
                <option value="vendor" <?= (isset($contact['type']) && $contact['type'] === 'vendor') ? 'selected' : '' ?>>Vendor / Supplier (Creditor)</option>
                <option value="both" <?= (isset($contact['type']) && $contact['type'] === 'both') ? 'selected' : '' ?>>Both Customer & Vendor</option>
              </select>
            </div>
          </div>

          <div class="row g-3 mb-3">
            <div class="col-md-6">
              <label for="email" class="form-label small fw-semibold text-secondary">Billing Email</label>
              <input type="email" class="form-control" id="email" name="email" value="<?= h($contact['email'] ?? '') ?>" placeholder="billing@client.com">
            </div>
            <div class="col-md-6">
              <label for="phone" class="form-label small fw-semibold text-secondary">Phone Number</label>
              <input type="text" class="form-control" id="phone" name="phone" value="<?= h($contact['phone'] ?? '') ?>" placeholder="+603-1234-5678">
            </div>
          </div>

          <div class="mb-3">
            <label for="address" class="form-label small fw-semibold text-secondary">Street Address</label>
            <input type="text" class="form-control" id="address" name="address" value="<?= h($contact['address'] ?? '') ?>" placeholder="Suite 101, Menara Business">
          </div>

          <div class="row g-3 mb-3">
            <div class="col-md-4">
              <label for="city" class="form-label small fw-semibold text-secondary">City</label>
              <input type="text" class="form-control" id="city" name="city" value="<?= h($contact['city'] ?? '') ?>" placeholder="Kuala Lumpur">
            </div>
            <div class="col-md-4">
              <label for="state" class="form-label small fw-semibold text-secondary">State / Province</label>
              <input type="text" class="form-control" id="state" name="state" value="<?= h($contact['state'] ?? '') ?>" placeholder="Selangor">
            </div>
            <div class="col-md-4">
              <label for="country" class="form-label small fw-semibold text-secondary">Country</label>
              <input type="text" class="form-control" id="country" name="country" value="<?= h($contact['country'] ?? 'Malaysia') ?>" placeholder="Malaysia">
            </div>
          </div>

          <div class="row g-3 mb-3">
            <div class="col-md-4">
              <label for="tax_number" class="form-label small fw-semibold text-secondary">Tax Registration / VAT ID</label>
              <input type="text" class="form-control" id="tax_number" name="tax_number" value="<?= h($contact['tax_number'] ?? '') ?>" placeholder="SST-001234">
            </div>
            <div class="col-md-4">
              <label for="payment_terms" class="form-label small fw-semibold text-secondary">Standard Payment Terms (Days)</label>
              <select class="form-select" id="payment_terms" name="payment_terms">
                <option value="0" <?= (isset($contact['payment_terms']) && $contact['payment_terms'] == 0) ? 'selected' : '' ?>>Due upon receipt (COD)</option>
                <option value="7" <?= (isset($contact['payment_terms']) && $contact['payment_terms'] == 7) ? 'selected' : '' ?>>Net 7 days</option>
                <option value="15" <?= (isset($contact['payment_terms']) && $contact['payment_terms'] == 15) ? 'selected' : '' ?>>Net 15 days</option>
                <option value="30" <?= (!isset($contact['payment_terms']) || $contact['payment_terms'] == 30) ? 'selected' : '' ?>>Net 30 days</option>
                <option value="60" <?= (isset($contact['payment_terms']) && $contact['payment_terms'] == 60) ? 'selected' : '' ?>>Net 60 days</option>
              </select>
            </div>
            <div class="col-md-4">
              <label for="credit_limit" class="form-label small fw-semibold text-secondary">Credit Limit</label>
              <input type="number" step="0.01" class="form-control" id="credit_limit" name="credit_limit" value="<?= h($contact['credit_limit'] ?? '0.00') ?>">
            </div>
          </div>

          <div class="mb-4">
            <label for="notes" class="form-label small fw-semibold text-secondary">Internal Notes</label>
            <textarea class="form-control" id="notes" name="notes" rows="2" placeholder="Private internal comments..."><?= h($contact['notes'] ?? '') ?></textarea>
          </div>

          <div class="d-flex justify-content-between align-items-center">
            <a href="<?= url('/app/contacts') ?>" class="btn btn-outline-secondary">Cancel</a>
            <button type="submit" class="btn btn-primary px-4 fw-semibold">
              <?= $contact ? 'Update Contact' : 'Save Contact' ?>
            </button>
          </div>
        </form>
      </div>
    </div>
  </div>
</div>
