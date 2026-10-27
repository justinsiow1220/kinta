<?php

namespace App\Controllers\Api;

use CodeIgniter\RESTful\ResourceController;
use Throwable;

class ProcessController extends ResourceController
{
    public function preflight()
    {
        return $this->response->setStatusCode(204);
    }

    public function active()
    {
        $db = db_connect();
        $records = $db
            ->table('processes p')
            ->select(
                'p.id, p.machine_id, m.machine_code, m.machine_name, '
                . 'p.sector_id, s.name AS sector_name, '
                . 'p.activity_id, a.name AS activity_name, '
                . 'p.sales_order_id, so.so_number, '
                . 'p.current_supervisor_id, u.name AS supervisor_name, '
                . 'p.status, p.started_at'
            )
            ->join('machines m', 'm.id = p.machine_id')
            ->join('sectors s', 's.id = p.sector_id')
            ->join('activities a', 'a.id = p.activity_id')
            ->join('sales_orders so', 'so.id = p.sales_order_id', 'left')
            ->join('users u', 'u.id = p.current_supervisor_id')
            ->where('p.status', 'active')
            ->orderBy('p.started_at', 'DESC')
            ->get()
            ->getResultArray();

        if ($records === []) {
            return $this->respondWithData('Active processes loaded.', $records);
        }

        $processIds = array_map(static fn (array $record): int => (int) $record['id'], $records);
        $operatorRows = $db->table('process_operators po')
            ->select('po.process_id, po.user_id, u.name AS operator_name')
            ->join('users u', 'u.id = po.user_id')
            ->whereIn('po.process_id', $processIds)
            ->where('po.removed_at', null)
            ->orderBy('u.name', 'ASC')
            ->get()
            ->getResultArray();

        $operatorsByProcess = [];
        foreach ($operatorRows as $operatorRow) {
            $operatorsByProcess[(int) $operatorRow['process_id']][] = [
                'id'   => (int) $operatorRow['user_id'],
                'name' => $operatorRow['operator_name'],
            ];
        }

        foreach ($records as &$record) {
            $record['operators'] = $operatorsByProcess[(int) $record['id']] ?? [];
        }
        unset($record);

        return $this->respondWithData('Active processes loaded.', $records);
    }

    public function show($id = null)
    {
        $processId = $this->positiveId($id);
        if ($processId === null) {
            return $this->respondWithError('A valid process ID is required.', 400);
        }

        $db = db_connect();
        $process = $db->table('processes p')
            ->select(
                'p.id, p.description, p.status, p.started_at, p.ended_at, '
                . 'p.qty_passed, p.qty_rejected, p.remarks, '
                . 'm.machine_code, m.machine_name, s.name AS sector_name, '
                . 'a.name AS activity_name, so.so_number, so.customer_name, so.batch_qty, '
                . 'p.current_supervisor_id, current_pic.name AS current_supervisor_name'
            )
            ->join('machines m', 'm.id = p.machine_id')
            ->join('sectors s', 's.id = p.sector_id')
            ->join('activities a', 'a.id = p.activity_id')
            ->join('sales_orders so', 'so.id = p.sales_order_id', 'left')
            ->join('users current_pic', 'current_pic.id = p.current_supervisor_id')
            ->where('p.id', $processId)
            ->get()
            ->getRowArray();

        if ($process === null) {
            return $this->respondWithError('Process not found.', 404);
        }

        $process['operators'] = $db->table('process_operators po')
            ->select('po.id, po.user_id, u.name AS operator_name, po.assigned_at, po.removed_at')
            ->join('users u', 'u.id = po.user_id')
            ->where('po.process_id', $processId)
            ->orderBy('po.assigned_at', 'ASC')
            ->orderBy('po.id', 'ASC')
            ->get()
            ->getResultArray();

        $process['qc_inspections'] = $db->table('qc_inspections qi')
            ->select('qi.id, qi.qc_user_id, u.name AS qc_user_name, qi.inspection_time, qi.quantity')
            ->join('users u', 'u.id = qi.qc_user_id')
            ->where('qi.process_id', $processId)
            ->orderBy('qi.inspection_time', 'DESC')
            ->orderBy('qi.id', 'DESC')
            ->get()
            ->getResultArray();

        $process['problems'] = $db->table('process_problems pp')
            ->select('pp.id, pp.problem_id, p.code, p.name, pp.created_at')
            ->join('problems p', 'p.id = pp.problem_id')
            ->where('pp.process_id', $processId)
            ->orderBy('p.code', 'ASC')
            ->get()
            ->getResultArray();

        $process['pic_history'] = $db->table('process_pic_history ph')
            ->select('ph.id, ph.supervisor_id, u.name AS supervisor_name, ph.started_at, ph.ended_at')
            ->join('users u', 'u.id = ph.supervisor_id')
            ->where('ph.process_id', $processId)
            ->orderBy('ph.started_at', 'ASC')
            ->orderBy('ph.id', 'ASC')
            ->get()
            ->getResultArray();

        return $this->respond([
            'success' => true,
            'message' => 'Process details loaded.',
            'data'    => $process,
        ]);
    }

