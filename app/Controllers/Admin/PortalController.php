<?php

namespace App\Controllers\Admin;

use CodeIgniter\Database\Exceptions\DatabaseException;
use CodeIgniter\Controller;

/** Online-only session portal for administrators. */
class PortalController extends Controller
{
    private const MASTERS = [
        'machines' => ['title' => 'Machines', 'fields' => ['machine_code' => 'Machine code', 'machine_name' => 'Machine name'], 'view' => 'admin/machines'],
        'sectors' => ['title' => 'Sectors', 'fields' => ['name' => 'Sector name'], 'view' => 'admin/sectors'],
        'activities' => ['title' => 'Activities', 'fields' => ['sector_id' => 'Sector', 'name' => 'Activity name'], 'view' => 'admin/activities'],
        'sales-orders' => ['title' => 'Sales Orders', 'table' => 'sales_orders', 'fields' => ['so_number' => 'S/O number', 'customer_name' => 'Customer', 'batch_qty' => 'Batch quantity'], 'view' => 'admin/sales_orders'],
        'problems' => ['title' => 'Problems', 'fields' => ['code' => 'Problem code', 'name' => 'Problem name'], 'view' => 'admin/problems'],
    ];

    public function login()
    {
        $this->preparePage();
        if (session('admin_workforce_id')) return redirect()->to('/admin');
        if ($this->request->is('post')) {
            $username = mb_strtolower(trim((string) $this->request->getPost('username')));
            $row = db_connect()->table('workforce')->where('username', $username)->where('role', 'admin')->where('status', 'active')->get()->getRowArray();
            if ($row && password_verify((string) $this->request->getPost('password'), $row['password_hash'] ?? '')) {
                session()->regenerate(true);
                session()->set(['admin_workforce_id' => (int) $row['id'], 'admin_name' => $row['name']]);
                return redirect()->to('/admin');
            }
            return view('admin/login', ['error' => 'Email/username or password is incorrect.']);
        }
        return view('admin/login', ['error' => null]);
    }

    public function logout()
    {
        session()->remove(['admin_workforce_id', 'admin_name']);
        session()->regenerate(true);
        return redirect()->to('/admin/login');
    }

