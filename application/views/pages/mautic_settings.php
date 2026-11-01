<?php extend('layouts/backend_layout'); ?>

<?php section('content'); ?>

<div id="mautic-settings-page" class="container backend-page py-3">
    <div class="row">
        <div class="col-sm-3">
            <?php component('settings_nav'); ?>
        </div>
        <div id="mautic-settings" class="col-sm-9">
            <form>
                <fieldset>
                    <div class="d-flex justify-content-between align-items-center border-bottom mb-4 py-2">
                        <h4 class="mb-0 fw-light">
                            <?= lang('mautic') ?>
                        </h4>

                        <div>
                            <a href="<?= site_url('integrations') ?>" class="btn btn-outline-primary me-2">
                                <i class="fas fa-chevron-left me-2"></i>
                                <?= lang('back') ?>
                            </a>

                            <?php if (can('edit', PRIV_SYSTEM_SETTINGS)): ?>
                                <button type="button" id="save-settings" class="btn btn-primary">
                                    <i class="fas fa-check-square me-2"></i>
                                    <?= lang('save') ?>
                                </button>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-12">
                            <div class="mb-3">
                                <div class="form-text text-muted mb-4">
                                    <?= lang('mautic_info') ?>
                                </div>
                            </div>

                            <div class="mb-3">
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" id="mautic-lead-lookup-enabled"
                                           data-field="mautic_lead_lookup_enabled">
                                    <label class="form-check-label" for="mautic-lead-lookup-enabled">
                                        <?= lang('active') ?>
                                    </label>
                                </div>
                            </div>

                            <div class="mb-3">
                                <label for="mautic-lookup-mode" class="form-label">
                                    <?= lang('mautic_lookup_mode') ?>
                                </label>
                                <select id="mautic-lookup-mode" class="form-select" data-field="mautic_lookup_mode">
                                    <option value="api"><?= lang('mautic_lookup_mode_api') ?></option>
                                    <option value="webhook"><?= lang('mautic_lookup_mode_webhook') ?></option>
                                </select>
                                <div class="form-text text-muted">
                                    <?= lang('mautic_lookup_mode_hint') ?>
                                </div>
                            </div>

                            <h5 class="fw-light mt-4 mb-3"><?= lang('mautic_api_credentials') ?></h5>

                            <div class="mb-3">
                                <label for="mautic-api-url" class="form-label">
                                    <?= lang('mautic_api_url') ?>
                                </label>
                                <input type="url" class="form-control" id="mautic-api-url"
                                       data-field="mautic_api_url"
                                       placeholder="https://mailings.example.com">
                                <div class="form-text text-muted">
                                    <?= lang('mautic_api_url_hint') ?>
                                </div>
                            </div>

                            <div class="mb-3">
                                <label for="mautic-api-username" class="form-label">
                                    <?= lang('mautic_api_username') ?>
                                </label>
                                <input type="text" class="form-control" id="mautic-api-username"
                                       data-field="mautic_api_username"
                                       autocomplete="off">
                            </div>

                            <div class="mb-3">
                                <label for="mautic-api-password" class="form-label">
                                    <?= lang('mautic_api_password') ?>
                                </label>
                                <input type="password" class="form-control" id="mautic-api-password"
                                       data-field="mautic_api_password"
                                       autocomplete="new-password">
                            </div>

                            <h5 class="fw-light mt-4 mb-3"><?= lang('mautic_webhook_fallback') ?></h5>

                            <div class="mb-3">
                                <label for="mautic-lead-lookup-url" class="form-label">
                                    <?= lang('mautic_lead_lookup_url') ?>
                                </label>
                                <input type="url" class="form-control" id="mautic-lead-lookup-url"
                                       data-field="mautic_lead_lookup_url"
                                       placeholder="https://automation.example.com/webhook/mautic-lead-lookup">
                                <div class="form-text text-muted">
                                    <?= lang('mautic_lead_lookup_url_hint') ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </fieldset>
            </form>
        </div>
    </div>
</div>

<?php end_section('content'); ?>

<?php section('scripts'); ?>

<script src="<?= asset_url('assets/js/http/mautic_settings_http_client.js') ?>"></script>
<script src="<?= asset_url('assets/js/pages/mautic_settings.js') ?>"></script>

<?php end_section('scripts'); ?>
