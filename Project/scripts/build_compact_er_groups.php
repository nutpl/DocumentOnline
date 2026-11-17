<?php
declare(strict_types=1);

/**
 * Rebuild presentation ER SQL groups from the authoritative current schema.
 * Each group keeps every attribute of its selected tables but only emits the
 * relationships needed to explain that group. Foreign keys are unnamed so
 * diagram tools do not show technical names such as fk_employee_user.
 */

$projectRoot = dirname(__DIR__);
$schemaFile = $projectRoot . '/ER_SCHEMA_CURRENT.sql';
$outputDirectory = $argv[1] ?? ($projectRoot . '/output/er_sql_groups_compact');
if (!is_dir($outputDirectory) && !mkdir($outputDirectory, 0777, true) && !is_dir($outputDirectory)) {
    throw new RuntimeException("Cannot create output directory: {$outputDirectory}");
}
$schema = file_get_contents($schemaFile);
if ($schema === false) {
    throw new RuntimeException('Cannot read ER_SCHEMA_CURRENT.sql');
}

$tables = [];
preg_match_all(
    '/CREATE TABLE(?: IF NOT EXISTS)?\s+`?([A-Za-z0-9_]+)`?\s*\(\R(.*?)\R\)\s*(ENGINE=.*?);/s',
    $schema,
    $matches,
    PREG_SET_ORDER
);
foreach ($matches as $match) {
    $tables[$match[1]] = ['body' => $match[2], 'suffix' => $match[3]];
}

/** Split a CREATE TABLE body at top-level commas without splitting ENUM values. */
function splitTableDefinitions(string $body): array
{
    $definitions = [];
    $start = 0;
    $depth = 0;
    $quote = '';
    $escaped = false;
    $length = strlen($body);
    for ($index = 0; $index < $length; $index++) {
        $character = $body[$index];
        if ($quote !== '') {
            if ($escaped) {
                $escaped = false;
            } elseif ($character === '\\') {
                $escaped = true;
            } elseif ($character === $quote) {
                $quote = '';
            }
            continue;
        }
        if ($character === "'" || $character === '"' || $character === '`') {
            $quote = $character;
        } elseif ($character === '(') {
            $depth++;
        } elseif ($character === ')') {
            $depth--;
        } elseif ($character === ',' && $depth === 0) {
            $definitions[] = trim(substr($body, $start, $index - $start));
            $start = $index + 1;
        }
    }
    $tail = trim(substr($body, $start));
    if ($tail !== '') $definitions[] = $tail;
    return $definitions;
}

