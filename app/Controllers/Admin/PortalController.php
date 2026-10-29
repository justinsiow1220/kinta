<?php

namespace App\Controllers\Admin;

use CodeIgniter\Controller;

/** Online-only session portal for administrators. */
class PortalController extends Controller
{
    private const MASTERS = [
        'machines' => ['title' => 'Machines', 'fields' => ['machine_code' => 'Machine code', 'machine_name' => 'Machine name']],
        'sectors' => ['title' => 'Sectors', 'fields' => ['name' => 'Sector name']],
        'activities' => ['title' => 'Activities', 'fields' => ['sector_id' => 'Sector', 'name' => 'Activity name']],
        'sales-orders' => ['title' => 'Sales Orders', 'table' => 'sales_orders', 'fields' => ['so_number' => 'S/O number', 'customer_name' => 'Customer', 'batch_qty' => 'Batch quantity']],
        'problems' => ['title' => 'Problems', 'fields' => ['code' => 'Problem code', 'name' => 'Problem name']],
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
        return view('admin/home', ['title' => 'Dashboard', 'recent' => $recent, 'latestMaster' => array_slice($latestMaster, 0, 15), 'counts' => $counts]);
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
        $rows = $query->orderBy($table . '.id', 'DESC')->get()->getResultArray();
        return view('admin/master', [
            'title' => $config['title'], 'kind' => $kind, 'fields' => $config['fields'],
            'rows' => $rows, 'sectors' => $db->table('sectors')->orderBy('name')->get()->getResultArray(),
        ]);
    }

    public function saveMaster(string $kind)
    {
        $config = self::MASTERS[$kind] ?? null;
        if (! $config) return $this->response->setStatusCode(404);
        $table = $config['table'] ?? $kind;
        $values = [];
        foreach (array_keys($config['fields']) as $field) $values[$field] = trim((string) $this->request->getPost($field));
        if (in_array('', array_diff_key($values, ['status' => true]), true)) return redirect()->back()->with('error', 'Complete all required fields.');
        if (isset($values['batch_qty']) && filter_var($values['batch_qty'], FILTER_VALIDATE_INT) === false) return redirect()->back()->with('error', 'Batch quantity must be a whole number.');
        if (isset($values['sector_id']) && ! db_connect()->table('sectors')->where('id', (int) $values['sector_id'])->countAllResults()) return redirect()->back()->with('error', 'Select a valid sector.');
        $db = db_connect();
        $columns = $db->getFieldNames($table);
        $now = gmdate('Y-m-d H:i:s');
        if (in_array('updated_at', $columns, true)) $values['updated_at'] = $now;
        $id = (int) $this->request->getPost('id');
        if ($id) $db->table($table)->where('id', $id)->update($values);
        else { $values['status'] = $this->request->getPost('status') ?: 'active'; if (in_array('created_at', $columns, true)) $values['created_at'] = $now; $db->table($table)->insert($values); }
        return redirect()->to('/admin/master-data/' . $kind)->with('message', 'Saved.');
    }

    public function toggleMaster(string $kind, int $id)
    {
        $config = self::MASTERS[$kind] ?? null;
        if (! $config) return $this->response->setStatusCode(404);
        $table = $config['table'] ?? $kind;
        $row = db_connect()->table($table)->where('id', $id)->get()->getRowArray();
        if ($row) db_connect()->table($table)->where('id', $id)->update(['status' => $row['status'] === 'active' ? 'inactive' : 'active']);
        return redirect()->to('/admin/master-data/' . $kind)->with('message', 'Status updated.');
    }

    public function workforce()
    {
        $this->preparePage();
        $db = db_connect();
        if ($this->request->is('post')) {
            $role = (string) $this->request->getPost('role');
            if (! in_array($role, ['supervisor', 'operator', 'qc'], true)) return redirect()->back()->with('error', 'Select supervisor, operator, or QC.');
            $name = trim((string) $this->request->getPost('name'));
            $username = trim((string) $this->request->getPost('username'));
            $password = (string) $this->request->getPost('password');
            $recordId = (int) $this->request->getPost('id');
            if ($name === '' || ($role === 'supervisor' && ($username === '' || (! $recordId && strlen($password) < 8)))) return redirect()->back()->with('error', 'Supervisor login needs a username and password of at least 8 characters.');
            if ($role === 'supervisor' && $db->table('workforce')->where('username', mb_strtolower($username))->where('id !=', $recordId ?: 0)->countAllResults()) return redirect()->back()->with('error', 'That username is already in use.');
            $now = gmdate('Y-m-d H:i:s');
            $id = (int) $this->request->getPost('id');
            if ($id) {
                $data = ['name' => $name, 'updated_at' => $now];
                if ($role === 'supervisor') { $data['username'] = mb_strtolower($username); if ($password !== '') $data['password_hash'] = password_hash($password, PASSWORD_DEFAULT); }
                $db->table('workforce')->where('id', $id)->where('role', $role)->update($data);
                return redirect()->to('/admin/workforce')->with('message', 'Workforce record updated.');
            }
            $db->table('workforce')->insert(['staff_id' => 'PENDING-' . bin2hex(random_bytes(8)), 'name' => $name, 'username' => $role === 'supervisor' ? mb_strtolower($username) : null, 'password_hash' => $role === 'supervisor' ? password_hash($password, PASSWORD_DEFAULT) : null, 'role' => $role, 'status' => 'active', 'created_at' => $now, 'updated_at' => $now]);
            $id = (int) $db->insertID();
            $staffId = sprintf('SID%06d', $id);
            $db->table('workforce')->where('id', $id)->update(['staff_id' => $staffId]);
            return redirect()->to('/admin/workforce')->with('message', 'Workforce record created with ID ' . $staffId . '.');
        }
        return view('admin/workforce', ['title' => 'Workforce', 'rows' => $db->table('workforce')->orderBy('id', 'DESC')->get()->getResultArray()]);
    }

