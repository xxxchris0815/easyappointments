<?php extend('layouts/backend_layout'); ?>

<?php section('content'); ?>

<div id="booking-blacklist-settings-page" class="container backend-page py-3">
    <div class="row">
        <div class="col-sm-3">
            <?php component('settings_nav'); ?>
        </div>
        <div class="col-sm-9">
            <div class="d-flex justify-content-between align-items-center border-bottom mb-4 py-2">
                <h4 class="mb-0 fw-light">
                    <?= lang('booking_blacklist') ?>
                </h4>
                <div>
                    <?php if (vars('can_edit_system_settings')): ?>
                        <button type="button" id="save-booking-blacklist" class="btn btn-primary me-2">
                            <i class="fas fa-check-square me-2"></i>
                            <?= lang('save') ?>
                        </button>
                    <?php endif; ?>
                    <a href="<?= site_url('booking_settings') ?>" class="btn btn-outline-primary">
                        <i class="fas fa-chevron-left me-2"></i>
                        <?= lang('back') ?>
                    </a>
                </div>
            </div>

            <p class="form-text text-muted mb-4">
                <?= lang('booking_blacklist_info') ?>
            </p>

            <h5 class="fw-light mb-3"><?= lang('booking_blacklist_entries') ?></h5>

            <div id="booking-blacklist-entries" class="mb-3"></div>

            <?php if (vars('can_edit_system_settings')): ?>
                <button type="button" class="btn btn-outline-primary btn-sm mb-5" id="add-booking-blacklist-entry">
                    <i class="fas fa-plus-square me-2"></i>
                    <?= lang('add') ?>
                </button>
            <?php endif; ?>

            <h5 class="fw-light mb-3"><?= lang('booking_blacklist_recent_blocks') ?></h5>
            <p class="form-text text-muted mb-3">
                <?= lang('booking_blacklist_recent_blocks_info') ?>
            </p>

            <div class="d-flex justify-content-end mb-2">
                <button type="button" class="btn btn-outline-secondary" id="refresh-booking-blacklist-logs">
                    <i class="fas fa-sync-alt me-2"></i>
                    <?= lang('reload') ?>
                </button>
            </div>

            <div class="table-responsive mb-2">
                <table class="table table-striped align-middle" id="booking-blacklist-logs-table">
                    <thead>
                    <tr>
                        <th><?= lang('date') ?></th>
                        <th><?= lang('booking_blacklist_entry_type') ?></th>
                        <th><?= lang('booking_blacklist_entry_value') ?></th>
                        <th><?= lang('customer') ?></th>
                        <th><?= lang('email') ?></th>
                        <th><?= lang('phone_number') ?></th>
                        <th>Mautic ID</th>
                    </tr>
                    </thead>
                    <tbody></tbody>
                </table>
            </div>
            <p class="text-muted small mb-3">
                <?= lang('results') ?>: <span id="booking-blacklist-logs-count">0</span>
            </p>
        </div>
    </div>
</div>

<?php end_section('content'); ?>

<?php section('scripts'); ?>

<script src="<?= asset_url('assets/js/http/booking_blacklist_settings_http_client.js') ?>"></script>
<script src="<?= asset_url('assets/js/pages/booking_blacklist_settings.js') ?>"></script>

<?php end_section('scripts'); ?>