    public function qcInspections($id = null)
    {
        $processId = $this->positiveId($id);
        if ($processId === null) {
            return $this->respondWithError('A valid process ID is required.', 400);
        }

        $db = db_connect();
        if (! $db->table('processes')->where('id', $processId)->countAllResults()) {
            return $this->respondWithError('Process not found.', 404);
        }

        $records = $db->table('qc_inspections qi')
            ->select('qi.id, qi.process_id, qi.qc_user_id, u.name AS qc_user_name, qi.inspection_time, qi.quantity')
            ->join('users u', 'u.id = qi.qc_user_id')
            ->where('qi.process_id', $processId)
            ->orderBy('qi.inspection_time', 'DESC')
            ->orderBy('qi.id', 'DESC')
            ->get()
            ->getResultArray();

        return $this->respondWithData('QC inspections loaded.', $records);
    }

    public function recordQcInspection($id = null)
    {
        $processId = $this->positiveId($id);
        if ($processId === null) {
            return $this->respondWithError('A valid process ID is required.', 400);
        }

        $payload = $this->request->getJSON(true);
        if (! is_array($payload)) {
            return $this->respondWithError('A JSON request body is required.', 400);
        }

        $qcUserId = $this->positiveId($payload['qc_user_id'] ?? null);
        $validQuantity = true;
        $quantity = $this->optionalQuantity($payload, 'quantity', $validQuantity);
        if ($qcUserId === null || ! $validQuantity || $quantity === null) {
            return $this->respondWithError(
                'qc_user_id must be a positive integer and quantity must be an unsigned integer.',
                422,
            );
        }

        $db = db_connect();
        $process = $db->table('processes')
            ->select('id, status')
            ->where('id', $processId)
            ->get()
            ->getRowArray();

        if ($process === null) {
            return $this->respondWithError('Process not found.', 404);
        }
        if ($process['status'] !== 'active') {
            return $this->respondWithError('QC inspections can only be recorded for active processes.', 409);
        }
        if (! $this->userExistsWithRole($db, $qcUserId, 'qc')) {
            return $this->respondWithError('The selected QC user is not active or does not have the QC role.', 422);
        }

        $now = date('Y-m-d H:i:s');
        $inserted = $db->table('qc_inspections')->insert([
            'process_id'      => $processId,
            'qc_user_id'      => $qcUserId,
            'inspection_time' => $now,
            'quantity'        => $quantity,
            'created_at'      => $now,
            'updated_at'      => $now,
        ]);

        if (! $inserted) {
            return $this->respondWithError('CI4 could not record the QC inspection.', 500);
        }

        $inspectionId = (int) $db->insertID();
        $inspection = $db->table('qc_inspections qi')
            ->select('qi.id, qi.process_id, qi.qc_user_id, u.name AS qc_user_name, qi.inspection_time, qi.quantity')
            ->join('users u', 'u.id = qi.qc_user_id')
            ->where('qi.id', $inspectionId)
            ->get()
            ->getRowArray();

        return $this->respondCreated([
            'success' => true,
            'message' => 'QC inspection recorded.',
            'data'    => $inspection,
        ]);
    }

