# Intranet Corporativa

Documentação oficial do sistema da Intranet: visão geral, arquitetura, módulos, APIs, banco de dados, segurança, operação e desenvolvimento.

## Sumário
- [Visão Geral](#visão-geral)
- [Arquitetura e Tecnologias](#arquitetura-e-tecnologias)
- [Módulos Principais](#módulos-principais)
- [Padrões de Segurança](#padrões-de-segurança)
- [Estrutura de Pastas](#estrutura-de-pastas)
- [Banco de Dados e Migrações](#banco-de-dados-e-migrações)
- [APIs e Endpoints](#apis-e-endpoints)
- [Configuração & Deploy Local](#configuração--deploy-local)
- [Fluxos Operacionais](#fluxos-operacionais)
- [Guia de Desenvolvimento](#guia-de-desenvolvimento)
- [Troubleshooting](#troubleshooting)
- [Capturas de Tela](#capturas-de-tela)
- [Diagrama de Arquitetura](#diagrama-de-arquitetura)
- [Autor e Créditos](#autor-e-créditos)

---

## Visão Geral
A Intranet centraliza comunicação, suporte e recursos corporativos para colaboradores:
- Notícias e comunicados corporativos.
- Documentos públicos (políticas, procedimentos, modelos).
- Helpdesk (tickets, categorias, SLA, atribuição, status e notificações).
- RH (solicitações, dossiês, políticas, pagamentos, assiduidade, publicação de notícias e documentos para o Portal).
- Notificações integradas (dropdown no Portal).

Usuários autenticados acessam via Portal; RBAC controla permissões por papéis e permissões agregadas.

## Arquitetura e Tecnologias
- Linguagem: PHP 8+ (PDO)
- Banco: MySQL ou SQLite (compatibilidade implementada)
- Front-end: Bootstrap 5.3 (tema dark), Bootstrap Icons, JavaScript vanilla (fetch + FormData)
- Sessões: nativas do PHP (cookies de sessão)
- Estilo visual: "glass"/neumorphism aplicado com utilitários Bootstrap
- Uploads públicos: pasta `/uploads/*`

## Módulos Principais
- Portal
  - Home (notícias recentes, notificações)
  - Notícias: `news.php`, `news_view.php`
  - Documentos: `documents.php`, `document_download.php`
- Helpdesk (`/portal/helpdesk`)
  - Criação, listagem e visão de tickets
  - Campos: prioridade, categoria, `sla_due_at`, atribuição, status
  - Notificações para criação/atribuição/atualizações
  - Respostas rápidas (canned) no comentário do ticket (escopo `helpdesk`)
- RH (`/portal/rh`)
  - PIN de segurança (step-up) com expiração
  - Colaboradores, solicitações (férias/atestados/reembolso), policies, salários, assiduidade
  - Publicação: `publish.php` para criar notícias (visíveis no Portal) e enviar documentos públicos
  - Comentários de solicitações com legibilidade no tema dark
  - Sem respostas rápidas em RH (por decisão de negócio)
- Notificações (`notifications` table e dropdown no Portal)
- Respostas rápidas (`canned_replies`), com escopos: `helpdesk`, `global` (API e UI)

## Padrões de Segurança
- CSRF: `csrf_token()` em todos POSTs sensíveis
- RBAC: sessão armazena `roles` e `perms` (permissões agregadas). Endpoints verificam ambos.
- PIN RH: variáveis de sessão `rh_pin_ok`, `rh_pin_last`; expiração 15 min
- Upload seguro: criação de diretórios específicos, nomes saneados, checagem de caminhos antes de apagar
- Headers de segurança: `includes/security_headers.php`

## Estrutura de Pastas
```
/var/www/html/intranet
├─ portal/
│  ├─ index.php, login.php, logout.php
│  ├─ includes/ (settings.php, security_headers.php)
│  ├─ config/db.php
│  ├─ news.php, news_view.php
│  ├─ documents.php, document_download.php
│  ├─ helpdesk/ (UI + helpdesk_api.php)
│  └─ rh/ (UI + rh_api.php + publish.php)
├─ uploads/
│  ├─ docs/ (documentos públicos)
│  ├─ hr_docs/ (documentos RH por colaborador)
│  └─ hr_policies/ (políticas internas RH)
├─ scripts/ (migrações)
│  ├─ migrate_helpdesk.php
│  └─ migrate_canned_replies.php
├─ roles.php, role_delete.php, users.php, user_roles.php
└─ assets/ (logotipo, CSS, etc.)
   └─ screenshots/ (imagens usadas no README)
```

## Banco de Dados e Migrações
- Rodar migrações conforme necessário:
  - `php scripts/migrate_helpdesk.php` (adiciona `category`, `sla_due_at` em `helpdesk_tickets`)
  - `php scripts/migrate_canned_replies.php` (cria `canned_replies`)
- Tabelas chave (exemplos):
  - `users`, `roles`, `permissions`, `user_roles`, `role_permissions`
  - `notifications`
  - `news`
  - `documents`
  - `helpdesk_tickets`, `helpdesk_comments`
  - `hr_employees`, `hr_requests`, `hr_documents`, `hr_policies`, `hr_payments`, `hr_attendance`, `hr_salaries`, `hr_employees_pii`

## APIs e Endpoints
- Helpdesk: `/portal/helpdesk/helpdesk_api.php`
  - `tickets_list`, `ticket_get`, `ticket_create`, `ticket_assign`, `ticket_status_update`, `users_list`, etc.
- RH: `/portal/rh/rh_api.php`
  - Employees: `employees_list`, `employee_get`, `employee_create`, `employee_update_basic`
  - Requests: `requests_list`, `request_get`, `request_update`, `request_comment_add`, `request_comments_list`
  - Policies: `policy_create`, `policy_update`, `policy_delete`
  - Pagamentos/Assiduidade/Salários: `payments_list|set`, `attendance_list|set`, `employee_salary_list|create|update`
  - Publicação (Portal): `news_create`, `public_doc_upload`
- Canned replies: `/portal/canned_api.php`
  - `list?scope=helpdesk|global`, `create`, `delete`
- Notificações: `/portal/notifications_api.php` (se aplicável)

Obs.: Todos os POSTs exigem `csrf` válido. RBAC checa `roles` e `perms` na sessão.

## Configuração & Deploy Local
1. Dependências do sistema (ex.):
   - PHP 8+, extensões pdo_mysql/pdo_sqlite, openssl, gd (conforme uso)
   - Servidor embutido para dev: `php -S 0.0.0.0:8000 -t /var/www/html/intranet`
2. Config de DB: `portal/config/db.php` retorna uma instância PDO válida.
3. Executar migrações necessárias (vide seção de migrações).
4. Acessar `http://localhost:8000/portal/login.php`.
5. Criar usuário admin e papéis/permissões via UI de Admin.

## Fluxos Operacionais
- Notícias (Portal):
  - RH publica em `RH → Publicar` (news_create) → aparece no Portal (home e listagem)
- Documentos (Portal):
  - RH envia em `RH → Publicar` (public_doc_upload) → aparece em `/portal/documents.php`
- Helpdesk:
  - Usuário cria ticket → equipe atribui, define status e SLA → comentários com respostas rápidas
- RH:
  - Solicitações são triadas e comentadas → sem canned responses → com PIN ativo

## Guia de Desenvolvimento
- Padrões
  - PHP com funções puras por endpoint; checagem de CSRF em POST
  - Idempotência em migrações e compatibilidade MySQL/SQLite
  - UI com Bootstrap 5 dark + ícones
- Estilo de código
  - Sem comentários automáticos extensos; nomes claros de variáveis
  - JS com `fetch` e `FormData`; evitar libs externas
- Boas práticas
  - Sanitização mínima de HTML em conteúdo rico (notícias) sob responsabilidade de admins
  - Não expor caminhos internos; validar extensões e tamanhos de upload conforme política

## Troubleshooting
- Não vejo permissões aplicadas no RH
  - Faça logout/login para recarregar `roles` e `perms` na sessão
  - Verifique se o papel concede permissões corretas; RH verifica `roles` e `perms`
- Não consigo excluir papel
  - Se houver usuários vinculados (user_roles), exclusão é bloqueada. Remova vínculos antes.
- Erro 500 em `canned_api.php`
  - Verifique paths de includes (`../config/db.php`) e existência de `includes/security_headers.php`
- Upload falha
  - Verifique permissões de pasta em `/uploads/*`

## Capturas de Tela
- Portal (Home): `assets/screenshots/portal-home.png`
- Helpdesk (Tickets): `assets/screenshots/helpdesk-tickets.png`
- RH (Inbox Solicitações): `assets/screenshots/rh-inbox.png`
- RH (Publicar): `assets/screenshots/rh-publish.png`

Para adicionar as imagens, coloque-as na pasta `assets/screenshots/` com os nomes acima e elas serão referenciadas aqui. Exemplos de embedded (Git viewers):

![Portal Home](assets/screenshots/portal-home.png)
![Helpdesk Tickets](assets/screenshots/helpdesk-tickets.png)
![RH Inbox](assets/screenshots/rh-inbox.png)
![RH Publicar](assets/screenshots/rh-publish.png)

## Diagrama de Arquitetura
Fluxo simplificado (ASCII):

```
[Browser]
   |
   v
[Portal UI (PHP + Bootstrap)] -- links --> [Helpdesk UI] / [RH UI]
   |                                          |            |
   | AJAX (fetch)                             | AJAX       | AJAX
   v                                          v            v
[APIs PHP (portal/helpdesk/helpdesk_api.php, portal/rh/rh_api.php, portal/canned_api.php)]
   |
   v
[PDO] <--> [MySQL/SQLite]

Uploads públicos -> /uploads/docs, /uploads/hr_docs, /uploads/hr_policies
Sessão -> $_SESSION[portal_user] com roles + perms
```

## Autor e Créditos
- Sistema desenvolvido por: Alexio António DOmingos Mango
- Contato: alexio.mango@outlook.com
- Organização: nenhuma
- Licença: Proprietária

Créditos de tecnologia: Bootstrap, Bootstrap Icons, PHP, PDO. Logotipo/ativos pertencem à organização.