    public function home()
    {
        $this->preparePage();
        $db = db_connect();
        $recent = $db->table('processes p')->select('p.id,p.status,p.started_at,p.ended_at,so.so_number,m.machine_name,a.name AS activity_name,w.name AS supervisor_name')
            ->join('sales_orders so','so.id=p.sales_order_id','left')->join('machines m','m.id=p.machine_id')->join('activities a','a.id=p.activity_id')->join('workforce w','w.id=p.current_supervisor_workforce_id')
            ->where('p.deleted_at', null)->orderBy('p.started_at','DESC')->limit(8)->get()->getResultArray();
        $counts = ['processes' => $db->table('processes')->where('deleted_at', null)->countAllResults()];
        foreach (['machines', 'sectors', 'activities', 'sales_orders', 'problems'] as $table) $counts[$table] = $db->table($table)->where('status','active')->countAllResults();
        $counts['workforce'] = $db->table('workforce')->whereIn('role',['admin','supervisor','operator','qc'])->where('status','active')->countAllResults();

        // The process timestamps are stored as UTC. Use Malaysia-local month
        // boundaries so the chart agrees with the dates displayed elsewhere.
        $localZone = new \DateTimeZone('Asia/Kuala_Lumpur');
        $utcZone = new \DateTimeZone('UTC');
        $currentMonth = new \DateTimeImmutable('first day of this month 00:00:00', $localZone);
        $firstMonth = $currentMonth->modify('-11 months');
        $afterLastMonth = $currentMonth->modify('+1 month');
        $monthlyLabels = [];
        $monthlyCounts = [];
        $monthKeys = [];

        for ($offset = 0; $offset < 12; $offset++) {
            $month = $firstMonth->modify('+' . $offset . ' months');
            $monthKey = $month->format('Y-m');
            $monthKeys[] = $monthKey;
            $monthlyLabels[] = $month->format('F Y');
            $monthlyCounts[$monthKey] = 0;
        }

        $monthlyRows = $db->query(
            "SELECT DATE_FORMAT(DATE_ADD(started_at, INTERVAL 8 HOUR), '%Y-%m') AS month_key, COUNT(*) AS operation_count
             FROM processes
             WHERE deleted_at IS NULL AND started_at >= ? AND started_at < ?
             GROUP BY month_key
             ORDER BY month_key ASC",
            [
                $firstMonth->setTimezone($utcZone)->format('Y-m-d H:i:s'),
                $afterLastMonth->setTimezone($utcZone)->format('Y-m-d H:i:s'),
            ],
        )->getResultArray();

        foreach ($monthlyRows as $monthlyRow) {
            if (array_key_exists($monthlyRow['month_key'], $monthlyCounts)) {
                $monthlyCounts[$monthlyRow['month_key']] = (int) $monthlyRow['operation_count'];
            }
        }

        $operationStatusRows = $db->table('processes')
            ->select('status, COUNT(*) AS record_count')
            ->where('deleted_at', null)
            ->whereIn('status', ['active', 'completed'])
            ->groupBy('status')
            ->orderBy('status', 'ASC')
            ->get()
            ->getResultArray();
        $operationStatusCounts = [
            'Completed' => 0,
            'In Progress' => 0,
        ];

        foreach ($operationStatusRows as $statusRow) {
            $label = match (strtolower((string) $statusRow['status'])) {
                'completed' => 'Completed',
                'active' => 'In Progress',
                default => null,
            };

            if ($label !== null) {
                $operationStatusCounts[$label] = (int) $statusRow['record_count'];
            }
        }

        $latestMaster = [];
        $masterSets = [
            ['Machine', $db->table('machines')->select('machine_code,machine_name,status,created_at')->orderBy('id','DESC')->limit(5)->get()->getResultArray(), static fn (array $r): string => $r['machine_code'] . ' - ' . $r['machine_name']],
            ['Sector', $db->table('sectors')->select('name,status,created_at')->orderBy('id','DESC')->limit(5)->get()->getResultArray(), static fn (array $r): string => $r['name']],
            ['Activity', $db->table('activities a')->select('a.name,a.status,a.created_at,s.name AS sector_name')->join('sectors s','s.id=a.sector_id')->orderBy('a.id','DESC')->limit(5)->get()->getResultArray(), static fn (array $r): string => $r['sector_name'] . ' - ' . $r['name']],
            ['Sales Order', $db->table('sales_orders')->select('so_number,customer_name,status,created_at')->orderBy('id','DESC')->limit(5)->get()->getResultArray(), static fn (array $r): string => $r['so_number'] . ' - ' . $r['customer_name']],
            ['Problem', $db->table('problems')->select('code,name,status,created_at')->orderBy('id','DESC')->limit(5)->get()->getResultArray(), static fn (array $r): string => $r['code'] . ' - ' . $r['name']],
            ['Workforce', $db->table('workforce')->select('staff_id,name,role,status,created_at')->orderBy('id','DESC')->limit(5)->get()->getResultArray(), static fn (array $r): string => $r['staff_id'] . ' - ' . $r['name'] . ' (' . $r['role'] . ')'],
        ];
        foreach ($masterSets as [$category, $records, $labelFor]) foreach ($records as $record) $latestMaster[] = ['category' => $category, 'label' => $labelFor($record), 'status' => $record['status'], 'created_at' => $record['created_at']];
        usort($latestMaster, static fn (array $left, array $right): int => strcmp((string)($right['created_at'] ?? ''), (string)($left['created_at'] ?? '')));
        return view('admin/home', [
            'title' => 'Dashboard',
            'recent' => $recent,
            'latestMaster' => array_slice($latestMaster, 0, 15),
            'counts' => $counts,
            'monthlyOperations' => [
                'labels' => $monthlyLabels,
                'values' => array_values($monthlyCounts),
            ],
            'operationStatuses' => [
                'labels' => array_keys($operationStatusCounts),
                'values' => array_values($operationStatusCounts),
            ],
        ]);
    }

