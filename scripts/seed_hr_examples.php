<?php
// Seed optional: departamentos, cargos e um colaborador de teste
$pdo = require __DIR__ . '/../config/db.php';
$driver = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);

function ins(PDO $pdo, string $sql, array $p){ try{ $st=$pdo->prepare($sql); $st->execute($p);}catch(Throwable $e){} }

// departamentos
ins($pdo, 'INSERT INTO hr_departments(id,name,parent_id) VALUES(1, "Administração", NULL)', []);
ins($pdo, 'INSERT INTO hr_departments(id,name,parent_id) VALUES(2, "Tecnologia", NULL)', []);
ins($pdo, 'INSERT INTO hr_departments(id,name,parent_id) VALUES(3, "RH", 1)', []);

// cargos
ins($pdo, 'INSERT INTO hr_roles_catalog(id,name) VALUES(1, "Analista RH")', []);
ins($pdo, 'INSERT INTO hr_roles_catalog(id,name) VALUES(2, "Desenvolvedor")', []);
ins($pdo, 'INSERT INTO hr_roles_catalog(id,name) VALUES(3, "Gerente")', []);

// colaborador de teste (sem PII real)
ins($pdo, 'INSERT INTO hr_employees(id,user_id,name,email,dept_id,role_id,status) VALUES(1, NULL, "Colaborador Teste", "colab.teste@example.com", 3, 1, "active")', []);

echo "HR seed completed\n";
