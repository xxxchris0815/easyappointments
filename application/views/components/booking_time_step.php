<?php
/**
 * Local variables.
 *
 * @var array $grouped_timezones
 */
?>

<div id="wizard-frame-2" class="wizard-frame p-3 p-md-4" style="display:none;">
    <div class="frame-container py-3" style="min-height: 500px;">

        <h2 class="frame-title fw-light text-center mb-4 text-muted"><?= lang('appointment_date_and_time') ?></h2>

        <div class="row frame-content">
            <div class="col-12 col-lg-6">
                <div id="select-date" class="mx-auto my-4"></div>

            </div>

            <div class="col-12 col-lg-6 d-flex">
                <div id="select-time" class="mx-auto py-3 w-100 d-flex flex-column flex-grow-1">
                    <div id="select-timezone-group" class="mb-3 booking-timezone-group">
                        <label for="select-timezone" class="form-label">
                            <?= lang('timezone') ?>
                        </label>
                        <?php component('timezone_dropdown', [
                            'attributes' => 'id="select-timezone" class="form-select mb-3" value="UTC"',
                            'grouped_timezones' => $grouped_timezones,
                        ]); ?>
                    </div>

                    <div id="available-hours"
                         class="available-hours-panel overflow-auto my-3 pe-2 flex-grow-1"></div>
                    <div id="available-hours-more" class="text-center mb-2" hidden>
                        <button type="button" id="load-more-hours" class="btn btn-outline-secondary btn-sm">
                            <?= lang('load_more_times') ?>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="command-buttons text-center my-3 mx-auto d-md-flex justify-content-md-between">
        <button type="button" id="button-back-2" class="btn button-back btn-outline-secondary" style="min-width: 120px; margin-right: 10px;"
                data-step_index="2">
            <i class="fas fa-chevron-left me-2"></i>
            <?= lang('back') ?>
        </button>
        <button type="button" id="button-next-2" class="btn button-next btn-dark" style="min-width: 120px; margin-right: 10px;"
                data-step_index="2">
            <?= lang('next') ?>
            <i class="fas fa-chevron-right ms-2"></i>
        </button>
    </div>
</div>
