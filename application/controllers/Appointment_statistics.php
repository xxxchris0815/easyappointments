<?php defined('BASEPATH') or exit('No direct script access allowed');

/**
 * Appointment statistics / listing report.
 */
class Appointment_statistics extends EA_Controller
{
    public function __construct()
    {
        parent::__construct();

        $this->load->model('appointments_model');
        $this->load->model('providers_model');
        $this->load->model('services_model');
        $this->load->model('customers_model');
        $this->load->model('users_model');

        if (cannot('view', PRIV_APPOINTMENTS)) {
            show_error('Forbidden', 403);
        }
    }

    public function index(): void
    {
        $user_id = session('user_id');

        $providers = $this->providers_model->get();
        $services = $this->services_model->get();
        $creators = $this->db
            ->select('u.id, u.first_name, u.last_name')
            ->from('users u')
            ->join('roles r', 'r.id = u.id_roles', 'inner')
            ->where_in('r.slug', ['admin', 'provider', 'secretary'])
            ->order_by('u.first_name')
            ->get()
            ->result_array();

        $status_options = json_decode((string) setting('appointment_status_options', '[]'), true);

        if (!is_array($status_options)) {
            $status_options = [];
        }

        $status_options = array_values(
            array_filter(array_map(static fn($status) => trim((string) $status), $status_options)),
        );

        script_vars([
            'user_id' => $user_id,
            'role_slug' => session('role_slug'),
            'date_format' => setting('date_format'),
            'time_format' => setting('time_format'),
            'appointment_status_options' => $status_options,
        ]);

        html_vars([
            'page_title' => lang('appointment_statistics'),
            'active_menu' => PRIV_APPOINTMENTS,
            'user_display_name' => $this->accounts->get_user_display_name($user_id),
            'providers' => $providers,
            'services' => $services,
            'creators' => $creators,
            'appointment_status_options' => $status_options,
        ]);

        $this->load->view('pages/appointment_statistics');
    }

    /**
     * JSON search endpoint.
     */
    public function search(): void
    {
        try {
            if (cannot('view', PRIV_APPOINTMENTS)) {
                abort(403, 'Forbidden');
            }

            $start = (string) request('start_date', date('Y-m-01'));
            $end = (string) request('end_date', date('Y-m-t'));
            $provider_id = (int) request('provider_id', 0);
            $service_id = (int) request('service_id', 0);
            $created_by = (int) request('created_by', 0);
            $status = trim((string) request('status', ''));
            $include_cancelled = filter_var(request('include_cancelled', '0'), FILTER_VALIDATE_BOOLEAN);
            $sort = (string) request('sort', 'start_datetime');
            $direction = strtolower((string) request('direction', 'asc')) === 'desc' ? 'desc' : 'asc';

            $allowed_sort = [
                'start_datetime',
                'end_datetime',
                'status',
                'id_users_provider',
                'id_users_created_by',
                'id_services',
                'create_datetime',
            ];

            if (!in_array($sort, $allowed_sort, true)) {
                $sort = 'start_datetime';
            }

            $this->db
                ->select(
                    'a.*, ' .
                        'p.first_name AS provider_first_name, p.last_name AS provider_last_name, ' .
                        'c.first_name AS customer_first_name, c.last_name AS customer_last_name, c.email AS customer_email, ' .
                        's.name AS service_name, ' .
                        'cr.first_name AS creator_first_name, cr.last_name AS creator_last_name',
                )
                ->from('appointments a')
                ->join('users p', 'p.id = a.id_users_provider', 'left')
                ->join('users c', 'c.id = a.id_users_customer', 'left')
                ->join('services s', 's.id = a.id_services', 'left')
                ->join('users cr', 'cr.id = a.id_users_created_by', 'left')
                ->where('a.is_unavailability', false)
                ->where('a.start_datetime >=', $start . ' 00:00:00')
                ->where('a.start_datetime <=', $end . ' 23:59:59');

            if ($provider_id > 0) {
                $this->db->where('a.id_users_provider', $provider_id);
            }

            if ($service_id > 0) {
                $this->db->where('a.id_services', $service_id);
            }

            if ($created_by > 0) {
                $this->db->where('a.id_users_created_by', $created_by);
            }

            if ($status !== '') {
                $this->db->where('a.status', $status);
            } elseif (!$include_cancelled) {
                $this->db->where(
                    "LOWER(COALESCE(a.status, '')) NOT IN ('cancelled', 'canceled')",
                    null,
                    false,
                );
            }

            // Secretary restricted view: only own bookings when enabled.
            if (
                session('role_slug') === DB_SLUG_SECRETARY &&
                filter_var(setting('secretary_restricted_view'), FILTER_VALIDATE_BOOLEAN)
            ) {
                $this->db->where('a.id_users_created_by', (int) session('user_id'));
            }

            $rows = $this->db->order_by('a.' . $sort, $direction)->get()->result_array();

            $result = [];

            foreach ($rows as $row) {
                $result[] = [
                    'id' => (int) $row['id'],
                    'start_datetime' => $row['start_datetime'],
                    'end_datetime' => $row['end_datetime'],
                    'status' => $row['status'],
                    'service_name' => $row['service_name'],
                    'provider_name' => trim(($row['provider_first_name'] ?? '') . ' ' . ($row['provider_last_name'] ?? '')),
                    'customer_name' => trim(($row['customer_first_name'] ?? '') . ' ' . ($row['customer_last_name'] ?? '')),
                    'customer_email' => $row['customer_email'] ?? '',
                    'creator_name' => trim(($row['creator_first_name'] ?? '') . ' ' . ($row['creator_last_name'] ?? '')),
                    'created_by_id' => $row['id_users_created_by'] !== null ? (int) $row['id_users_created_by'] : null,
                    'create_datetime' => $row['create_datetime'],
                    'location' => $row['location'],
                    'notes' => $row['notes'],
                ];
            }

            json_response([
                'success' => true,
                'count' => count($result),
                'appointments' => $result,
            ]);
        } catch (Throwable $e) {
            json_exception($e);
        }
    }
}