    public function processProblems($id = null)
    {
        $processId = $this->positiveId($id);
        if ($processId === null) {
            return $this->respondWithError('A valid process ID is required.', 400);
        }

        $db = db_connect();
        if (! $db->table('processes')->where('id', $processId)->countAllResults()) {
            return $this->respondWithError('Process not found.', 404);
        }

        $records = $db->table('process_problems pp')
            ->select('pp.id, pp.process_id, pp.problem_id, p.code, p.name, pp.created_at')
            ->join('problems p', 'p.id = pp.problem_id')
            ->where('pp.process_id', $processId)
            ->orderBy('p.code', 'ASC')
            ->get()
            ->getResultArray();

        return $this->respondWithData('Process problems loaded.', $records);
    }

    public function attachProblems($id = null)
    {
        $processId = $this->positiveId($id);
        if ($processId === null) {
            return $this->respondWithError('A valid process ID is required.', 400);
        }

        $payload = $this->request->getJSON(true);
        if (! is_array($payload)) {
            return $this->respondWithError('A JSON request body is required.', 400);
        }

        $problemIds = $payload['problem_ids'] ?? null;
        if (! is_array($problemIds) || ! array_is_list($problemIds) || $problemIds === []) {
            return $this->respondWithError('problem_ids must be a non-empty list of problem IDs.', 422);
        }

        $problemIds = array_map(fn ($problemId) => $this->positiveId($problemId), $problemIds);
        if (in_array(null, $problemIds, true)) {
            return $this->respondWithError('Each problem ID must be a positive integer.', 422);
        }
        $problemIds = array_values(array_unique($problemIds));

        $db = db_connect();
        $process = $db->table('processes')
            ->select('id, status')
            ->where('id', $processId)
            ->get()
            ->getRowArray();

        if ($process === null) {
            return $this->respondWithError('Process not found.', 404);
        }
        $activeProblemCount = $db->table('problems')
            ->whereIn('id', $problemIds)
            ->where('status', 'active')
            ->countAllResults();
        if ($activeProblemCount !== count($problemIds)) {
            return $this->respondWithError('Every selected problem must be active and exist.', 422);
        }

        $alreadyAttachedCount = $db->table('process_problems')
            ->where('process_id', $processId)
            ->whereIn('problem_id', $problemIds)
            ->countAllResults();
        if ($alreadyAttachedCount > 0) {
            return $this->respondWithError('One or more selected problems are already attached to this process.', 409);
        }

        $now = date('Y-m-d H:i:s');
        $rows = array_map(
            static fn (int $problemId): array => [
                'process_id' => $processId,
                'problem_id' => $problemId,
                'created_at' => $now,
            ],
            $problemIds,
        );

        $db->transBegin();
        try {
            $db->table('process_problems')->insertBatch($rows);
            if (! $db->transStatus()) {
                throw new \RuntimeException('The process-problem transaction could not be completed.');
            }
            $db->transCommit();
        } catch (Throwable $exception) {
            $db->transRollback();
            log_message('error', 'Attaching process problems failed: {message}', [
                'message' => $exception->getMessage(),
            ]);

            return $this->respondWithError('CI4 could not attach the selected problems.', 500);
        }

        return $this->respondCreated([
            'success' => true,
            'message' => 'Problems attached to the process.',
            'data'    => ['process_id' => $processId, 'problem_ids' => $problemIds],
        ]);
    }

    public function picHistory($id = null)
    {
        $processId = $this->positiveId($id);
        if ($processId === null) {
            return $this->respondWithError('A valid process ID is required.', 400);
        }

        $db = db_connect();
        if (! $db->table('processes')->where('id', $processId)->countAllResults()) {
            return $this->respondWithError('Process not found.', 404);
        }

        $records = $db->table('process_pic_history ph')
            ->select('ph.id, ph.process_id, ph.supervisor_id, u.name AS supervisor_name, ph.started_at, ph.ended_at')
            ->join('users u', 'u.id = ph.supervisor_id')
            ->where('ph.process_id', $processId)
            ->orderBy('ph.started_at', 'DESC')
            ->orderBy('ph.id', 'DESC')
            ->get()
            ->getResultArray();

        return $this->respondWithData('PIC history loaded.', $records);
    }

