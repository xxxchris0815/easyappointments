<?php defined('BASEPATH') or exit('No direct script access allowed');

/**
 * Serves custom CSS as a stylesheet so it applies reliably in browsers.
 */
class Custom_css extends EA_Controller
{
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Output enabled custom CSS.
     */
    public function index(): void
    {
        if (!filter_var(setting('custom_css_enabled', '0'), FILTER_VALIDATE_BOOLEAN)) {
            $this->output
                ->set_status_header(204)
                ->set_content_type('text/css', 'utf-8')
                ->set_output('/* custom css disabled */');
            return;
        }

        $css = (string) setting('custom_css', '');

        $this->output
            ->set_header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0')
            ->set_header('Pragma: no-cache')
            ->set_content_type('text/css', 'utf-8')
            ->set_output($css);
    }
}
