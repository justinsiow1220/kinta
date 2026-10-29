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
        'sales-orders' => ['title' => 'Sales Orders', 'table' => 'sales_orders', 'fields' => ['so_number' => 'S/O number', 'customer_name' => 'Customer', 'batch_qty' => 'Batch quantity', 'status' => 'Status']],
        'problems' => ['title' => 'Problems', 'fields' => ['code' => 'Problem code', 'name' => 'Problem name']],
    ];

    public function login()
    {
        if (session('admin_workforce_id')) return redirect()->to('/admin');
        if ($this->request->getMethod() === 'post') {
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
        return view('admin/home', ['title' => 'Dashboard']);
    }

    public function master(string $kind)
    {
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
        $values['status'] = $values['status'] ?? 'active';
        if (in_array('', array_diff_key($values, ['status' => true]), true)) return redirect()->back()->with('error', 'Complete all required fields.');
        if (isset($values['batch_qty']) && filter_var($values['batch_qty'], FILTER_VALIDATE_INT) === false) return redirect()->back()->with('error', 'Batch quantity must be a whole number.');
        if (isset($values['sector_id']) && ! db_connect()->table('sectors')->where('id', (int) $values['sector_id'])->countAllResults()) return redirect()->back()->with('error', 'Select a valid sector.');
        $values['updated_at'] = date('Y-m-d H:i:s');
        $id = (int) $this->request->getPost('id');
        if ($id) db_connect()->table($table)->where('id', $id)->update($values);
        else { $values['created_at'] = date('Y-m-d H:i:s'); db_connect()->table($table)->insert($values); }
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
        $db = db_connect();
        if ($this->request->getMethod() === 'post') {
            $role = (string) $this->request->getPost('role');
            if (! in_array($role, ['supervisor', 'operator', 'qc'], true)) return redirect()->back()->with('error', 'Select supervisor, operator, or QC.');
            $name = trim((string) $this->request->getPost('name'));
            $username = trim((string) $this->request->getPost('username'));
            $password = (string) $this->request->getPost('password');
            $recordId = (int) $this->request->getPost('id');
            if ($name === '' || ($role === 'supervisor' && ($username === '' || (! $recordId && strlen($password) < 8)))) return redirect()->back()->with('error', 'Supervisor login needs a username and password of at least 8 characters.');
            $now = date('Y-m-d H:i:s');
            $id = (int) $this->request->getPost('id');
            if ($id) {
                $data = ['name' => $name, 'updated_at' => $now];
                if ($role === 'supervisor') { $data['username'] = mb_strtolower($username); if ($password !== '') $data['password_hash'] = password_hash($password, PASSWORD_DEFAULT); }
                $db->table('workforce')->where('id', $id)->where('role', $role)->update($data);
                return redirect()->to('/admin/workforce')->with('message', 'Workforce record updated.');
            }
            $db->table('workforce')->insert(['staff_id' => 'PENDING', 'name' => $name, 'username' => $role === 'supervisor' ? mb_strtolower($username) : null, 'password_hash' => $role === 'supervisor' ? password_hash($password, PASSWORD_DEFAULT) : null, 'role' => $role, 'status' => 'active', 'created_at' => $now, 'updated_at' => $now]);
            $id = (int) $db->insertID();
            $staffId = sprintf('SID%06d', $id);
            $db->table('workforce')->where('id', $id)->update(['staff_id' => $staffId]);
            return redirect()->to('/admin/workforce')->with('message', 'Workforce record created with ID ' . $staffId . '.');
        }
        return view('admin/workforce', ['title' => 'Workforce', 'rows' => $db->table('workforce')->whereIn('role', ['supervisor', 'operator', 'qc'])->orderBy('id', 'DESC')->get()->getResultArray()]);
    }

    public function toggleWorkforce(int $id)
    {
        $row = db_connect()->table('workforce')->where('id', $id)->whereIn('role', ['supervisor', 'operator', 'qc'])->get()->getRowArray();
        if ($row) db_connect()->table('workforce')->where('id', $id)->update(['status' => $row['status'] === 'active' ? 'inactive' : 'active']);
        return redirect()->to('/admin/workforce')->with('message', 'Workforce status updated.');
    }

    public function processes()
    {
        $db = db_connect();
        $builder = $db->table('processes p')->select('p.id, p.status, p.started_at, p.ended_at, p.qty_passed, p.qty_rejected, so.so_number, m.machine_name, s.name AS sector_name, a.name AS activity_name, w.name AS supervisor_name')
            ->join('sales_orders so', 'so.id=p.sales_order_id', 'left')->join('machines m', 'm.id=p.machine_id')->join('sectors s', 's.id=p.sector_id')->join('activities a', 'a.id=p.activity_id')->join('workforce w', 'w.id=p.current_supervisor_workforce_id');
        $search = trim((string) $this->request->getGet('q'));
        if ($search !== '') $builder->groupStart()->like('so.so_number', $search)->orLike('m.machine_name', $search)->orLike('w.name', $search)->groupEnd();
        if ($date = $this->request->getGet('date')) $builder->where('DATE(p.started_at)', $date);
        $rows = $builder->where('p.deleted_at', null)->orderBy('p.started_at', 'DESC')->paginate(25);
        return view('admin/processes', ['title' => 'Operation reports', 'rows' => $rows, 'pager' => $builder->pager, 'q' => $search, 'date' => $this->request->getGet('date')]);
    }

    public function processDetails(int $id)
    {
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
}