    public function changePic($id = null)
    {
        $processId = $this->positiveId($id);
        if ($processId === null) {
            return $this->respondWithError('A valid process ID is required.', 400);
        }

        $payload = $this->request->getJSON(true);
        if (! is_array($payload)) {
            return $this->respondWithError('A JSON request body is required.', 400);
        }

        $supervisorId = $this->positiveId($payload['supervisor_id'] ?? null);
        if ($supervisorId === null) {
            return $this->respondWithError('supervisor_id must be a positive integer.', 422);
        }

        $db = db_connect();
        $process = $db->table('processes')
            ->select('id, status, current_supervisor_id')
            ->where('id', $processId)
            ->get()
            ->getRowArray();

        if ($process === null) {
            return $this->respondWithError('Process not found.', 404);
        }
        if ($process['status'] !== 'active') {
            return $this->respondWithError('The PIC can only be changed for an active process.', 409);
        }
        if ((int) $process['current_supervisor_id'] === $supervisorId) {
            return $this->respondWithError('The selected supervisor is already the current PIC.', 409);
        }
        if (! $this->userExistsWithRole($db, $supervisorId, 'supervisor')) {
            return $this->respondWithError('The selected supervisor is not active or does not have the supervisor role.', 422);
        }

        $now = date('Y-m-d H:i:s');
        $db->transBegin();

        try {
            $db->table('processes')
                ->where('id', $processId)
                ->where('status', 'active')
                ->update([
                    'current_supervisor_id' => $supervisorId,
                    'updated_at'            => $now,
                ]);

            if ($db->affectedRows() !== 1) {
                $db->transRollback();

                return $this->respondWithError('The process is no longer active.', 409);
            }

            $db->table('process_pic_history')
                ->where('process_id', $processId)
                ->where('ended_at', null)
                ->update(['ended_at' => $now]);

            $db->table('process_pic_history')->insert([
                'process_id'    => $processId,
                'supervisor_id' => $supervisorId,
                'started_at'    => $now,
                'created_at'    => $now,
            ]);

            if (! $db->transStatus()) {
                throw new \RuntimeException('The PIC change transaction could not be completed.');
            }
            $db->transCommit();
        } catch (Throwable $exception) {
            $db->transRollback();
            log_message('error', 'Changing process PIC failed: {message}', [
                'message' => $exception->getMessage(),
            ]);

            return $this->respondWithError('CI4 could not change the process PIC.', 500);
        }

        return $this->respond([
            'success' => true,
            'message' => 'Process PIC changed.',
            'data'    => [
                'process_id'    => $processId,
                'supervisor_id' => $supervisorId,
                'supervisor_name' => db_connect()->table('users')
                    ->select('name')
                    ->where('id', $supervisorId)
                    ->get()
                    ->getRow('name'),
                'started_at'    => $now,
            ],
        ]);
    }

    public function operatorHistory($id = null)
    {
        $processId = $this->positiveId($id);
        if ($processId === null) {
            return $this->respondWithError('A valid process ID is required.', 400);
        }

        $db = db_connect();
        if (! $db->table('processes')->where('id', $processId)->countAllResults()) {
            return $this->respondWithError('Process not found.', 404);
        }

        $records = $db->table('process_operators po')
            ->select('po.id, po.process_id, po.user_id, u.name AS operator_name, po.assigned_at, po.removed_at')
            ->join('users u', 'u.id = po.user_id')
            ->where('po.process_id', $processId)
            ->orderBy('po.assigned_at', 'DESC')
            ->orderBy('po.id', 'DESC')
            ->get()
            ->getResultArray();

        return $this->respondWithData('Operator assignment history loaded.', $records);
    }

