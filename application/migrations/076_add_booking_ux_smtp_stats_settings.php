<?php defined('BASEPATH') or exit('No direct script access allowed');

/**
 * Booking UX options, SMTP settings, custom CSS, and related defaults.
 */
class Migration_Add_booking_ux_smtp_stats_settings extends EA_Migration
{
    public function up(): void
    {
        $default_css = $this->default_custom_css();

        $settings = [
            ['name' => 'booking_skip_confirmation_step', 'value' => '0'],
            ['name' => 'booking_end_screen_title', 'value' => ''],
            ['name' => 'booking_end_screen_message', 'value' => ''],
            ['name' => 'booking_end_screen_show_details', 'value' => '1'],
            ['name' => 'custom_css_enabled', 'value' => '0'],
            ['name' => 'custom_css', 'value' => $default_css],
            ['name' => 'smtp_enabled', 'value' => '0'],
            ['name' => 'smtp_host', 'value' => ''],
            ['name' => 'smtp_port', 'value' => '587'],
            ['name' => 'smtp_crypto', 'value' => 'tls'],
            ['name' => 'smtp_user', 'value' => ''],
            ['name' => 'smtp_pass', 'value' => ''],
            ['name' => 'smtp_from_name', 'value' => ''],
            ['name' => 'smtp_from_address', 'value' => ''],
            ['name' => 'smtp_reply_to', 'value' => ''],
        ];

        foreach ($settings as $setting) {
            if (!$this->db->get_where('settings', ['name' => $setting['name']])->num_rows()) {
                $this->db->insert('settings', $setting);
            }
        }
    }

    public function down(): void
    {
        $names = [
            'booking_skip_confirmation_step',
            'booking_end_screen_title',
            'booking_end_screen_message',
            'booking_end_screen_show_details',
            'custom_css_enabled',
            'custom_css',
            'smtp_enabled',
            'smtp_host',
            'smtp_port',
            'smtp_crypto',
            'smtp_user',
            'smtp_pass',
            'smtp_from_name',
            'smtp_from_address',
            'smtp_reply_to',
        ];

        foreach ($names as $name) {
            $this->db->delete('settings', ['name' => $name]);
        }
    }

    private function default_custom_css(): string
    {
        return <<<'CSS'
/* =========================================================
   Booking page custom theme (custom.css)
   Custom fields stay visible — hide-rules intentionally omitted.
   ========================================================= */

:root {
  --gold-primary: #C5A47E;
  --gold-hover: #B08E66;
  --gold-soft: #FAF6F0;
  --bg-color: #FAF8F5;
  --card-bg: #FFFFFF;
  --text-dark: #2C2825;
  --text-muted: #7A736E;
  --border-color: #E2D9CF;
  --font-family: 'Inter', system-ui, -apple-system, sans-serif;
  --border-radius: 12px;
  --shadow-soft: 0 12px 32px rgba(0, 0, 0, 0.04);
}

html, body {
  font-family: var(--font-family) !important;
  background-color: var(--bg-color) !important;
  color: var(--text-dark) !important;
}

div#header.overflow-hidden,
#wizard div#header {
  display: none !important;
  visibility: hidden !important;
  height: 0 !important;
  padding: 0 !important;
  margin: 0 !important;
}

.frame-container, #main-mobile, .booking-container,
#book-appointment-wizard {
  background: var(--card-bg) !important;
  border-radius: var(--border-radius) !important;
  box-shadow: var(--shadow-soft) !important;
  border: 1px solid var(--border-color) !important;
}

#book-appointment-wizard {
  padding: 32px !important;
  max-width: 800px !important;
  margin: 20px auto !important;
}

.form-control, select, input[type="text"], input[type="email"], input[type="tel"], textarea, select.form-select {
  border-radius: 8px !important;
  border: 1px solid var(--border-color) !important;
  padding: 12px 16px !important;
  background-color: #FFFFFF !important;
  color: var(--text-dark) !important;
  font-size: 14px !important;
}

.flatpickr-months,
.flatpickr-month {
  background: var(--gold-primary) !important;
  background-color: var(--gold-primary) !important;
  color: #FFFFFF !important;
  border: none !important;
  border-radius: 8px 8px 0 0 !important;
}

.flatpickr-current-month,
.flatpickr-prev-month,
.flatpickr-next-month {
  color: #FFFFFF !important;
  fill: #FFFFFF !important;
  background: transparent !important;
}

.flatpickr-current-month {
  display: flex !important;
  align-items: center !important;
  justify-content: center !important;
  padding: 0 !important;
  font-size: 15px !important;
}

.flatpickr-current-month .flatpickr-monthDropdown-months {
  background: rgba(255, 255, 255, 0.2) !important;
  border: none !important;
  border-radius: 6px !important;
  padding: 4px 8px !important;
  margin-right: 6px !important;
  color: #FFFFFF !important;
  font-weight: 600 !important;
}

.flatpickr-current-month input.cur-year {
  background: transparent !important;
  border: none !important;
  color: #FFFFFF !important;
  font-weight: 600 !important;
}

.flatpickr-monthDropdown-months option {
  background-color: #FFFFFF !important;
  color: var(--text-dark) !important;
}

.flatpickr-monthDropdown-months option:checked {
  background-color: var(--gold-primary) !important;
  color: #FFFFFF !important;
}

.flatpickr-weekdays,
span.flatpickr-weekday {
  background: var(--gold-hover) !important;
  color: #FFFFFF !important;
  font-weight: 500 !important;
}

.flatpickr-day.selected {
  background: var(--gold-primary) !important;
  border-color: var(--gold-primary) !important;
  color: #FFFFFF !important;
}

.flatpickr-day:hover {
  background: var(--gold-soft) !important;
  color: var(--gold-hover) !important;
}

#available-hours .available-hour,
.time-slot,
button.available-hour {
  background-color: #FFFFFF !important;
  border: 1px solid var(--border-color) !important;
  color: var(--text-dark) !important;
  border-radius: 8px !important;
  padding: 12px !important;
  margin-bottom: 8px !important;
  font-weight: 500 !important;
  text-align: center !important;
}

#available-hours .available-hour:hover,
.time-slot:hover {
  background-color: var(--gold-soft) !important;
  border-color: var(--gold-primary) !important;
  color: var(--gold-hover) !important;
}

#available-hours .selected-hour,
#available-hours .available-hour.selected,
.time-slot.selected {
  background-color: var(--gold-primary) !important;
  border-color: var(--gold-primary) !important;
  color: #FFFFFF !important;
  font-weight: 600 !important;
}

.btn-primary,
#button-next,
#button-appointment-submit,
.btn-next,
button[id*="next"] {
  background-color: var(--gold-primary) !important;
  border-color: var(--gold-primary) !important;
  color: #FFFFFF !important;
  border-radius: 8px !important;
  font-weight: 600 !important;
  padding: 12px 24px !important;
}

.btn-primary:hover,
#button-next:hover,
#button-appointment-submit:hover {
  background-color: var(--gold-hover) !important;
  border-color: var(--gold-hover) !important;
}

@media (max-width: 767px) {
  .flatpickr-calendar.inline {
    width: 100% !important;
    max-width: 100% !important;
    box-sizing: border-box !important;
    margin-bottom: 20px !important;
  }

  .flatpickr-days, .dayContainer {
    width: 100% !important;
    max-width: 100% !important;
    min-width: 100% !important;
  }

  #select-date, #available-hours, .row {
    display: flex !important;
    flex-direction: column !important;
    width: 100% !important;
    margin: 0 !important;
  }
}
CSS;
    }
}
