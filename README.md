# local_academicpanel — Painel Acadêmico

Plugin local Moodle para gestão de indicadores acadêmicos por programa e semestre, com visibilidade restrita a coordenadores.

[![Moodle](https://img.shields.io/badge/Moodle-4.4.9%2B-orange)](https://moodle.org)
[![PHP](https://img.shields.io/badge/PHP-8.1%2B-blue)](https://www.php.net)
[![License](https://img.shields.io/badge/license-GPL%20v3-green)](https://www.gnu.org/licenses/gpl-3.0.html)

## Sumário

- [Visão geral](#visão-geral)
- [Requisitos](#requisitos)
- [Funcionalidades](#funcionalidades)
- [Instalação](#instalação)
- [Modelo de permissões](#modelo-de-permissões)
- [Conceito de engajamento](#conceito-de-engajamento)
- [Estrutura do plugin](#estrutura-do-plugin)
- [Web Service REST](#web-service-rest)
- [CLI](#cli)
- [Privacidade e LGPD/GDPR](#privacidade-e-lgpdgdpr)
- [Testes](#testes)
- [Troubleshooting](#troubleshooting)
- [Roadmap](#roadmap)
- [Contribuindo](#contribuindo)
- [Licença](#licença)

---

## Visão geral

`local_academicpanel` agrega métricas de desempenho acadêmico de cursos Moodle organizados em programas (graduações, pós, etc.) e semestres. Coordenadores enxergam apenas seus programas. Administradores veem todos.

**Indicadores monitorados:**
- Matriculados, aprovados, reprovados, sem nota.
- Aprovação entre avaliados e sobre inscritos.
- Taxa de engajamento, taxa de abandono, taxa de não acesso.
- Comparação delta entre semestres.

## Requisitos

| Item | Versão |
|---|---|
| Moodle | **4.4.9+** (Build 20250718 ou superior) |
| PHP | **8.1+** |
| Banco | MySQL/MariaDB ou PostgreSQL |

## Funcionalidades

- **Dashboard** com cards de KPI + gráficos (barras comparativas + donuts).
- **Filtros** por programa + semestre, com comparação opcional entre períodos (delta visual ±%).
- **Drill-down por disciplina**: tabela de estudantes com nota final, status (aprovado/reprovado/sem nota), último acesso, atividades feitas, engajamento.
- **Export XLSX** por disciplina (nativo via `MoodleExcelWorkbook`).
- **Web Service REST**: `local_academicpanel_get_programs` e `local_academicpanel_get_dashboard`.
- **Scheduled task** diária (03:00) para gerar snapshots automaticamente.
- **CLI export/import JSON** para backup site-wide.
- **Eventos auditáveis**: criação/atualização de programa, mapeamento, coordenador.
- **Role custom** `acpanel_coordinator` com visibilidade restrita.
- **Privacy provider** completo (GDPR/LGPD).

## Instalação

1. Copiar pasta `academicpanel/` para `local/` do Moodle:
   ```bash
   cd /path/to/moodle/local
   git clone https://github.com/lucianohgalmeida/academicpanel.git
   ```
2. Acessar **Administração do Site → Notificações** → executar upgrade.
3. (Opcional) Habilitar **Web Services REST** em Administração do Site → Recursos Avançados → Funcionalidades avançadas.

## Modelo de permissões

| Capability | Atribuída a | Acesso |
|---|---|---|
| `local/academicpanel:manage` | Manager Moodle | Gerenciar programas, mapeamentos, coordenadores e regras |
| `local/academicpanel:viewall` | Manager Moodle | Ver todos os programas no painel |
| `local/academicpanel:viewassigned` | Role `acpanel_coordinator` | Ver apenas programas em que o usuário está associado como coordenador |

A role `acpanel_coordinator` é criada automaticamente no install. Atribuição ao usuário ocorre na aba **Coordenadores** do painel.

## Conceito de engajamento

Engajado = **acessou o curso** E tem pelo menos um dos seguintes sinais:

1. Atividade concluída (`course_modules_completion`)
2. Ação create/update em logs do curso (`logstore_standard_log`)
3. Curso concluído (`course_completions.timecompleted`)
4. Nota lançada (`grade_grades.finalgrade IS NOT NULL`)

Classificação resultante:
- **Engajado**: acessou + qualquer sinal acima.
- **Abandonou**: acessou mas sem sinais.
- **Nunca acessou**: sem registro em `user_lastaccess`.

## Estrutura do plugin

```
local/academicpanel/
├── amd/
│   ├── src/dashboard.js        # ES6 source
│   └── build/dashboard.min.js  # AMD build
├── classes/
│   ├── event/                  # Eventos Moodle (program/coordinator/mapping)
│   ├── external/               # Web Service API
│   ├── form/                   # Moodle Forms
│   ├── local/                  # Repositórios e serviços
│   ├── privacy/provider.php    # GDPR/LGPD
│   └── task/                   # Scheduled tasks
├── cli/                        # Scripts CLI (export, import, seed, snapshots)
├── db/
│   ├── access.php              # Capabilities
│   ├── install.php             # Install hook (role creation)
│   ├── install.xml             # Schema XMLDB
│   ├── services.php            # Web Services
│   ├── tasks.php               # Scheduled tasks
│   └── upgrade.php             # Migrations idempotentes
├── lang/{en,pt_br}/            # Strings
├── tests/local/                # PHPUnit
├── course_detail.php           # Drill-down por disciplina
├── index.php                   # Dashboard principal
├── manage.php                  # CRUD em 3 abas
├── rules.php                   # Regra global de cálculo
└── version.php
```

### Tabelas de banco

| Tabela | Conteúdo |
|---|---|
| `local_acpanel_program` | Programas acadêmicos |
| `local_acpanel_category` | Mapeamento programa ↔ categoria Moodle ↔ semestre |
| `local_acpanel_coord` | Associação coordenador ↔ programa |
| `local_acpanel_rule` | Regra de cálculo (nota corte, roles, fallback) |
| `local_acpanel_snapshot` | Cache de métricas por programa/semestre/curso |
| `local_acpanel_seed` | Marcador de dados de seed (limpável) |

## Web Service REST

### Habilitar

1. Site Administration → Advanced features → Enable web services.
2. Site Administration → Server → Web services → Protocols → REST enabled.
3. External services → adicionar usuário autorizado em "Academic Panel Service".
4. Manage tokens → gerar token.

### `local_academicpanel_get_programs`

Lista programas visíveis ao usuário autenticado.

```bash
curl -X POST "https://moodle/webservice/rest/server.php" \
  -d "wstoken=TOKEN" \
  -d "wsfunction=local_academicpanel_get_programs" \
  -d "moodlewsrestformat=json"
```

### `local_academicpanel_get_dashboard`

Retorna agregado + métricas por curso para `(programid, semester)`.

```bash
curl -X POST "https://moodle/webservice/rest/server.php" \
  -d "wstoken=TOKEN" \
  -d "wsfunction=local_academicpanel_get_dashboard" \
  -d "moodlewsrestformat=json" \
  -d "programid=1" \
  -d "semester=2026.1"
```

Resposta inclui `summary` (agregado do programa) e `courses[]` (por disciplina).

## CLI

### Gerar snapshots manualmente

```bash
php local/academicpanel/cli/generate_snapshots.php \
    --programshortname=nutricao \
    --semester=2026.1
```

### Backup/export (JSON)

```bash
# Exportar tudo
php local/academicpanel/cli/export.php --output=academicpanel-backup.json

# Restaurar
php local/academicpanel/cli/import.php --input=academicpanel-backup.json
```

Exporta: programas, mapeamentos, coordenadores, regras. Não exporta snapshots (regeneráveis) nem seed records.

### Atribuir role a coordenadores legados

```bash
php local/academicpanel/cli/assign_coordinator_roles.php
```

Útil após upgrade quando há coordenadores cadastrados antes da role `acpanel_coordinator` existir.

### Seed dados demo

```bash
# Requer DEBUG_DEVELOPER habilitado OU --confirm=1
php local/academicpanel/cli/seed_demo.php --reset=1 --confirm=1
```

Cria 2 programas (Nutrição, Fisioterapia), 1 coordenador, 12 estudantes e cursos em 2 semestres (2025.2 e 2026.1).

## Privacidade e LGPD/GDPR

Plugin implementa:

- `\core_privacy\local\metadata\provider` — declara coleta de `userid` em `local_acpanel_coord`.
- `\core_privacy\local\request\plugin\provider` — export e delete por usuário.
- `\core_privacy\local\request\core_userlist_provider` — listagem e delete em lote.

Verificável em **Administração do Site → Usuários → Privacidade e políticas → Registro de privacidade do plugin**.

## Testes

### PHPUnit

```bash
# Setup (uma vez)
php admin/tool/phpunit/cli/init.php

# Rodar testes do plugin
vendor/bin/phpunit --filter indicator_calculator_test
```

Cobre: cálculo de métricas, merge de agregados, valores limite da nota de corte.

### Validação manual via Playwright

Para validar capability matrix (admin vs coordenador), testes de IDOR e UI.

## Troubleshooting

| Sintoma | Causa | Solução |
|---|---|---|
| Coord vê "Nenhum programa atribuído" mesmo associado | Role `acpanel_coordinator` não foi atribuída ao usuário | `php cli/assign_coordinator_roles.php` |
| Dashboard sem dados após mudar regra | Snapshots em cache | Clicar Salvar em Gerenciar regras (invalida snapshots) OU aguardar scheduled task |
| Export XLSX baixa HTML | Sessão expirada — recebeu login page | Re-autenticar e tentar de novo |
| "Plugin não aparece em Notifications" | `version.php` ausente/com erro | Validar sintaxe + `$plugin->component` correto |
| Erro `core_external\external_api not found` | Moodle 4.1 (sem o namespace) | Plugin requer Moodle 4.4.9+ |

## Roadmap

- [ ] Reativação de programa via UI (atualmente requer mudar shortname e recriar)
- [ ] Backup nativo Moodle (`backup/moodle2/`)
- [ ] Renderer/templates Mustache (extrair HTML inline de `index.php`)
- [ ] Behat tests da capability matrix
- [ ] Cobertura PHPUnit dos repositórios

## Contribuindo

1. Fork e clone.
2. Branch `feature/xxx`.
3. PR com descrição do contexto + screenshots se UI.
4. Lint PHP obrigatório: `find . -name "*.php" -exec php -l {} \;`

## Licença

GNU GPL v3 ou superior. Consistente com Moodle core.

---

**Autor:** Luciano Henrique Gomes de Almeida (lucianohgalmeida@gmail.com)