    public function updateOperators($id = null)
    {
        $processId = $this->positiveId($id);
        if ($processId === null) {
            return $this->respondWithError('A valid process ID is required.', 400);
        }

        $payload = $this->request->getJSON(true);
        if (! is_array($payload)) {
            return $this->respondWithError('A JSON request body is required.', 400);
        }

        $operatorIds = $payload['operator_ids'] ?? null;
        if (! is_array($operatorIds) || ! array_is_list($operatorIds)) {
            return $this->respondWithError('operator_ids must be a list of user IDs.', 422);
        }

        $operatorIds = array_map(fn ($operatorId) => $this->positiveId($operatorId), $operatorIds);
        if (in_array(null, $operatorIds, true)) {
            return $this->respondWithError('Each operator ID must be a positive integer.', 422);
        }
        $operatorIds = array_values(array_unique($operatorIds));

        $db = db_connect();
        $process = $db->table('processes')
            ->select('id, status')
            ->where('id', $processId)
            ->get()
            ->getRowArray();

        if ($process === null) {
            return $this->respondWithError('Process not found.', 404);
        }
        if ($process['status'] !== 'active') {
            return $this->respondWithError('Operators can only be changed for active processes.', 409);
        }

        if ($operatorIds !== []) {
            $validOperatorCount = $db->table('users')
                ->whereIn('id', $operatorIds)
                ->where('role', 'operator')
                ->where('status', 'active')
                ->countAllResults();
            if ($validOperatorCount !== count($operatorIds)) {
                return $this->respondWithError('Every selected user must be an active operator.', 422);
            }
        }

        $currentRows = $db->table('process_operators')
            ->select('user_id')
            ->where('process_id', $processId)
            ->where('removed_at', null)
            ->get()
            ->getResultArray();
        $currentIds = array_map(static fn (array $row): int => (int) $row['user_id'], $currentRows);
        $toRemove = array_values(array_diff($currentIds, $operatorIds));
        $toAdd = array_values(array_diff($operatorIds, $currentIds));

        if ($toRemove === [] && $toAdd === []) {
            return $this->respond([
                'success' => true,
                'message' => 'Operator assignments are unchanged.',
                'data'    => ['process_id' => $processId, 'operator_ids' => $currentIds],
            ]);
        }

        $now = date('Y-m-d H:i:s');
        $db->transBegin();

        try {
            if ($toRemove !== []) {
                $db->table('process_operators')
                    ->where('process_id', $processId)
                    ->whereIn('user_id', $toRemove)
                    ->where('removed_at', null)
                    ->update(['removed_at' => $now]);
            }

            if ($toAdd !== []) {
                $rows = array_map(
                    static fn (int $operatorId): array => [
                        'process_id'  => $processId,
                        'user_id'     => $operatorId,
                        'assigned_at' => $now,
                        'created_at'  => $now,
                    ],
                    $toAdd,
                );
                $db->table('process_operators')->insertBatch($rows);
            }

            if (! $db->transStatus()) {
                throw new \RuntimeException('The operator update transaction could not be completed.');
            }
            $db->transCommit();
        } catch (Throwable $exception) {
            $db->transRollback();
            log_message('error', 'Updating process operators failed: {message}', [
                'message' => $exception->getMessage(),
            ]);

            return $this->respondWithError('CI4 could not update the process operators.', 500);
        }

        return $this->respond([
            'success' => true,
            'message' => 'Process operators updated.',
            'data'    => ['process_id' => $processId, 'operator_ids' => $operatorIds],
        ]);
    }

