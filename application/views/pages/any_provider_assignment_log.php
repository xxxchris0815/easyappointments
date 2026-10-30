<?php extend('layouts/backend_layout'); ?>

<?php section('content'); ?>

<div id="any-provider-assignment-log-page" class="container backend-page py-3">
    <div class="row">
        <div class="col-sm-3">
            <?php component('settings_nav'); ?>
        </div>
        <div class="col-sm-9">
            <div class="d-flex justify-content-between align-items-center border-bottom mb-4 py-2">
                <h4 class="mb-0 fw-light">
                    <?= lang('any_provider_assignment_log') ?>
                </h4>
                <div>
                    <a href="<?= site_url('booking_settings') ?>" class="btn btn-outline-primary">
                        <i class="fas fa-chevron-left me-2"></i>
                        <?= lang('back') ?>
                    </a>
                </div>
            </div>

            <p class="form-text text-muted mb-3">
                <?= lang('any_provider_assignment_log_info') ?>
            </p>

            <div class="border rounded p-3 mb-4 bg-light">
                <div class="row g-3">
                    <div class="col-md-4">
                        <div class="text-muted small"><?= lang('any_provider_selection_mode') ?></div>
                        <div class="fw-semibold" id="any-provider-current-mode">
                            <?= html_escape(vars('any_provider_selection_mode')) ?>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="text-muted small"><?= lang('any_provider_rr_counter') ?></div>
                        <div class="fw-semibold" id="any-provider-current-rr">
                            <?= (int) vars('any_provider_rr_counter') ?>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="text-muted small"><?= lang('display_any_provider') ?></div>
                        <div class="fw-semibold">
                            <?= vars('display_any_provider') ? lang('yes') : lang('no') ?>
                        </div>
                    </div>
                </div>
            </div>

            <h5 class="fw-light mb-3"><?= lang('any_provider_assignment_simulate') ?></h5>

            <form id="any-provider-explain-form" class="row g-3 mb-3">
                <div class="col-md-4">
                    <label class="form-label" for="explain-service-id"><?= lang('service') ?></label>
                    <select class="form-select" id="explain-service-id" required>
                        <option value=""><?= lang('select') ?></option>
                        <?php foreach (vars('services') as $service): ?>
                            <option value="<?= (int) $service['id'] ?>">
                                <?= html_escape($service['name'] ?? ('#' . $service['id'])) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label" for="explain-date"><?= lang('date') ?></label>
                    <input type="date" class="form-control" id="explain-date" required
                           value="<?= html_escape(date('Y-m-d')) ?>">
                </div>
                <div class="col-md-2">
                    <label class="form-label" for="explain-hour"><?= lang('hour') ?></label>
                    <input type="time" class="form-control" id="explain-hour" step="60">
                </div>
                <div class="col-md-3">
                    <label class="form-label" for="explain-preferred-provider-id">
                        <?= lang('any_provider_preferred_provider') ?>
                    </label>
                    <select class="form-select" id="explain-preferred-provider-id">
                        <option value=""><?= lang('none') ?></option>
                        <?php foreach (vars('providers') as $provider): ?>
                            <?php
                            $provider_name = trim(($provider['first_name'] ?? '') . ' ' . ($provider['last_name'] ?? ''));
                            if ($provider_name === '') {
                                $provider_name = '#' . $provider['id'];
                            }
                            ?>
                            <option value="<?= (int) $provider['id'] ?>">
                                <?= html_escape($provider_name) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label" for="explain-exclude-appointment-id">
                        <?= lang('any_provider_exclude_appointment_id') ?>
                    </label>
                    <input type="number" min="1" class="form-control" id="explain-exclude-appointment-id"
                           placeholder="ID">
                </div>
                <div class="col-md-3 d-flex align-items-end">
                    <button type="submit" class="btn btn-primary w-100">
                        <i class="fas fa-calculator me-2"></i>
                        <?= lang('any_provider_run_calculation') ?>
                    </button>
                </div>
            </form>

            <div id="any-provider-selection-summary" class="alert alert-secondary d-none mb-3"></div>

            <div class="table-responsive mb-2">
                <table class="table table-striped align-middle" id="any-provider-providers-table">
                    <thead>
                    <tr>
                        <th><?= lang('provider') ?></th>
                        <th><?= lang('any_provider_offers_service') ?></th>
                        <th><?= lang('any_provider_available_hours') ?></th>
                        <th><?= lang('any_provider_weight') ?></th>
                        <th><?= lang('any_provider_candidate') ?></th>
                        <th><?= lang('reason') ?></th>
                    </tr>
                    </thead>
                    <tbody></tbody>
                </table>
            </div>
            <p class="text-muted small mb-4">
                <?= lang('results') ?>: <span id="any-provider-providers-count">0</span>
            </p>

            <div id="any-provider-last-result" class="card mb-5 d-none">
                <div class="card-header d-flex justify-content-between align-items-center py-2">
                    <h6 class="mb-0 fw-normal"><?= lang('any_provider_calculation_details') ?></h6>
                    <button type="button" class="btn btn-sm btn-outline-primary" id="any-provider-copy-result">
                        <i class="fas fa-copy me-1"></i>
                        <?= lang('copy') ?>
                    </button>
                </div>
                <div class="card-body p-0">
                    <pre class="mb-0 p-3 small" style="max-height: 420px; overflow: auto;"></pre>
                </div>
            </div>

            <h5 class="fw-light mb-3"><?= lang('any_provider_recent_assignments') ?></h5>
            <p class="form-text text-muted mb-3">
                <?= lang('any_provider_recent_assignments_info') ?>
            </p>

            <div class="d-flex justify-content-end mb-2">
                <button type="button" class="btn btn-outline-secondary" id="any-provider-refresh-logs">
                    <i class="fas fa-sync-alt me-2"></i>
                    <?= lang('reload') ?>
                </button>
            </div>

            <div class="table-responsive mb-2">
                <table class="table table-striped align-middle" id="any-provider-logs-table">
                    <thead>
                    <tr>
                        <th><?= lang('date') ?></th>
                        <th><?= lang('service') ?></th>
                        <th><?= lang('hour') ?></th>
                        <th><?= lang('any_provider_selection_mode') ?></th>
                        <th><?= lang('any_provider_candidates') ?></th>
                        <th><?= lang('provider') ?></th>
                        <th><?= lang('reason') ?></th>
                    </tr>
                    </thead>
                    <tbody></tbody>
                </table>
            </div>
            <p class="text-muted small mb-3">
                <?= lang('results') ?>: <span id="any-provider-logs-count">0</span>
            </p>
        </div>
    </div>
</div>

<?php end_section('content'); ?>

<?php section('scripts'); ?>

<script src="<?= asset_url('assets/js/http/any_provider_assignment_log_http_client.js') ?>"></script>
<script src="<?= asset_url('assets/js/pages/any_provider_assignment_log.js') ?>"></script>

<?php end_section('scripts'); ?>
