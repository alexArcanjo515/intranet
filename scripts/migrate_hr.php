<?php
// Migration: HR base tables
// Usage: php scripts/migrate_hr.php

$pdo = require __DIR__ . '/../config/db.php';
$driver = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);

function execSql(PDO $pdo, string $sql){
  try { $pdo->exec($sql); } catch (Throwable $e) { /* ignore if already exists or unsupported */ }
}

if ($driver === 'mysql') {
  $pk = 'INT UNSIGNED AUTO_INCREMENT PRIMARY KEY';
  $id = 'INT UNSIGNED';
  $int = 'INT';
  $dt = 'DATETIME';
  $txt = 'TEXT';
  $str = 'VARCHAR(255)';
  $bool = 'TINYINT(1)';
} else { // sqlite (default)
  $pk = 'INTEGER PRIMARY KEY AUTOINCREMENT';
  $id = 'INTEGER';
  $int = 'INTEGER';
  $dt = 'TEXT';
  $txt = 'TEXT';
  $str = 'TEXT';
  $bool = 'INTEGER';
}

// Departments
execSql($pdo, "CREATE TABLE IF NOT EXISTS hr_departments (
  id $pk,
  name $str NOT NULL,
  parent_id $id NULL
)");
execSql($pdo, "CREATE INDEX IF NOT EXISTS idx_hr_departments_parent ON hr_departments(parent_id)");

// Roles catalog (positions)
execSql($pdo, "CREATE TABLE IF NOT EXISTS hr_roles_catalog (
  id $pk,
  name $str NOT NULL
)");

// Employees
execSql($pdo, "CREATE TABLE IF NOT EXISTS hr_employees (
  id $pk,
  user_id $id NULL,
  name $str,
  email $str,
  dept_id $id NULL,
  role_id $id NULL,
  manager_user_id $id NULL,
  status $str,
  hire_date $dt NULL,
  termination_date $dt NULL,
  salary_enc $txt NULL,
  doc_id_enc $txt NULL,
  pii_json_enc $txt NULL,
  created_at $dt DEFAULT CURRENT_TIMESTAMP
)");
execSql($pdo, "CREATE INDEX IF NOT EXISTS idx_hr_employees_dept ON hr_employees(dept_id)");
execSql($pdo, "CREATE INDEX IF NOT EXISTS idx_hr_employees_role ON hr_employees(role_id)");
execSql($pdo, "CREATE INDEX IF NOT EXISTS idx_hr_employees_manager ON hr_employees(manager_user_id)");
// Phone (contato básico) - tentativa idempotente
execSql($pdo, "ALTER TABLE hr_employees ADD COLUMN phone $str");

// HR documents (per employee)
execSql($pdo, "CREATE TABLE IF NOT EXISTS hr_documents (
  id $pk,
  employee_id $id NOT NULL,
  type $str,
  title $str,
  file_path $str,
  created_at $dt DEFAULT CURRENT_TIMESTAMP,
  accepted_at $dt NULL,
  accepted_by $id NULL
)");
execSql($pdo, "CREATE INDEX IF NOT EXISTS idx_hr_documents_emp ON hr_documents(employee_id)");

// Policies catalog
execSql($pdo, "CREATE TABLE IF NOT EXISTS hr_policies (
  id $pk,
  title $str NOT NULL,
  version $str,
  file_path $str,
  published_at $dt NULL
)");

// Policy accepts
execSql($pdo, "CREATE TABLE IF NOT EXISTS hr_policy_accepts (
  id $pk,
  policy_id $id NOT NULL,
  user_id $id NOT NULL,
  accepted_at $dt NOT NULL,
  ip $str
)");
execSql($pdo, "CREATE INDEX IF NOT EXISTS idx_hr_policy_accepts_pol ON hr_policy_accepts(policy_id)");
execSql($pdo, "CREATE INDEX IF NOT EXISTS idx_hr_policy_accepts_user ON hr_policy_accepts(user_id)");

// Requests
execSql($pdo, "CREATE TABLE IF NOT EXISTS hr_requests (
  id $pk,
  employee_id $id NOT NULL,
  type $str NOT NULL,
  status $str NOT NULL,
  data_json $txt,
  created_at $dt DEFAULT CURRENT_TIMESTAMP,
  approved_by_manager_at $dt NULL,
  approved_by_rh_at $dt NULL
)");
execSql($pdo, "CREATE INDEX IF NOT EXISTS idx_hr_requests_emp ON hr_requests(employee_id)");
execSql($pdo, "CREATE INDEX IF NOT EXISTS idx_hr_requests_status ON hr_requests(status)");
// Tornar employee_id anulável para permitir solicitações sem vínculo direto
execSql($pdo, "ALTER TABLE hr_requests ALTER COLUMN employee_id DROP NOT NULL");
execSql($pdo, "ALTER TABLE hr_requests MODIFY employee_id $id NULL");