    public function start()
    {
        $payload = $this->request->getJSON(true);
        if (! is_array($payload)) {
            return $this->respondWithError('A JSON request body is required.', 400);
        }

        $machineId    = $this->positiveId($payload['machine_id'] ?? null);
        $sectorId     = $this->positiveId($payload['sector_id'] ?? null);
        $activityId   = $this->positiveId($payload['activity_id'] ?? null);
        $supervisorId = $this->positiveId($payload['current_supervisor_id'] ?? null);

        if ($machineId === null || $sectorId === null || $activityId === null || $supervisorId === null) {
            return $this->respondWithError(
                'machine_id, sector_id, activity_id, and current_supervisor_id must be positive integers.',
                422,
            );
        }

        $salesOrderId = null;
        if (isset($payload['sales_order_id']) && $payload['sales_order_id'] !== '') {
            $salesOrderId = $this->positiveId($payload['sales_order_id']);
            if ($salesOrderId === null) {
                return $this->respondWithError('sales_order_id must be a positive integer or null.', 422);
            }
        }

        $operatorIds = $payload['operator_ids'] ?? [];
        if (! is_array($operatorIds) || ! array_is_list($operatorIds)) {
            return $this->respondWithError('operator_ids must be a list of user IDs.', 422);
        }

        $operatorIds = array_map(fn ($id) => $this->positiveId($id), $operatorIds);
        if (in_array(null, $operatorIds, true)) {
            return $this->respondWithError('Each operator ID must be a positive integer.', 422);
        }
        $operatorIds = array_values(array_unique($operatorIds));

        $description = $payload['description'] ?? null;
        if ($description !== null && ! is_string($description)) {
            return $this->respondWithError('description must be a string or null.', 422);
        }
        $description = is_string($description) ? trim($description) : null;
        $description = $description === '' ? null : $description;

        $db = db_connect();

        if (! $this->activeRecordExists($db, 'machines', $machineId)) {
            return $this->respondWithError('The selected machine is not active or does not exist.', 422);
        }
        if (! $this->activeRecordExists($db, 'sectors', $sectorId)) {
            return $this->respondWithError('The selected sector is not active or does not exist.', 422);
        }
        if (! $db->table('activities')
            ->where('id', $activityId)
            ->where('sector_id', $sectorId)
            ->where('status', 'active')
            ->countAllResults()) {
            return $this->respondWithError('The selected activity is not active in the selected sector.', 422);
        }
        if (! $this->userExistsWithRole($db, $supervisorId, 'supervisor')) {
            return $this->respondWithError('The selected supervisor is not active or does not exist.', 422);
        }
        if ($salesOrderId !== null && ! $db->table('sales_orders')->where('id', $salesOrderId)->countAllResults()) {
            return $this->respondWithError('The selected sales order does not exist.', 422);
        }
        if ($operatorIds !== []) {
            $validOperatorCount = $db->table('users')
                ->whereIn('id', $operatorIds)
                ->where('role', 'operator')
                ->where('status', 'active')
                ->countAllResults();

            if ($validOperatorCount !== count($operatorIds)) {
                return $this->respondWithError('Every selected operator must be active and have the operator role.', 422);
            }
        }

        $now = date('Y-m-d H:i:s');
        $db->transBegin();

        try {
            $db->table('processes')->insert([
                'machine_id'            => $machineId,
                'sector_id'             => $sectorId,
                'activity_id'           => $activityId,
                'sales_order_id'        => $salesOrderId,
                'current_supervisor_id' => $supervisorId,
                'description'           => $description,
                'status'                => 'active',
                'started_at'            => $now,
                'created_at'            => $now,
                'updated_at'            => $now,
            ]);

            $processId = (int) $db->insertID();

            $db->table('process_pic_history')->insert([
                'process_id'    => $processId,
                'supervisor_id' => $supervisorId,
                'started_at'   => $now,
                'created_at'   => $now,
            ]);

            if ($operatorIds !== []) {
                $assignments = array_map(
                    static fn (int $userId): array => [
                        'process_id'  => $processId,
                        'user_id'     => $userId,
                        'assigned_at' => $now,
                        'created_at'  => $now,
                    ],
                    $operatorIds,
                );
                $db->table('process_operators')->insertBatch($assignments);
            }

            if (! $db->transStatus()) {
                throw new \RuntimeException('The process transaction could not be completed.');
            }

            $db->transCommit();
        } catch (Throwable $exception) {
            $db->transRollback();
            log_message('error', 'Process start failed: {message}', [
                'message' => $exception->getMessage(),
            ]);

            return $this->respondWithError('CI4 could not start the process.', 500);
        }

        return $this->respondCreated([
            'success' => true,
            'message' => 'Process started.',
            'data'    => [
                'id'                    => $processId,
                'machine_id'            => $machineId,
                'sector_id'             => $sectorId,
                'activity_id'           => $activityId,
                'sales_order_id'        => $salesOrderId,
                'current_supervisor_id' => $supervisorId,
                'operator_ids'          => $operatorIds,
                'description'           => $description,
                'status'                => 'active',
                'started_at'            => $now,
            ],
        ]);
    }