$groups = [
    '01_organization.sql' => [
        'title' => 'Organization and people',
        'tables' => ['faculty', 'department', 'users', 'employee', 'group_class', 'student'],
        'relations' => [
            ['department', 'faculty_id', 'faculty', 'faculty_id'],
            ['employee', 'user_id', 'users', 'user_id'],
            ['employee', 'department_id', 'department', 'department_id'],
            ['group_class', 'department_id', 'department', 'department_id'],
            ['group_class', 'advisor_employee_id', 'employee', 'employee_id'],
            ['student', 'user_id', 'users', 'user_id'],
            ['student', 'group_id', 'group_class', 'group_id'],
        ],
    ],
    '02_accounts_roles.sql' => [
        'title' => 'Accounts and roles',
        'tables' => ['users', 'user_roles', 'password_reset_otp', 'notifications'],
        'relations' => [
            ['user_roles', 'user_id', 'users', 'user_id'],
            ['password_reset_otp', 'user_id', 'users', 'user_id'],
            ['notifications', 'recipient_user_id', 'users', 'user_id'],
        ],
    ],
    '03_templates_workflow.sql' => [
        'title' => 'Document templates and workflow',
        'notes' => [
            'The response form is registered as document_type INTERNSHIP_RESPONSE_FORM.',
            'Its approved PDF location is stored by the matching document_template row; the PDF binary remains a managed file asset.',
        ],
        'tables' => ['department', 'users', 'document_type', 'document_template', 'internship_official_template_setting', 'review_workflows', 'review_workflow_steps'],
        'relations' => [
            ['document_template', 'document_type_id', 'document_type', 'document_type_id'],
            ['internship_official_template_setting', 'department_id', 'department', 'department_id'],
            ['internship_official_template_setting', 'updated_by', 'users', 'user_id'],
            ['review_workflows', 'document_type_id', 'document_type', 'document_type_id'],
            ['review_workflow_steps', 'workflow_id', 'review_workflows', 'workflow_id'],
        ],
    ],
    '04_document_review.sql' => [
        'title' => 'Documents and review history',
        'tables' => ['users', 'documents', 'document_review_processes', 'document_review_step_instances', 'document_events'],
        'relations' => [
            ['document_review_processes', 'document_id', 'documents', 'document_id'],
            ['document_review_step_instances', 'process_id', 'document_review_processes', 'process_id'],
            ['document_review_step_instances', 'reviewer_user_id', 'users', 'user_id'],
            ['document_events', 'document_id', 'documents', 'document_id'],
            ['document_events', 'document_step_id', 'document_review_step_instances', 'document_step_id'],
            ['document_events', 'actor_user_id', 'users', 'user_id'],
        ],
    ],
    '05_request_forms.sql' => [
        'title' => 'Request form details',
        'tables' => ['documents', 'r01_request', 'r12_request', 'r14_request', 'r18_request', 'r24_request'],
        'relations' => [
            ['r01_request', 'document_id', 'documents', 'document_id'],
            ['r12_request', 'document_id', 'documents', 'document_id'],
            ['r14_request', 'document_id', 'documents', 'document_id'],
            ['r18_request', 'document_id', 'documents', 'document_id'],
            ['r24_request', 'document_id', 'documents', 'document_id'],
        ],
    ],
    '06_internship_application_response.sql' => [
        'title' => 'Internship application and response',
        'notes' => [
            'internship_response_document stores metadata and review state for the response letter uploaded back by a student.',
        ],
        'tables' => ['student', 'internship_calendar', 'internship_profile', 'internship_application', 'internship_response_document', 'internship_response_declaration'],
        'relations' => [
            ['internship_profile', 'student_id', 'student', 'student_id'],
            ['internship_profile', 'calendar_id', 'internship_calendar', 'calendar_id'],
            ['internship_profile', 'previous_profile_id', 'internship_profile', 'internship_profile_id'],
            ['internship_application', 'internship_profile_id', 'internship_profile', 'internship_profile_id'],
            ['internship_response_document', 'internship_profile_id', 'internship_profile', 'internship_profile_id'],
            ['internship_response_declaration', 'internship_profile_id', 'internship_profile', 'internship_profile_id'],
        ],
    ],
    '07_operations_official_documents.sql' => [
        'title' => 'Tracking, supervision and official documents',
        'notes' => [
            'A request official document plus its linked internship profiles supplies the data used to generate one response-form PDF per company.',
            'This generation rule is application logic, not a database foreign key; no artificial ER relationship is added.',
        ],
        'tables' => ['employee', 'internship_profile', 'internship_tracking', 'internship_supervision', 'internship_official_document', 'internship_official_student'],
        'relations' => [
            ['internship_profile', 'previous_profile_id', 'internship_profile', 'internship_profile_id'],
            ['internship_tracking', 'internship_profile_id', 'internship_profile', 'internship_profile_id'],
            ['internship_supervision', 'internship_profile_id', 'internship_profile', 'internship_profile_id'],
            ['internship_supervision', 'supervisor_employee_id', 'employee', 'employee_id'],
            ['internship_official_student', 'official_document_id', 'internship_official_document', 'official_document_id'],
            ['internship_official_student', 'internship_profile_id', 'internship_profile', 'internship_profile_id'],
        ],
    ],
];

foreach ($groups as $filename => $group) {
    $sql = "-- Compact ER Group: {$group['title']}\n";
    $sql .= "-- Full attributes from ER_SCHEMA_CURRENT.sql; diagram-only, do not execute as a migration.\n\n";
    foreach ($group['notes'] ?? [] as $note) {
        $sql .= '-- ' . $note . "\n";
    }
    if (!empty($group['notes'])) $sql .= "\n";
    $relationsByTable = [];
    foreach ($group['relations'] as $relation) $relationsByTable[$relation[0]][] = $relation;

    foreach ($group['tables'] as $tableName) {
        if (!isset($tables[$tableName])) throw new RuntimeException("Missing schema table: {$tableName}");
        $definitions = array_values(array_filter(
            splitTableDefinitions($tables[$tableName]['body']),
            static fn (string $definition): bool => !preg_match('/^(?:CONSTRAINT\b|FOREIGN\s+KEY\b|KEY\s+`?fk_)/i', ltrim($definition))
        ));
        foreach ($relationsByTable[$tableName] ?? [] as [, $column, $targetTable, $targetColumn]) {
            $definitions[] = "FOREIGN KEY (`{$column}`) REFERENCES `{$targetTable}` (`{$targetColumn}`)";
        }
        $sql .= "CREATE TABLE `{$tableName}` (\n  " . implode(",\n  ", $definitions) . "\n) {$tables[$tableName]['suffix']};\n\n";
    }
    file_put_contents($outputDirectory . '/' . $filename, $sql);
}

echo 'Built ' . count($groups) . " compact ER SQL files.\n";
