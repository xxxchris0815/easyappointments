<?php extend('layouts/backend_layout'); ?>

<?php section('content'); ?>

<div id="appointment-statistics-page" class="container backend-page py-3">
    <div class="d-flex justify-content-between align-items-center border-bottom mb-4 py-2">
        <h4 class="mb-0 fw-light">
            <?= lang('appointment_statistics') ?>
        </h4>
    </div>

    <p class="form-text text-muted mb-4">
        <?= lang('appointment_statistics_info') ?>
    </p>

    <form id="appointment-statistics-filters" class="row g-3 mb-4">
        <div class="col-md-3">
            <label class="form-label" for="filter-start-date"><?= lang('start_date') ?></label>
            <input type="date" class="form-control" id="filter-start-date" name="start_date"
                   value="<?= date('Y-m-01') ?>">
        </div>
        <div class="col-md-3">
            <label class="form-label" for="filter-end-date"><?= lang('end_date') ?></label>
            <input type="date" class="form-control" id="filter-end-date" name="end_date"
                   value="<?= date('Y-m-t') ?>">
        </div>
        <div class="col-md-3">
            <label class="form-label" for="filter-provider"><?= lang('provider') ?></label>
            <select class="form-select" id="filter-provider" name="provider_id">
                <option value="0"><?= lang('all') ?></option>
                <?php foreach (vars('providers') as $provider): ?>
                    <option value="<?= (int) $provider['id'] ?>">
                        <?= e($provider['first_name'] . ' ' . $provider['last_name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-3">
            <label class="form-label" for="filter-service"><?= lang('service') ?></label>
            <select class="form-select" id="filter-service" name="service_id">
                <option value="0"><?= lang('all') ?></option>
                <?php foreach (vars('services') as $service): ?>
                    <option value="<?= (int) $service['id'] ?>">
                        <?= e($service['name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-3">
            <label class="form-label" for="filter-creator"><?= lang('created_by') ?></label>
            <select class="form-select" id="filter-creator" name="created_by">
                <option value="0"><?= lang('all') ?></option>
                <?php foreach (vars('creators') as $creator): ?>
                    <option value="<?= (int) $creator['id'] ?>">
                        <?= e($creator['first_name'] . ' ' . $creator['last_name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-3">
            <label class="form-label" for="filter-status"><?= lang('status') ?></label>
            <input type="text" class="form-control" id="filter-status" name="status"
                   placeholder="<?= lang('status') ?>">
        </div>
        <div class="col-md-3">
            <label class="form-label" for="filter-sort"><?= lang('sort') ?></label>
            <select class="form-select" id="filter-sort" name="sort">
                <option value="start_datetime"><?= lang('start') ?></option>
                <option value="create_datetime"><?= lang('created') ?></option>
                <option value="id_users_provider"><?= lang('provider') ?></option>
                <option value="id_users_created_by"><?= lang('created_by') ?></option>
                <option value="status"><?= lang('status') ?></option>
            </select>
        </div>
        <div class="col-md-3">
            <label class="form-label" for="filter-direction"><?= lang('direction') ?></label>
            <select class="form-select" id="filter-direction" name="direction">
                <option value="asc"><?= lang('ascending') ?></option>
                <option value="desc"><?= lang('descending') ?></option>
            </select>
        </div>
        <div class="col-md-3 d-flex align-items-end">
            <div class="form-check form-switch mb-2">
                <input class="form-check-input" type="checkbox" id="filter-include-cancelled" name="include_cancelled">
                <label class="form-check-label" for="filter-include-cancelled">
                    <?= lang('include_cancelled') ?>
                </label>
            </div>
        </div>
        <div class="col-md-3 d-flex align-items-end">
            <button type="submit" class="btn btn-primary w-100" id="run-statistics-search">
                <i class="fas fa-search me-2"></i>
                <?= lang('search') ?>
            </button>
        </div>
    </form>

    <div class="mb-2 text-muted">
        <span id="statistics-count">0</span> <?= lang('appointments') ?>
    </div>

    <div class="table-responsive">
        <table class="table table-striped align-middle" id="appointment-statistics-table">
            <thead>
            <tr>
                <th><?= lang('start') ?></th>
                <th><?= lang('end') ?></th>
                <th><?= lang('service') ?></th>
                <th><?= lang('provider') ?></th>
                <th><?= lang('customer') ?></th>
                <th><?= lang('created_by') ?></th>
                <th><?= lang('status') ?></th>
            </tr>
            </thead>
            <tbody></tbody>
        </table>
    </div>
</div>

<?php end_section('content'); ?>

<?php section('scripts'); ?>
<script src="<?= asset_url('assets/js/pages/appointment_statistics.js') ?>"></script>
<?php end_section('scripts'); ?>
