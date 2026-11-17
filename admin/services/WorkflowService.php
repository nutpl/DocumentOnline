<?php

final class WorkflowService
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function getDocumentTypes(): array
    {
        return $this->pdo->query(
            "SELECT document_type_id, document_code, document_name
             FROM document_type
             WHERE status = 'active'
             ORDER BY document_type_id"
        )->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getPositions(): array
    {
        return $this->pdo->query(
            "SELECT p.position_id, p.position_name,
                    GROUP_CONCAT(DISTINCT TRIM(CONCAT(
                        CASE WHEN pf.prefix_name IN ('นาย','นาง','นางสาว') THEN 'อาจารย์'
                             ELSE COALESCE(NULLIF(pf.prefix_short,''),NULLIF(pf.prefix_name,''),'') END,
                        ' ', e.firstname, ' ', e.lastname
                    )) ORDER BY e.firstname SEPARATOR ', ') AS holder_names
             FROM positions p
             LEFT JOIN employee_position ep ON ep.position_id = p.position_id
             LEFT JOIN employee e ON e.employee_id = ep.employee_id
             LEFT JOIN prefixes pf ON pf.prefix_id = e.prefix_id
             GROUP BY p.position_id, p.position_name
             ORDER BY p.position_name"
        )->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getOfficePositions(): array
    {
        $statement = $this->pdo->prepare(
            "SELECT position_id, position_name
             FROM positions
             WHERE position_name IN ('คณบดี', 'รองคณบดีฝ่ายวิชาการและวิจัย')
             ORDER BY FIELD(position_name, 'คณบดี', 'รองคณบดีฝ่ายวิชาการและวิจัย')"
        );
        $statement->execute();

        return $statement->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getEmployees(bool $teachersOnly = false): array
    {
        $typeCondition = $teachersOnly ? "AND e.employee_type = 'teacher'" : '';
        $statement = $this->pdo->query(
            "SELECT e.employee_id, e.employee_type, e.department_id,
                    CASE
                        WHEN p.prefix_name IN ('นาย', 'นาง', 'นางสาว') AND e.employee_type = 'teacher'
                            THEN 'อาจารย์'
                        ELSE COALESCE(NULLIF(p.prefix_short, ''), NULLIF(p.prefix_name, ''), '')
                    END AS display_prefix,
                    e.firstname, e.lastname, d.department_name
             FROM employee e
             INNER JOIN users u ON u.user_id = e.user_id AND u.status = 'active'
             LEFT JOIN prefixes p ON p.prefix_id = e.prefix_id
             LEFT JOIN department d ON d.department_id = e.department_id
             WHERE 1 = 1 {$typeCondition}
             ORDER BY e.employee_type, e.firstname, e.lastname"
        );

        return $statement->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getDepartments(): array
    {
        return $this->pdo->query(
            "SELECT d.department_id, d.department_name, f.faculty_name
             FROM department d
             INNER JOIN faculty f ON f.faculty_id = d.faculty_id
             ORDER BY f.faculty_name, d.department_name"
        )->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getActiveWorkflow(int $documentTypeId): ?array
    {
        $statement = $this->pdo->prepare(
            "SELECT rw.*, dt.document_code, dt.document_name
             FROM review_workflows rw
             INNER JOIN document_type dt ON dt.document_type_id = rw.document_type_id
             WHERE rw.document_type_id = ? AND rw.workflow_status = 'active'
             ORDER BY rw.version_no DESC
             LIMIT 1"
        );
        $statement->execute([$documentTypeId]);
        $workflow = $statement->fetch(PDO::FETCH_ASSOC);

        if (!$workflow) {
            return null;
        }

        $stepStatement = $this->pdo->prepare(
            "SELECT rws.*, p.position_name,
                    CASE
                        WHEN pf.prefix_name IN ('นาย', 'นาง', 'นางสาว') AND e.employee_type = 'teacher'
                            THEN 'อาจารย์'
                        ELSE COALESCE(NULLIF(pf.prefix_short, ''), NULLIF(pf.prefix_name, ''), '')
                    END AS employee_prefix,
                    e.firstname, e.lastname,
                    rws.reviewer_employee_id AS employee_id,
                    rws.reviewer_position_id AS position_id
             FROM review_workflow_steps rws
             LEFT JOIN positions p ON p.position_id = rws.reviewer_position_id
             LEFT JOIN employee e ON e.employee_id = rws.reviewer_employee_id
             LEFT JOIN prefixes pf ON pf.prefix_id = e.prefix_id
             WHERE rws.workflow_id = ?
             ORDER BY rws.step_order"
        );
        $stepStatement->execute([(int) $workflow['workflow_id']]);
        $workflow['steps'] = $stepStatement->fetchAll(PDO::FETCH_ASSOC);

        return $workflow;
    }

    /**
     * คำร้อง R ผ่านอาจารย์ที่ปรึกษา ส่วนคำร้องสหกิจผ่านอาจารย์ที่ปรึกษา
     * แล้วส่งต่อให้อาจารย์ประสานงานฝึกประสบการณ์วิชาชีพตามลำดับ
     */
    public function getSuggestedWorkflowSteps(int $documentTypeId): array
    {
        $typeStatement = $this->pdo->prepare(
            "SELECT REPLACE(UPPER(document_code), '.', '') FROM document_type WHERE document_type_id = ?"
        );
        $typeStatement->execute([$documentTypeId]);
        $code = (string) $typeStatement->fetchColumn();

        $positionIds = [];
        $positionStatement = $this->pdo->query(
            "SELECT position_id, position_name FROM positions
             WHERE position_name IN ('ประธานหลักสูตร', 'อาจารย์ประสานงานฝึกประสบการณ์วิชาชีพ')
                OR position_name LIKE 'หัวหน้าสาขา%'"
        );
        foreach ($positionStatement->fetchAll(PDO::FETCH_ASSOC) as $position) {
            if ($position['position_name'] === 'ประธานหลักสูตร') {
                $positionIds['curriculum_chair'] = (int) $position['position_id'];
            } elseif ($position['position_name'] === 'อาจารย์ประสานงานฝึกประสบการณ์วิชาชีพ') {
                $positionIds['internship_coordinator'] = (int) $position['position_id'];
            } elseif (str_starts_with($position['position_name'], 'หัวหน้าสาขา')) {
                $positionIds['department_head'] = (int) $position['position_id'];
            }
        }

        $advisor = static fn(): array => [
            'step_name' => 'อาจารย์ที่ปรึกษา',
            'reviewer_source' => 'student_advisor',
            'employee_id' => null,
            'position_id' => null,
            'reviewer_display' => 'ดึงอัตโนมัติตามนักศึกษา',
        ];
        $documentTeacher = static fn(): array => [
            'step_name' => 'อาจารย์ผู้รับผิดชอบรายวิชา',
            'reviewer_source' => 'document_teacher',
            'employee_id' => null,
            'position_id' => null,
            'reviewer_display' => 'ดึงอัตโนมัติจากอาจารย์ที่ระบุในคำร้อง',
        ];
        $requestAdvisor = static fn(): array => [
            'step_name' => 'อาจารย์ที่ปรึกษา',
            'reviewer_source' => 'document_teacher',
            'employee_id' => null,
            'position_id' => null,
            'reviewer_display' => 'ดึงอาจารย์ที่ปรึกษาที่ระบุในคำร้อง',
        ];
        $curriculumChair = static fn() => [
            'step_name' => 'ประธานหลักสูตร',
            'reviewer_source' => 'office_position',
            'employee_id' => null,
            'position_id' => $positionIds['curriculum_chair'] ?? null,
            'reviewer_display' => 'ดึงผู้ดำรงตำแหน่งประธานหลักสูตรของสาขา',
        ];
        $departmentHead = static fn() => [
            'step_name' => 'หัวหน้าสาขา',
            'reviewer_source' => 'office_position',
            'employee_id' => null,
            'position_id' => $positionIds['department_head'] ?? null,
            'reviewer_display' => 'ดึงผู้ดำรงตำแหน่งหัวหน้าสาขา',
        ];
        $internshipCoordinator = static fn() => [
            'step_name' => 'อาจารย์ประสานงานฝึกประสบการณ์วิชาชีพ',
            'reviewer_source' => 'office_position',
            'employee_id' => null,
            'position_id' => $positionIds['internship_coordinator'] ?? null,
            'reviewer_display' => 'อาจารย์ประสานงานฝึกประสบการณ์วิชาชีพ',
        ];

        if ($code === 'INTERNSHIP_INFO') {
            return [$advisor(), $internshipCoordinator()];
        }
        return in_array($code, ['R01', 'R12', 'R14', 'R18', 'R24'], true) ? [$advisor()] : [];
    }

    public function getWorkflowReadiness(): array
    {
        $items = [];
        foreach ($this->getDocumentTypes() as $type) {
            if (!preg_match('/^R\.?(01|12|14|18|24)$/i', (string) $type['document_code'])
                && strtoupper((string) $type['document_code']) !== 'INTERNSHIP_INFO') {
                continue;
            }
            $active = $this->getActiveWorkflow((int) $type['document_type_id']);
            $suggested = $this->getSuggestedWorkflowSteps((int) $type['document_type_id']);
            $items[] = [
                'document_type_id' => (int) $type['document_type_id'],
                'document_code' => (string) $type['document_code'],
                'document_name' => (string) $type['document_name'],
                'is_configured' => $active !== null,
                'version_no' => $active ? (int) $active['version_no'] : null,
                'step_count' => count($suggested),
                'step_names' => array_column($suggested, 'step_name'),
            ];
        }
        return $items;
    }

    public function saveWorkflow(int $documentTypeId, array $rawSteps, int $adminUserId): int
    {
        $typeStatement = $this->pdo->prepare(
            "SELECT document_code, document_name FROM document_type WHERE document_type_id = ? AND status = 'active'"
        );
        $typeStatement->execute([$documentTypeId]);
        $documentType = $typeStatement->fetch(PDO::FETCH_ASSOC);
        if (!$documentType) {
            throw new InvalidArgumentException('ไม่พบประเภทเอกสารที่เลือก');
        }

        $suggestedSteps = $this->getSuggestedWorkflowSteps($documentTypeId);
        if ($suggestedSteps) {
            $suggestedByKey = [];
            foreach ($suggestedSteps as $suggestedStep) {
                $key = $suggestedStep['reviewer_source'] . ':' . (int) ($suggestedStep['position_id'] ?? $suggestedStep['employee_id'] ?? 0);
                $suggestedByKey[$key] = $suggestedStep;
            }
            $orderedCanonicalSteps = [];
            foreach ($rawSteps as $rawStep) {
                $source = (string) ($rawStep['reviewer_source'] ?? '');
                $targetId = (int) ($rawStep['target_id'] ?? 0);
                $key = $source . ':' . $targetId;
                if (!isset($suggestedByKey[$key])) {
                    throw new InvalidArgumentException('รายการผู้เกี่ยวข้องไม่ตรงกับประเภทคำร้อง');
                }
                $canonical = $suggestedByKey[$key];
                $orderedCanonicalSteps[] = [
                    'step_name' => $canonical['step_name'],
                    'reviewer_source' => $canonical['reviewer_source'],
                    'target_id' => (int) ($canonical['position_id'] ?? $canonical['employee_id'] ?? 0),
                ];
                unset($suggestedByKey[$key]);
            }
            if ($suggestedByKey || count($orderedCanonicalSteps) !== count($suggestedSteps)) {
                throw new InvalidArgumentException('กรุณาจัดลำดับผู้เกี่ยวข้องให้ครบทุกบทบาท');
            }
            $rawSteps = $orderedCanonicalSteps;
        }

        $allowedSources = ['student_advisor', 'document_teacher', 'office_position', 'employee'];
        $steps = [];
        foreach ($rawSteps as $rawStep) {
            $stepName = trim((string) ($rawStep['step_name'] ?? ''));
            $source = (string) ($rawStep['reviewer_source'] ?? '');
            $targetId = (int) ($rawStep['target_id'] ?? 0);

            if ($stepName === '' || !in_array($source, $allowedSources, true)) {
                throw new InvalidArgumentException('กรุณาระบุชื่อขั้นตอนและประเภทผู้พิจารณาให้ครบ');
            }

            $employeeId = null;
            $positionId = null;
            $scopeType = 'department';
            if ($source === 'employee') {
                $employeeId = $targetId;
                $this->assertEmployeeExists($employeeId);
            } elseif ($source === 'office_position') {
                $positionId = $targetId;
                $positionName = $this->getPositionName($positionId);
                if (in_array($positionName, ['คณบดี', 'รองคณบดีฝ่ายวิชาการและวิจัย'], true)) {
                    $scopeType = 'faculty';
                }
            }

            $steps[] = [
                'step_name' => $stepName,
                'reviewer_source' => $source,
                'employee_id' => $employeeId,
                'position_id' => $positionId,
                'scope_type' => $scopeType,
            ];
        }

        if (!$steps) {
            throw new InvalidArgumentException('ต้องมีผู้พิจารณาอย่างน้อย 1 ขั้นตอน');
        }

        $this->pdo->beginTransaction();
        try {
            $versionStatement = $this->pdo->prepare(
                "SELECT COALESCE(MAX(version_no), 0) + 1
                 FROM review_workflows
                 WHERE document_type_id = ?
                 FOR UPDATE"
            );
            $versionStatement->execute([$documentTypeId]);
            $version = (int) $versionStatement->fetchColumn();

            $deactivate = $this->pdo->prepare(
                "UPDATE review_workflows SET workflow_status = 'inactive'
                 WHERE document_type_id = ? AND workflow_status = 'active'"
            );
            $deactivate->execute([$documentTypeId]);

            $workflowName = $documentType['document_code'] . ' - ' . $documentType['document_name'];
            $insertWorkflow = $this->pdo->prepare(
                "INSERT INTO review_workflows
                    (document_type_id, workflow_name, version_no, workflow_status, revision_return_policy, created_by)
                 VALUES (?, ?, ?, 'active', 'same_step', ?)"
            );
            $insertWorkflow->execute([$documentTypeId, $workflowName, $version, $adminUserId]);
            $workflowId = (int) $this->pdo->lastInsertId();

            $insertStep = $this->pdo->prepare(
                "INSERT INTO review_workflow_steps
                    (workflow_id, step_order, role_code, step_name, reviewer_source,
                     reviewer_employee_id, reviewer_position_id, scope_type, is_required)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, 1)"
            );
            foreach ($steps as $index => $step) {
                $order = $index + 1;
                $insertStep->execute([
                    $workflowId,
                    $order,
                    'step_' . $order,
                    $step['step_name'],
                    $step['reviewer_source'],
                    $step['employee_id'],
                    $step['position_id'],
                    $step['scope_type'],
                ]);
            }

            $this->pdo->commit();
            return $workflowId;
        } catch (Throwable $exception) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $exception;
        }
    }

    public function getCurrentOfficeHolders(): array
    {
        $statement = $this->pdo->query(
            "SELECT p.position_id, p.position_name, oh.office_holder_id, oh.effective_from,
                    oh.department_id, d.department_name, f.faculty_name, oh.holder_name
             FROM positions p
             LEFT JOIN office_holders oh
                ON oh.office_holder_id = (
                    SELECT oh2.office_holder_id
                    FROM office_holders oh2
                    WHERE oh2.position_id = p.position_id
                      AND oh2.is_active = 1
                      AND oh2.effective_from <= CURRENT_DATE
                      AND (oh2.effective_to IS NULL OR oh2.effective_to >= CURRENT_DATE)
                    ORDER BY oh2.effective_from DESC, oh2.office_holder_id DESC
                    LIMIT 1
                )
             LEFT JOIN department d ON d.department_id = oh.department_id
             LEFT JOIN faculty f ON f.faculty_id = d.faculty_id
             WHERE p.position_name IN ('คณบดี', 'รองคณบดีฝ่ายวิชาการและวิจัย')
             ORDER BY FIELD(p.position_name, 'คณบดี', 'รองคณบดีฝ่ายวิชาการและวิจัย')"
        );

        return $statement->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getOfficeHolderHistory(): array
    {
        return $this->pdo->query(
            "SELECT oh.*, p.position_name, d.department_name, f.faculty_name
             FROM office_holders oh
             INNER JOIN positions p ON p.position_id = oh.position_id
             LEFT JOIN department d ON d.department_id = oh.department_id
             LEFT JOIN faculty f ON f.faculty_id = d.faculty_id
             WHERE p.position_name IN ('คณบดี', 'รองคณบดีฝ่ายวิชาการและวิจัย')
             ORDER BY oh.effective_from DESC, oh.office_holder_id DESC"
        )->fetchAll(PDO::FETCH_ASSOC);
    }

    public function assignOfficeHolder(int $positionId, string $holderName, int $departmentId, int $adminUserId): int
    {
        $positionStatement = $this->pdo->prepare(
            "SELECT position_name FROM positions
             WHERE position_id = ? AND position_name IN ('คณบดี', 'รองคณบดีฝ่ายวิชาการและวิจัย')"
        );
        $positionStatement->execute([$positionId]);
        $positionName = $positionStatement->fetchColumn();
        if (!$positionName) {
            throw new InvalidArgumentException('ตำแหน่งที่เลือกไม่ถูกต้อง');
        }

        $holderName = trim($holderName);
        if ($holderName === '') {
            throw new InvalidArgumentException('กรุณากรอกชื่อผู้ดำรงตำแหน่ง');
        }
        $departmentStatement = $this->pdo->prepare("SELECT COUNT(*) FROM department WHERE department_id = ?");
        $departmentStatement->execute([$departmentId]);
        if (!(int) $departmentStatement->fetchColumn()) {
            throw new InvalidArgumentException('ไม่พบสาขาที่เลือก');
        }

        $this->pdo->beginTransaction();
        try {
            $currentStatement = $this->pdo->prepare(
                "SELECT office_holder_id, holder_name
                 FROM office_holders
                 WHERE position_id = ? AND department_id = ? AND is_active = 1
                 ORDER BY office_holder_id DESC
                 LIMIT 1 FOR UPDATE"
            );
            $currentStatement->execute([$positionId, $departmentId]);
            $current = $currentStatement->fetch(PDO::FETCH_ASSOC);
            if ($current && trim((string) $current['holder_name']) === $holderName) {
                throw new InvalidArgumentException('ชื่อนี้เป็นผู้ดำรงตำแหน่งปัจจุบันอยู่แล้ว');
            }

            $closeStatement = $this->pdo->prepare(
                "UPDATE office_holders
                 SET is_active = 0, effective_to = CURRENT_DATE
                 WHERE position_id = ? AND department_id = ? AND is_active = 1"
            );
            $closeStatement->execute([$positionId, $departmentId]);

            $insertStatement = $this->pdo->prepare(
                "INSERT INTO office_holders
                    (position_id, holder_name, department_id, effective_from, is_active,
                     signature_title_line1, signature_title_line2, created_by)
                 VALUES (?, ?, ?, CURRENT_DATE, 1, ?, ?, ?)"
            );
            $signatureTitleLine1 = $positionName === 'รองคณบดีฝ่ายวิชาการและวิจัย'
                ? 'รองคณบดีฝ่ายวิชาการและวิจัย ปฏิบัติราชการแทน'
                : 'คณบดีคณะวิศวกรรมศาสตร์และเทคโนโลยี';
            $signatureTitleLine2 = $positionName === 'รองคณบดีฝ่ายวิชาการและวิจัย'
                ? 'คณบดีคณะวิศวกรรมศาสตร์และเทคโนโลยี'
                : null;
            $insertStatement->execute([
                $positionId,
                $holderName,
                $departmentId,
                $signatureTitleLine1,
                $signatureTitleLine2,
                $adminUserId,
            ]);

            $officeHolderId = (int) $this->pdo->lastInsertId();

            $this->pdo->commit();
            return $officeHolderId;
        } catch (Throwable $exception) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $exception;
        }
    }

    private function assertEmployeeExists(int $employeeId, bool $teacherOnly = false): void
    {
        if ($employeeId < 1) {
            throw new InvalidArgumentException('กรุณาเลือกบุคลากร');
        }
        $typeCondition = $teacherOnly ? "AND e.employee_type = 'teacher'" : '';
        $statement = $this->pdo->prepare(
            "SELECT COUNT(*)
             FROM employee e
             INNER JOIN users u ON u.user_id = e.user_id AND u.status = 'active'
             WHERE e.employee_id = ? {$typeCondition}"
        );
        $statement->execute([$employeeId]);
        if (!(int) $statement->fetchColumn()) {
            throw new InvalidArgumentException('ไม่พบบุคลากรที่เลือกหรือบัญชีถูกปิดใช้งาน');
        }
    }

    private function getPositionName(int $positionId): string
    {
        if ($positionId < 1) {
            throw new InvalidArgumentException('กรุณาเลือกตำแหน่งผู้พิจารณา');
        }
        $statement = $this->pdo->prepare("SELECT position_name FROM positions WHERE position_id = ?");
        $statement->execute([$positionId]);
        $positionName = $statement->fetchColumn();
        if (!$positionName) {
            throw new InvalidArgumentException('ไม่พบตำแหน่งผู้พิจารณาที่เลือก');
        }
        return (string) $positionName;
    }
}
