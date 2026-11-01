<?php defined('BASEPATH') or exit('No direct script access allowed');

/**
 * Apply funnel booking theme defaults (magenta accent + enable note in custom CSS).
 */
class Migration_Add_booking_funnel_theme extends EA_Migration
{
    private const COMPANY_COLOR = '#A2235A';

    public function up(): void
    {
        $this->upsert_setting('company_color', self::COMPANY_COLOR);

        // Keep custom CSS available for extra overrides; funnel base lives in
        // assets/css/booking-funnel.scss (compiled to booking-funnel.css) and is
        // loaded on booking/message layouts.
        $note = trim(
            "/* Funnel booking theme is loaded from assets/css/booking-funnel.css.\n" .
                "   Add optional overrides below if needed. */\n",
        );

        $row = $this->db->get_where('settings', ['name' => 'custom_css'])->row_array();
        $existing = trim((string) ($row['value'] ?? ''));

        if ($existing === '' || $this->looks_like_legacy_gold_theme($existing)) {
            $this->upsert_setting('custom_css', $note);
        }

        $this->upsert_setting('custom_css_enabled', '1');
    }

    public function down(): void
    {
        // Leave branding in place; irreversible design default.
    }

    private function upsert_setting(string $name, string $value): void
    {
        $exists = $this->db->get_where('settings', ['name' => $name])->num_rows() > 0;

        if ($exists) {
            $this->db->update('settings', ['value' => $value], ['name' => $name]);
            return;
        }

        $this->db->insert('settings', [
            'name' => $name,
            'value' => $value,
        ]);
    }

    private function looks_like_legacy_gold_theme(string $css): bool
    {
        return str_contains($css, '--gold-primary: #C5A47E')
            || str_contains($css, "font-family: 'Inter'");
    }
}
