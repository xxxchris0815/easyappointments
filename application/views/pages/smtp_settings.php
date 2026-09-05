<?php extend('layouts/backend_layout'); ?>

<?php section('content'); ?>

<div id="smtp-settings-page" class="container backend-page py-3">
    <div class="row">
        <div class="col-sm-3">
            <?php component('settings_nav'); ?>
        </div>
        <div id="smtp-settings" class="col-sm-9">
            <form>
                <fieldset>
                    <div class="d-flex justify-content-between align-items-center border-bottom mb-4 py-2">
                        <h4 class="mb-0 fw-light">
                            <?= lang('smtp_settings') ?>
                        </h4>

                        <div>
                            <a href="<?= site_url('integrations') ?>" class="btn btn-outline-primary me-2">
                                <i class="fas fa-chevron-left me-2"></i>
                                <?= lang('back') ?>
                            </a>

                            <?php if (can('edit', PRIV_SYSTEM_SETTINGS)): ?>
                                <button type="button" id="test-smtp-settings" class="btn btn-outline-primary me-2">
                                    <i class="fas fa-paper-plane me-2"></i>
                                    <?= lang('smtp_send_test_email') ?>
                                </button>
                                <button type="button" id="save-settings" class="btn btn-primary">
                                    <i class="fas fa-check-square me-2"></i>
                                    <?= lang('save') ?>
                                </button>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="form-text text-muted mb-4">
                        <?= lang('smtp_settings_info') ?>
                    </div>

                    <div class="mb-3">
                        <label class="form-label" for="smtp-test-recipient"><?= lang('smtp_test_recipient') ?></label>
                        <input type="email" class="form-control" id="smtp-test-recipient"
                               placeholder="<?= lang('smtp_test_recipient_hint') ?>">
                    </div>

                    <div class="mb-3">
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" id="smtp-enabled" data-field="smtp_enabled">
                            <label class="form-check-label" for="smtp-enabled">
                                <?= lang('smtp_enabled') ?>
                            </label>
                        </div>
                        <div class="form-text text-muted">
                            <small><?= lang('smtp_enabled_hint') ?></small>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label" for="smtp-host"><?= lang('smtp_host') ?></label>
                        <input type="text" class="form-control" id="smtp-host" data-field="smtp_host">
                        <div class="form-text text-muted"><small><?= lang('smtp_host_hint') ?></small></div>
                    </div>

                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label class="form-label" for="smtp-port"><?= lang('smtp_port') ?></label>
                            <input type="number" class="form-control" id="smtp-port" data-field="smtp_port">
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label" for="smtp-crypto"><?= lang('smtp_crypto') ?></label>
                            <select class="form-select" id="smtp-crypto" data-field="smtp_crypto">
                                <option value=""><?= lang('none') ?></option>
                                <option value="tls">TLS</option>
                                <option value="ssl">SSL</option>
                            </select>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label" for="smtp-user"><?= lang('smtp_user') ?></label>
                        <input type="text" class="form-control" id="smtp-user" data-field="smtp_user">
                    </div>

                    <div class="mb-3">
                        <label class="form-label" for="smtp-pass"><?= lang('smtp_pass') ?></label>
                        <input type="password" class="form-control" id="smtp-pass" data-field="smtp_pass">
                        <div class="form-text text-muted"><small><?= lang('smtp_pass_hint') ?></small></div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label" for="smtp-from-name"><?= lang('smtp_from_name') ?></label>
                        <input type="text" class="form-control" id="smtp-from-name" data-field="smtp_from_name">
                    </div>

                    <div class="mb-3">
                        <label class="form-label" for="smtp-from-address"><?= lang('smtp_from_address') ?></label>
                        <input type="email" class="form-control" id="smtp-from-address" data-field="smtp_from_address">
                    </div>

                    <div class="mb-3">
                        <label class="form-label" for="smtp-reply-to"><?= lang('smtp_reply_to') ?></label>
                        <input type="email" class="form-control" id="smtp-reply-to" data-field="smtp_reply_to">
                    </div>
                </fieldset>
            </form>
        </div>
    </div>
</div>

<?php end_section('content'); ?>

<?php section('scripts'); ?>
<script src="<?= asset_url('assets/js/http/smtp_settings_http_client.js') ?>"></script>
<script src="<?= asset_url('assets/js/pages/smtp_settings.js') ?>"></script>
<?php end_section('scripts'); ?>