    public function master(string $kind)
    {
        $this->preparePage();
        $config = self::MASTERS[$kind] ?? null;
        if (! $config) return $this->response->setStatusCode(404)->setBody('Not found');
        $table = $config['table'] ?? $kind;
        $db = db_connect();
        $query = $db->table($table);
        if ($table === 'activities') $query->select('activities.*, sectors.name AS sector_name')->join('sectors', 'sectors.id = activities.sector_id');
        $rows = $query->orderBy($table . '.id', 'ASC')->get()->getResultArray();
        $editRecord = null;
        if (in_array($kind, ['activities', 'machines', 'sectors', 'sales-orders', 'problems'], true)) {
            $editId = (int) $this->request->getGet('edit');
            foreach ($rows as $row) {
                if ((int) $row['id'] === $editId && $editId > 0) {
                    $editRecord = $row;
                    break;
                }
            }
        }

        return view($config['view'], [
            'title' => $config['title'], 'kind' => $kind, 'fields' => $config['fields'],
            'rows' => $rows, 'editRecord' => $editRecord,
            'sectors' => $db->table('sectors')->orderBy('name')->get()->getResultArray(),
        ]);
    }

    public function saveMaster(string $kind)
    {
        helper('url');
        $config = self::MASTERS[$kind] ?? null;
        if (! $config) return $this->response->setStatusCode(404);
        $table = $config['table'] ?? $kind;
        $values = [];
        foreach (array_keys($config['fields']) as $field) $values[$field] = trim((string) $this->request->getPost($field));
        $id = (int) $this->request->getPost('id');
        if (in_array('', array_diff_key($values, ['status' => true]), true)) return $this->masterValidationRedirect($kind, 'Complete all required fields.');
        if (isset($values['batch_qty']) && filter_var($values['batch_qty'], FILTER_VALIDATE_INT) === false) return $this->masterValidationRedirect($kind, 'Batch quantity must be a whole number.');
        if (isset($values['sector_id']) && ! db_connect()->table('sectors')->where('id', (int) $values['sector_id'])->countAllResults()) return $this->masterValidationRedirect($kind, 'Select a valid sector.');
        if ($kind === 'sales-orders') {
            if (mb_strlen($values['so_number']) > 100) return $this->masterValidationRedirect($kind, 'S/O number must be 100 characters or fewer.');
            if (mb_strlen($values['customer_name']) > 150) return $this->masterValidationRedirect($kind, 'Customer name must be 150 characters or fewer.');
            if ((int) $values['batch_qty'] < 0) return $this->masterValidationRedirect($kind, 'Batch quantity cannot be negative.');
            if ($this->salesOrderNumberExists($values['so_number'], $id)) return $this->masterValidationRedirect($kind, 'That S/O number is already in use.');
        }
        if ($kind === 'problems') {
            if (mb_strlen($values['code']) > 50) return $this->masterValidationRedirect($kind, 'Problem code must be 50 characters or fewer.');
            if (mb_strlen($values['name']) > 150) return $this->masterValidationRedirect($kind, 'Problem name must be 150 characters or fewer.');
            if ($this->problemCodeExists($values['code'], $id)) return $this->masterValidationRedirect($kind, 'That problem code is already in use.');
        }
        if ($kind === 'machines') {
            if (mb_strlen($values['machine_code']) > 50) return $this->masterValidationRedirect($kind, 'Machine code must be 50 characters or fewer.');
            if (mb_strlen($values['machine_name']) > 150) return $this->masterValidationRedirect($kind, 'Machine name must be 150 characters or fewer.');
            if ($this->machineCodeExists($values['machine_code'], $id)) return $this->masterValidationRedirect($kind, 'That machine code is already in use. Enter a unique machine code.');
        }

        $db = db_connect();
        $columns = $db->getFieldNames($table);
        $now = gmdate('Y-m-d H:i:s');
        if (in_array('updated_at', $columns, true)) $values['updated_at'] = $now;
        $isCreate = $id === 0;
        try {
            if ($id) {
                $db->table($table)->where('id', $id)->update($values);
            } else {
                $values['status'] = $this->request->getPost('status') ?: 'active';
                if (in_array('created_at', $columns, true)) $values['created_at'] = $now;
                $db->table($table)->insert($values);
                $id = (int) $db->insertID();
            }
        } catch (DatabaseException $exception) {
            if ($kind === 'machines' && $this->machineCodeExists($values['machine_code'], $id)) {
                return $this->masterValidationRedirect($kind, 'That machine code is already in use. Enter a unique machine code.');
            }
            if ($kind === 'sales-orders' && $this->salesOrderNumberExists($values['so_number'], $id)) {
                return $this->masterValidationRedirect($kind, 'That S/O number is already in use.');
            }
            if ($kind === 'problems' && $this->problemCodeExists($values['code'], $id)) {
                return $this->masterValidationRedirect($kind, 'That problem code is already in use.');
            }

            throw $exception;
        }

        if ($this->isAjaxMasterMutation($kind)) {
            if ($kind === 'activities') {
                $record = $db->table('activities a')
                    ->select('a.id, a.sector_id, a.name, a.status, s.name AS sector_name')
                    ->join('sectors s', 's.id = a.sector_id')
                    ->where('a.id', $id)
                    ->get()
                    ->getRowArray();
            } elseif ($kind === 'machines') {
                $record = $db->table('machines')
                    ->select('id, machine_code, machine_name, status')
                    ->where('id', $id)
                    ->get()
                    ->getRowArray();
            } elseif ($kind === 'sales-orders') {
                $record = $db->table('sales_orders')
                    ->select('id, so_number, customer_name, batch_qty, status')
                    ->where('id', $id)
                    ->get()
                    ->getRowArray();
            } elseif ($kind === 'problems') {
                $record = $db->table('problems')
                    ->select('id, code, name, status')
                    ->where('id', $id)
                    ->get()
                    ->getRowArray();
            } else {
                $record = $db->table('sectors')
                    ->select('id, name, status')
                    ->where('id', $id)
                    ->get()
                    ->getRowArray();
            }

            if ($record === null) {
                $label = $this->masterLabel($kind);
                return $this->response->setStatusCode($isCreate ? 500 : 404)->setJSON([
                    'success' => false,
                    'saved' => $isCreate,
                    'record_id' => $isCreate ? $id : null,
                    'message' => $isCreate
                        ? "The {$label} was saved, but its details could not be loaded. Check the table before retrying."
                        : "{$label} not found.",
                    'csrf' => $this->currentCsrfToken(),
                ]);
            }

            $record['id'] = (int) $record['id'];
            if ($kind === 'activities') $record['sector_id'] = (int) $record['sector_id'];
            if ($kind === 'sales-orders') $record['batch_qty'] = (string) $record['batch_qty'];
            $label = $this->masterLabel($kind);

            return $this->response->setStatusCode($isCreate ? 201 : 200)->setJSON([
                'success' => true,
                'message' => $isCreate
                    ? "{$label} created successfully. If it is not visible, check the current search or filter."
                    : "{$label} updated successfully.",
                'record_id' => $id,
                'record' => $record,
                'toggle_url' => site_url('admin/' . $kind . '/' . $id . '/toggle'),
                'csrf' => $this->currentCsrfToken(),
            ]);
        }

        return redirect()->to('/admin/' . $kind)->with('message', 'Saved.');
    }

