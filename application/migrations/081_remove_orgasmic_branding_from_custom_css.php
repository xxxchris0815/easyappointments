<?php defined('BASEPATH') or exit('No direct script access allowed');

/**
 * Strip ORGASMIC / Alexandra branding from stored custom CSS comments.
 */
class Migration_Remove_orgasmic_branding_from_custom_css extends EA_Migration
{
    public function up(): void
    {
        $row = $this->db->get_where('settings', ['name' => 'custom_css'])->row_array();

        if (empty($row)) {
            return;
        }

        $css = (string) ($row['value'] ?? '');

        if ($css === '') {
            return;
        }

        $cleaned = str_ireplace(
            [
                'ORGASMIC / Alexandra booking theme',
                'ORGASMIC / Alexandra',
                'ORGASMIC',
                'Alexandra',
            ],
            [
                'Booking page custom theme',
                'Booking page',
                '',
                '',
            ],
            $css,
        );

        // Tidy double spaces left by removals inside the header comment.
        $cleaned = preg_replace('/[ \t]{2,}/', ' ', $cleaned) ?? $cleaned;

        if ($cleaned !== $css) {
            $this->db->update('settings', ['value' => $cleaned], ['name' => 'custom_css']);
        }
    }

    public function down(): void
    {
        // Irreversible branding cleanup.
    }
}
