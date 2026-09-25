<?php extend('layouts/backend_layout'); ?>

<?php section('content'); ?>

<div id="google-calendar-settings-page" class="container backend-page py-3">
    <div class="row">
        <div class="col-sm-3">
            <?php component('settings_nav'); ?>
        </div>
        <div id="google-calendar-settings" class="col-sm-9">
            <form>
                <fieldset>
                    <div class="d-flex justify-content-between align-items-center border-bottom mb-4 py-2">
                        <h4 class="mb-0 fw-light">
                            <?= lang('google_calendar') ?>
                        </h4>

                        <div>
                            <a href="<?= site_url('google_calendar_sync_status') ?>" class="btn btn-outline-secondary me-2">
                                <i class="fas fa-clipboard-list me-2"></i>
                                <?= lang('google_calendar_sync_status_open') ?>
                            </a>
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
                                    <?= lang('google_calendar_info') ?>
                                </div>
                            </div>

                            <div class="mb-3">
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" id="google-sync-feature"
                                           data-field="google_sync_feature">
                                    <label class="form-check-label" for="google-sync-feature">
                                        <?= lang('active') ?>
                                    </label>
                                </div>
                            </div>

                            <div class="mb-3">
                                <label for="google-client-id" class="form-label">
                                    <?= lang('google_client_id') ?>
                                </label>
                                <input type="text" class="form-control" id="google-client-id"
                                       data-field="google_client_id"
                                       placeholder="<?= lang('google_client_id') ?>">
                                <div class="form-text text-muted">
                                    <?= lang('google_client_id_info') ?>
                                </div>
                            </div>

                            <div class="mb-3">
                                <label for="google-client-secret" class="form-label">
                                    <?= lang('google_client_secret') ?>
                                </label>
                                <input type="password" class="form-control" id="google-client-secret"
                                       data-field="google_client_secret"
                                       placeholder="<?= lang('google_client_secret') ?>">
                                <div class="form-text text-muted">
                                    <?= lang('google_client_secret_info') ?>
                                </div>
                            </div>

                            <div class="alert alert-info">
                                <i class="fas fa-info-circle me-2"></i>
                                <?= lang('google_calendar_setup_info') ?>
                                <br>
                                <strong><?= lang('google_oauth_redirect_uri') ?>:</strong>
                                <code><?= site_url('google/oauth_callback') ?></code>
                                <div class="small mt-1"><?= lang('google_oauth_redirect_uri_hint') ?></div>
                                <a href="https://console.developers.google.com" target="_blank">
                                    Google Cloud Console
                                </a>
                            </div>

                            <div class="mb-3">
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" id="google-meet-link-generation"
                                           data-field="google_meet_link_generation">
                                    <label class="form-check-label" for="google-meet-link-generation">
                                        <?= lang('google_meet_link_generation') ?>
                                    </label>
                                </div>
                                <div class="form-text text-muted">
                                    <?= lang('google_meet_link_generation_info') ?>
                                </div>
                            </div>

                            <div class="mb-3">
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" id="display-add-to-google-calendar"
                                           data-field="display_add_to_google_calendar">
                                    <label class="form-check-label" for="display-add-to-google-calendar">
                                        <?= lang('display_add_to_google_calendar') ?>
                                    </label>
                                </div>
                                <div class="form-text text-muted">
                                    <?= lang('display_add_to_google_calendar_info') ?>
                                </div>
                            </div>

                            <div class="mb-3">
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" id="google-calendar-anonymize"
                                           data-field="google_calendar_anonymize">
                                    <label class="form-check-label" for="google-calendar-anonymize">
                                        <?= lang('google_calendar_anonymize') ?>
                                    </label>
                                </div>
                                <div class="form-text text-muted">
                                    <?= lang('google_calendar_anonymize_hint') ?>
                                </div>
                            </div>

                            <h5 class="fw-light mt-4 mb-3"><?= lang('sync_period') ?></h5>

                            <div class="row g-3 mb-3">
                                <div class="col-md-6">
                                    <label class="form-label" for="google-sync-past-days">
                                        <?= lang('sync_past_days') ?>
                                    </label>
                                    <input type="number" class="form-control" id="google-sync-past-days"
                                           data-field="google_sync_past_days"
                                           min="1" max="400" step="1" value="30">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label" for="google-sync-future-days">
                                        <?= lang('sync_future_days') ?>
                                    </label>
                                    <input type="number" class="form-control" id="google-sync-future-days"
                                           data-field="google_sync_future_days"
                                           min="1" max="400" step="1" value="90">
                                </div>
                                <div class="col-12">
                                    <div class="form-text text-muted">
                                        <?= lang('google_sync_window_global_hint') ?>
                                    </div>
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

<script src="<?= asset_url('assets/js/http/google_calendar_settings_http_client.js') ?>"></script>
<script src="<?= asset_url('assets/js/pages/google_calendar_settings.js') ?>"></script>

<?php end_section('scripts'); ?>