    public function toggleMaster(string $kind, int $id)
    {
        $config = self::MASTERS[$kind] ?? null;
        if (! $config) return $this->response->setStatusCode(404);
        $table = $config['table'] ?? $kind;
        $db = db_connect();
        $row = $db->table($table)->where('id', $id)->get()->getRowArray();

        if ($row === null) {
            if ($this->isAjaxMasterMutation($kind)) {
                $label = $this->masterLabel($kind);
                return $this->response->setStatusCode(404)->setJSON([
                    'success' => false,
                    'message' => "{$label} not found.",
                    'csrf' => $this->currentCsrfToken(),
                ]);
            }

            return $this->response->setStatusCode(404);
        }

        $newStatus = $row['status'] === 'active' ? 'inactive' : 'active';
        $db->table($table)->where('id', $id)->update(['status' => $newStatus]);

        if ($this->isAjaxMasterMutation($kind)) {
            $label = $this->masterLabel($kind);
            return $this->response->setJSON([
                'success' => true,
                'record_id' => $id,
                'status' => $newStatus,
                'message' => "{$label} status updated.",
                'csrf' => $this->currentCsrfToken(),
            ]);
        }

        return redirect()->to('/admin/' . $kind)->with('message', 'Status updated.');
    }

    public function workforce()
    {
        $this->preparePage();
        $db = db_connect();
        if ($this->request->is('post')) {
            $isAjax = $this->request->isAJAX();
            $validationError = function (string $message) use ($isAjax) {
                if ($isAjax) {
                    return $this->response->setStatusCode(422)->setJSON([
                        'success' => false,
                        'message' => $message,
                        'csrf' => $this->currentCsrfToken(),
                    ]);
                }

                return redirect()->back()->withInput()->with('error', $message);
            };

            $role = (string) $this->request->getPost('role');
            if (! in_array($role, ['supervisor', 'operator', 'qc'], true)) return $validationError('Select supervisor, operator, or QC.');
            $name = trim((string) $this->request->getPost('name'));
            $username = trim((string) $this->request->getPost('username'));
            $password = (string) $this->request->getPost('password');
            $recordId = (int) $this->request->getPost('id');
            if ($name === '') return $validationError('Name is required.');
            if ($role === 'supervisor' && ($username === '' || (! $recordId && strlen($password) < 8))) {
                return $validationError('Supervisor login needs a username and password of at least 8 characters.');
            }

            if ($role === 'supervisor' && $db->table('workforce')->where('username', mb_strtolower($username))->where('id !=', $recordId ?: 0)->countAllResults()) {
                return $validationError('That username is already in use.');
            }

            $now = gmdate('Y-m-d H:i:s');
            $id = $recordId;
            if ($id) {
                $existing = $db->table('workforce')->where('id', $id)->whereIn('role', ['supervisor', 'operator', 'qc'])->get()->getRowArray();
                if ($existing === null) return $validationError('Workforce member not found.');
                if ($existing['role'] !== $role) return $validationError('A workforce member’s role cannot be changed after creation.');

                $data = ['name' => $name, 'updated_at' => $now];
                if ($role === 'supervisor') { $data['username'] = mb_strtolower($username); if ($password !== '') $data['password_hash'] = password_hash($password, PASSWORD_DEFAULT); }
                $db->table('workforce')->where('id', $id)->where('role', $role)->update($data);
                $message = 'Workforce record updated.';
                $statusCode = 200;
            } else {
                $db->table('workforce')->insert([
                    'staff_id' => 'PENDING-' . bin2hex(random_bytes(8)),
                    'name' => $name,
                    'username' => $role === 'supervisor' ? mb_strtolower($username) : null,
                    'password_hash' => $role === 'supervisor' ? password_hash($password, PASSWORD_DEFAULT) : null,
                    'role' => $role,
                    'status' => 'active',
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
                $id = (int) $db->insertID();
                $staffId = sprintf('SID%06d', $id);
                $db->table('workforce')->where('id', $id)->update(['staff_id' => $staffId]);
                $message = 'Workforce record created with ID ' . $staffId . '.';
                $statusCode = 201;
            }

            if ($isAjax) {
                $record = $db->table('workforce')
                    ->select('id, staff_id, name, role, username, status')
                    ->where('id', $id)
                    ->get()
                    ->getRowArray();

                if ($record === null) {
                    return $this->response->setStatusCode(500)->setJSON([
                        'success' => false,
                        'saved' => true,
                        'record_id' => $id,
                        'message' => 'The workforce member was saved, but its details could not be loaded. Check the table before retrying.',
                        'csrf' => $this->currentCsrfToken(),
                    ]);
                }

                $record['id'] = (int) $record['id'];

                return $this->response->setStatusCode($statusCode)->setJSON([
                    'success' => true,
                    'message' => $message,
                    'record_id' => $id,
                    'record' => $record,
                    'toggle_url' => site_url('admin/workforce/' . $id . '/toggle'),
                    'csrf' => $this->currentCsrfToken(),
                ]);
            }

            return redirect()->to('/admin/workforce')->with('message', $message);
        }

        $editRecord = null;
        $editId = (int) $this->request->getGet('edit');
        if ($editId > 0) {
            $editRecord = $db->table('workforce')
                ->where('id', $editId)
                ->whereIn('role', ['supervisor', 'operator', 'qc'])
                ->get()
                ->getRowArray();
        }

        return view('admin/workforce', [
            'title' => 'Workforce',
            'rows' => $db->table('workforce')->orderBy('id', 'ASC')->get()->getResultArray(),
            'editRecord' => $editRecord,
        ]);
    }

    public function toggleWorkforce(int $id)
    {
        $db = db_connect();
        $row = $db->table('workforce')->where('id', $id)->whereIn('role', ['supervisor', 'operator', 'qc'])->get()->getRowArray();
        if ($row === null) {
            if ($this->request->isAJAX()) {
                return $this->response->setStatusCode(404)->setJSON([
                    'success' => false,
                    'message' => 'Workforce member not found.',
                    'csrf' => $this->currentCsrfToken(),
                ]);
            }

            return $this->response->setStatusCode(404);
        }

        $newStatus = $row['status'] === 'active' ? 'inactive' : 'active';
        $db->table('workforce')->where('id', $id)->whereIn('role', ['supervisor', 'operator', 'qc'])->update(['status' => $newStatus]);

        if ($this->request->isAJAX()) {
            return $this->response->setJSON([
                'success' => true,
                'record_id' => $id,
                'status' => $newStatus,
                'message' => 'Workforce status updated.',
                'csrf' => $this->currentCsrfToken(),
            ]);
        }

        return redirect()->to('/admin/workforce')->with('message', 'Workforce status updated.');
    }

    public function processes()
    {
        $this->preparePage();
        $db = db_connect();
        $rows = $db->table('processes p')
            ->select('p.id, p.status, p.started_at, p.ended_at, p.qty_passed, p.qty_rejected, so.so_number, m.machine_name, s.name AS sector_name, a.name AS activity_name, w.name AS supervisor_name')
            ->join('sales_orders so', 'so.id = p.sales_order_id', 'left')
            ->join('machines m', 'm.id = p.machine_id')
            ->join('sectors s', 's.id = p.sector_id')
            ->join('activities a', 'a.id = p.activity_id')
            ->join('workforce w', 'w.id = p.current_supervisor_workforce_id')
            ->where('p.deleted_at', null)
            ->orderBy('p.started_at', 'ASC')
            ->get()
            ->getResultArray();

        return view('admin/processes', [
            'title' => 'Operation reports',
            'rows' => $rows,
            'total' => count($rows),
        ]);
    }

    public function processDetails(int $id)
    {
        $this->preparePage();
        $db = db_connect();
        $row = $db->table('processes p')->select('p.*, so.so_number, so.customer_name, so.batch_qty, m.machine_name, m.machine_code, s.name AS sector_name, a.name AS activity_name, w.name AS supervisor_name')
            ->join('sales_orders so', 'so.id=p.sales_order_id', 'left')->join('machines m', 'm.id=p.machine_id')->join('sectors s', 's.id=p.sector_id')->join('activities a', 'a.id=p.activity_id')->join('workforce w', 'w.id=p.current_supervisor_workforce_id')->where('p.id', $id)->get()->getRowArray();
        if (! $row) return $this->response->setStatusCode(404)->setBody('Process record not found.');
        $row['operators'] = $db->table('process_operators po')->select('w.staff_id,w.name,po.assigned_at,po.removed_at')->join('workforce w','w.id=po.workforce_id')->where('po.process_id',$id)->orderBy('po.assigned_at')->get()->getResultArray();
        $row['qc'] = $db->table('qc_inspections qi')->select('w.staff_id,w.name,qi.inspection_time,qi.quantity')->join('workforce w','w.id=qi.qc_workforce_id')->where('qi.process_id',$id)->orderBy('qi.inspection_time')->get()->getResultArray();
        $row['problems'] = $db->table('process_problems pp')->select('p.code,p.name,pp.created_at')->join('problems p','p.id=pp.problem_id')->where('pp.process_id',$id)->get()->getResultArray();
        $row['pics'] = $db->table('process_pic_history ph')->select('w.staff_id,w.name,ph.started_at,ph.ended_at')->join('workforce w','w.id=ph.supervisor_workforce_id')->where('ph.process_id',$id)->orderBy('ph.started_at')->get()->getResultArray();
        return view('admin/process-details', ['title' => 'Process #' . $id, 'row' => $row]);
    }

    private function preparePage(): void
    {
        helper(['form', 'url', 'kinta_time']);
        $this->response->setHeader('Cache-Control', 'no-store, no-cache, must-revalidate, private');
    }

    private function masterValidationRedirect(string $kind, string $message)
    {
        if ($this->isAjaxMasterMutation($kind)) {
            return $this->response->setStatusCode(422)->setJSON([
                'success' => false,
                'message' => $message,
                'csrf' => $this->currentCsrfToken(),
            ]);
        }

        $redirect = redirect()->back()->with('error', $message);

        if (in_array($kind, ['activities', 'machines', 'sectors', 'sales-orders', 'problems'], true)) {
            $redirect->withInput();
        }

        return $redirect;
    }

    private function isAjaxMasterMutation(string $kind): bool
    {
        return in_array($kind, ['activities', 'machines', 'sectors', 'sales-orders', 'problems'], true)
            && $this->request->isAJAX();
    }

    private function masterLabel(string $kind): string
    {
        return match ($kind) {
            'activities' => 'Activity',
            'machines' => 'Machine',
            'sectors' => 'Sector',
            'sales-orders' => 'Sales Order',
            'problems' => 'Problem',
            default => 'Record',
        };
    }

    private function salesOrderNumberExists(string $soNumber, int $exceptId = 0): bool
    {
        $query = db_connect()->table('sales_orders')->where('so_number', $soNumber);
        if ($exceptId > 0) $query->where('id !=', $exceptId);

        return $query->countAllResults() > 0;
    }

    private function problemCodeExists(string $code, int $exceptId = 0): bool
    {
        $query = db_connect()->table('problems')->where('code', $code);
        if ($exceptId > 0) $query->where('id !=', $exceptId);

        return $query->countAllResults() > 0;
    }

    private function machineCodeExists(string $machineCode, int $exceptId = 0): bool
    {
        $query = db_connect()->table('machines')->where('machine_code', $machineCode);
        if ($exceptId > 0) $query->where('id !=', $exceptId);

        return $query->countAllResults() > 0;
    }

    /** @return array{name: string, hash: string} */
    private function currentCsrfToken(): array
    {
        return [
            'name' => csrf_token(),
            'hash' => csrf_hash(),
        ];
    }
}