// Campos para solicitações RH abertas por usuários do portal
execSql($pdo, "ALTER TABLE hr_requests ADD COLUMN requester_user_id $id NULL");
execSql($pdo, "ALTER TABLE hr_requests ADD COLUMN subject $str NULL");
execSql($pdo, "ALTER TABLE hr_requests ADD COLUMN requester_name $str NULL");
execSql($pdo, "CREATE INDEX IF NOT EXISTS idx_hr_requests_requester ON hr_requests(requester_user_id)");

// Comentários em solicitações RH
execSql($pdo, "CREATE TABLE IF NOT EXISTS hr_request_comments (
  id $pk,
  request_id $id NOT NULL,
  user_id $id NOT NULL,
  body $txt NOT NULL,
  created_at $dt DEFAULT CURRENT_TIMESTAMP
)");
execSql($pdo, "CREATE INDEX IF NOT EXISTS idx_hr_reqc_req ON hr_request_comments(request_id)");
execSql($pdo, "CREATE INDEX IF NOT EXISTS idx_hr_reqc_user ON hr_request_comments(user_id)");

// Audit log
execSql($pdo, "CREATE TABLE IF NOT EXISTS hr_audit_log (
  id $pk,
  user_id $id,
  action $str,
  entity $str,
  entity_id $id,
  meta_json $txt,
  ip $str,
  created_at $dt DEFAULT CURRENT_TIMESTAMP
)");
execSql($pdo, "CREATE INDEX IF NOT EXISTS idx_hr_audit_entity ON hr_audit_log(entity, entity_id)");
execSql($pdo, "CREATE INDEX IF NOT EXISTS idx_hr_audit_user ON hr_audit_log(user_id)");

// ===== Fase 1: PII e Salários =====
// PII por colaborador (dados criptografados)
execSql($pdo, "CREATE TABLE IF NOT EXISTS hr_employees_pii (
  id $pk,
  employee_id $id NOT NULL,
  pii_encrypted $txt NULL,
  updated_at $dt NULL,
  updated_by $id NULL
)");
execSql($pdo, "CREATE INDEX IF NOT EXISTS idx_hr_pii_emp ON hr_employees_pii(employee_id)");

// Salários por colaborador (valor criptografado + histórico)
execSql($pdo, "CREATE TABLE IF NOT EXISTS hr_salaries (
  id $pk,
  employee_id $id NOT NULL,
  amount_encrypted $txt NULL,
  currency $str,
  valid_from $dt NULL,
  valid_to $dt NULL,
  created_by $id NULL,
  created_at $dt DEFAULT CURRENT_TIMESTAMP
)");
execSql($pdo, "CREATE INDEX IF NOT EXISTS idx_hr_salaries_emp ON hr_salaries(employee_id)");

// Pagamentos mensais por colaborador
execSql($pdo, "CREATE TABLE IF NOT EXISTS hr_payments (
  id $pk,
  employee_id $id NOT NULL,
  year $int NOT NULL,
  month $int NOT NULL,
  status $str NOT NULL,
  paid_at $dt NULL,
  amount_encrypted $txt NULL,
  currency $str
)");
execSql($pdo, "CREATE INDEX IF NOT EXISTS idx_hr_pay_emp ON hr_payments(employee_id)");
execSql($pdo, "CREATE INDEX IF NOT EXISTS idx_hr_pay_year_month ON hr_payments(year, month)");
// updated_by em pagamentos
execSql($pdo, "ALTER TABLE hr_payments ADD COLUMN updated_by $id");

// Assiduidade / faltas
execSql($pdo, "CREATE TABLE IF NOT EXISTS hr_attendance (
  id $pk,
  employee_id $id NOT NULL,
  date $dt NOT NULL,
  status $str NOT NULL,
  note $txt
)");
execSql($pdo, "CREATE INDEX IF NOT EXISTS idx_hr_att_emp_date ON hr_attendance(employee_id, date)");

echo "HR base migration completed\n";