    public function toggleWorkforce(int $id)
    {
        $row = db_connect()->table('workforce')->where('id', $id)->whereIn('role', ['supervisor', 'operator', 'qc'])->get()->getRowArray();
        if ($row) db_connect()->table('workforce')->where('id', $id)->update(['status' => $row['status'] === 'active' ? 'inactive' : 'active']);
        return redirect()->to('/admin/workforce')->with('message', 'Workforce status updated.');
    }

    public function processes()
    {
        $this->preparePage();
        $db = db_connect();
        $builder = $db->table('processes p')->select('p.id, p.status, p.started_at, p.ended_at, p.qty_passed, p.qty_rejected, so.so_number, m.machine_name, s.name AS sector_name, a.name AS activity_name, w.name AS supervisor_name')
            ->join('sales_orders so', 'so.id=p.sales_order_id', 'left')->join('machines m', 'm.id=p.machine_id')->join('sectors s', 's.id=p.sector_id')->join('activities a', 'a.id=p.activity_id')->join('workforce w', 'w.id=p.current_supervisor_workforce_id');
        $search = trim((string) $this->request->getGet('q'));
        if ($search !== '') $builder->groupStart()->like('so.so_number', $search)->orLike('m.machine_name', $search)->orLike('w.name', $search)->groupEnd();
        $date = (string) $this->request->getGet('date');
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            $localZone = new \DateTimeZone('Asia/Kuala_Lumpur');
            $utcZone = new \DateTimeZone('UTC');
            $localStart = new \DateTimeImmutable($date . ' 00:00:00', $localZone);
            $utcStart = $localStart->setTimezone($utcZone)->format('Y-m-d H:i:s');
            $utcEnd = $localStart->modify('+1 day')->setTimezone($utcZone)->format('Y-m-d H:i:s');
            $builder->where('p.started_at >=', $utcStart)->where('p.started_at <', $utcEnd);
        }
        if ($machineId = $this->request->getGet('machine_id')) $builder->where('p.machine_id', (int) $machineId);
        if ($supervisorId = $this->request->getGet('supervisor_id')) $builder->where('p.current_supervisor_workforce_id', (int) $supervisorId);
        $builder->where('p.deleted_at', null);
        $total = (clone $builder)->countAllResults();
        $perPage = 25;
        $page = max(1, (int) $this->request->getGet('page'));
        $pageCount = max(1, (int) ceil($total / $perPage));
        $page = min($page, $pageCount);
        $rows = $builder->orderBy('p.started_at', 'DESC')->limit($perPage, ($page - 1) * $perPage)->get()->getResultArray();
        $query = array_filter(['q' => $search, 'date' => $date, 'machine_id' => $this->request->getGet('machine_id'), 'supervisor_id' => $this->request->getGet('supervisor_id')], static fn ($value) => $value !== null && $value !== '');
        $pageUrls = [];
        for ($number = max(1, $page - 2); $number <= min($pageCount, $page + 2); $number++) $pageUrls[$number] = site_url('admin/operations') . '?' . http_build_query($query + ['page' => $number]);
        return view('admin/processes', [
            'title' => 'Operation reports', 'rows' => $rows, 'total' => $total, 'page' => $page,
            'pageCount' => $pageCount, 'pageUrls' => $pageUrls,
            'q' => $search, 'date' => $this->request->getGet('date'),
            'machines' => $db->table('machines')->orderBy('machine_name')->get()->getResultArray(),
            'supervisors' => $db->table('workforce')->where('role', 'supervisor')->orderBy('name')->get()->getResultArray(),
            'machineId' => $this->request->getGet('machine_id'), 'supervisorId' => $this->request->getGet('supervisor_id'),
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
}