    public function end($id = null)
    {
        $processId = $this->positiveId($id);
        if ($processId === null) {
            return $this->respondWithError('A valid process ID is required.', 400);
        }

        $payload = $this->request->getJSON(true);
        if (! is_array($payload)) {
            return $this->respondWithError('A JSON request body is required.', 400);
        }

        $validQuantity = true;
        $qtyPassed = $this->optionalQuantity($payload, 'qty_passed', $validQuantity);
        $qtyRejected = $this->optionalQuantity($payload, 'qty_rejected', $validQuantity);
        if (! $validQuantity) {
            return $this->respondWithError(
                'qty_passed and qty_rejected must be unsigned integers or null.',
                422,
            );
        }

        $remarks = $payload['remarks'] ?? null;
        if ($remarks !== null && ! is_string($remarks)) {
            return $this->respondWithError('remarks must be a string or null.', 422);
        }
        $remarks = is_string($remarks) ? trim($remarks) : null;
        $remarks = $remarks === '' ? null : $remarks;

        $db = db_connect();
        $process = $db->table('processes')
            ->select('id, status')
            ->where('id', $processId)
            ->get()
            ->getRowArray();

        if ($process === null) {
            return $this->respondWithError('Process not found.', 404);
        }
        if ($process['status'] !== 'active') {
            return $this->respondWithError('Only active processes can be completed.', 409);
        }

        $now = date('Y-m-d H:i:s');
        $db->transBegin();

        try {
            $db->table('processes')
                ->where('id', $processId)
                ->where('status', 'active')
                ->update([
                    'status'       => 'completed',
                    'ended_at'     => $now,
                    'qty_passed'   => $qtyPassed,
                    'qty_rejected' => $qtyRejected,
                    'remarks'      => $remarks,
                    'updated_at'   => $now,
                ]);

            if ($db->affectedRows() !== 1) {
                $db->transRollback();

                return $this->respondWithError('The process is no longer active.', 409);
            }

            $db->table('process_operators')
                ->where('process_id', $processId)
                ->where('removed_at', null)
                ->update(['removed_at' => $now]);

            $db->table('process_pic_history')
                ->where('process_id', $processId)
                ->where('ended_at', null)
                ->update(['ended_at' => $now]);

            if (! $db->transStatus()) {
                throw new \RuntimeException('The process completion transaction could not be completed.');
            }

            $db->transCommit();
        } catch (Throwable $exception) {
            $db->transRollback();
            log_message('error', 'Process completion failed: {message}', [
                'message' => $exception->getMessage(),
            ]);

            return $this->respondWithError('CI4 could not complete the process.', 500);
        }

        $completed = $db->table('processes')
            ->select('id, status, ended_at, qty_passed, qty_rejected, remarks')
            ->where('id', $processId)
            ->get()
            ->getRowArray();

        return $this->respond([
            'success' => true,
            'message' => 'Process completed.',
            'data'    => $completed,
        ]);
    }

    private function positiveId($value): ?int
    {
        if (! is_int($value) && ! is_string($value)) {
            return null;
        }

        $id = filter_var($value, FILTER_VALIDATE_INT);

        return $id !== false && $id > 0 ? $id : null;
    }

    private function optionalQuantity(array $payload, string $field, bool &$valid): ?int
    {
        if (! array_key_exists($field, $payload) || $payload[$field] === null || $payload[$field] === '') {
            return null;
        }

        $value = $payload[$field];
        if (! is_int($value) && ! is_string($value)) {
            $valid = false;

            return null;
        }

        $quantity = filter_var($value, FILTER_VALIDATE_INT);
        if ($quantity === false || $quantity < 0 || $quantity > 4294967295) {
            $valid = false;

            return null;
        }

        return $quantity;
    }

    private function activeRecordExists($db, string $table, int $id): bool
    {
        return (bool) $db->table($table)
            ->where('id', $id)
            ->where('status', 'active')
            ->countAllResults();
    }

    private function userExistsWithRole($db, int $id, string $role): bool
    {
        return (bool) $db->table('users')
            ->where('id', $id)
            ->where('role', $role)
            ->where('status', 'active')
            ->countAllResults();
    }

    private function respondWithData(string $message, array $records)
    {
        return $this->respond([
            'success' => true,
            'message' => $message,
            'data'    => $records,
        ]);
    }

    private function respondWithError(string $message, int $status)
    {
        return $this->respond([
            'success' => false,
            'message' => $message,
        ], $status);
    }
}
