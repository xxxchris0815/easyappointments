<?php extend('layouts/backend_layout'); ?>

<?php section('content'); ?>

<div id="google-calendar-sync-status-page" class="container backend-page py-3">
    <div class="row">
        <div class="col-sm-3">
            <?php component('settings_nav'); ?>
        </div>
        <div class="col-sm-9">
            <div class="d-flex justify-content-between align-items-center border-bottom mb-4 py-2">
                <h4 class="mb-0 fw-light">
                    <?= lang('google_calendar_sync_status') ?>
                </h4>
                <div>
                    <a href="<?= site_url('google_calendar_settings') ?>" class="btn btn-outline-primary me-2">
                        <i class="fas fa-cogs me-2"></i>
                        <?= lang('configure') ?>
                    </a>
                    <a href="<?= site_url('integrations') ?>" class="btn btn-outline-primary">
                        <i class="fas fa-chevron-left me-2"></i>
                        <?= lang('back') ?>
                    </a>
                </div>
            </div>

            <p class="form-text text-muted mb-3">
                <?= lang('google_calendar_sync_status_info') ?>
            </p>
            <p class="form-text text-muted mb-3">
                <?= lang('google_reset_unavailabilities_info') ?>
            </p>

            <?php if (!filter_var(vars('google_sync_feature'), FILTER_VALIDATE_BOOLEAN)): ?>
                <div class="alert alert-warning">
                    <?= lang('google_calendar_sync_feature_disabled_hint') ?>
                </div>
            <?php endif; ?>

            <h5 class="fw-light mb-3"><?= lang('providers') ?></h5>

            <form id="google-sync-provider-filters" class="row g-3 mb-3">
                <div class="col-md-4">
                    <label class="form-label" for="provider-filter-search"><?= lang('search') ?></label>
                    <input type="text" class="form-control" id="provider-filter-search"
                           placeholder="<?= lang('provider') ?> / <?= lang('email') ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label" for="provider-filter-sync"><?= lang('google_sync') ?></label>
                    <select class="form-select" id="provider-filter-sync">
                        <option value="all"><?= lang('all') ?></option>
                        <option value="on"><?= lang('active') ?></option>
                        <option value="off"><?= lang('inactive') ?></option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label" for="provider-filter-connected"><?= lang('google_connected') ?></label>
                    <select class="form-select" id="provider-filter-connected">
                        <option value="all"><?= lang('all') ?></option>
                        <option value="yes"><?= lang('yes') ?></option>
                        <option value="no"><?= lang('no') ?></option>
                    </select>
                </div>
                <div class="col-md-2 d-flex align-items-end">
                    <button type="submit" class="btn btn-primary w-100">
                        <i class="fas fa-filter me-2"></i>
                        <?= lang('filter') ?>
                    </button>
                </div>
            </form>

            <div class="table-responsive mb-2">
                <table class="table table-striped align-middle" id="google-sync-providers-table">
                    <thead>
                    <tr>
                        <th><?= lang('provider') ?></th>
                        <th><?= lang('email') ?></th>
                        <th><?= lang('google_sync') ?></th>
                        <th><?= lang('google_connected') ?></th>
                        <th><?= lang('calendar') ?></th>
                        <th><?= lang('sync_period') ?></th>
                        <th><?= lang('actions') ?></th>
                    </tr>
                    </thead>
                    <tbody></tbody>
                </table>
            </div>
            <p class="text-muted small mb-5">
                <?= lang('results') ?>: <span id="google-sync-providers-count">0</span>
            </p>

            <h5 class="fw-light mb-3"><?= lang('google_sync_logs') ?></h5>

            <form id="google-sync-log-filters" class="row g-3 mb-3">
                <div class="col-md-3">
                    <label class="form-label" for="log-filter-date"><?= lang('date') ?></label>
                    <select class="form-select" id="log-filter-date"></select>
                </div>
                <div class="col-md-3">
                    <label class="form-label" for="log-filter-level"><?= lang('level') ?></label>
                    <select class="form-select" id="log-filter-level">
                        <option value="all"><?= lang('all') ?></option>
                        <option value="error"><?= lang('error') ?></option>
                        <option value="info">INFO</option>
                        <option value="debug">DEBUG</option>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label" for="log-filter-search"><?= lang('search') ?></label>
                    <input type="text" class="form-control" id="log-filter-search"
                           placeholder="provider ID / appointment / Google">
                </div>
                <div class="col-md-2 d-flex align-items-end">
                    <button type="submit" class="btn btn-primary w-100">
                        <i class="fas fa-search me-2"></i>
                        <?= lang('filter') ?>
                    </button>
                </div>
            </form>

            <div class="table-responsive">
                <table class="table table-sm table-hover" id="google-sync-logs-table">
                    <thead>
                    <tr>
                        <th style="width: 160px;"><?= lang('date') ?></th>
                        <th style="width: 90px;"><?= lang('level') ?></th>
                        <th><?= lang('message') ?></th>
                    </tr>
                    </thead>
                    <tbody></tbody>
                </table>
            </div>
            <p class="text-muted small mb-0">
                <?= lang('results') ?>: <span id="google-sync-logs-count">0</span>
            </p>
        </div>
    </div>
</div>

<?php end_section('content'); ?>

<?php section('scripts'); ?>
<script src="<?= asset_url('assets/js/http/google_calendar_sync_status_http_client.js') ?>"></script>
<script src="<?= asset_url('assets/js/pages/google_calendar_sync_status.js') ?>"></script>
<?php end_section('scripts'); ?>
