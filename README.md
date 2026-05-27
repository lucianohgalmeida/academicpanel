# local_academicpanel

Plugin local Moodle para gestão de Painel Acadêmico — indicadores de cursos por programa e semestre, com visibilidade restrita a coordenadores.

## Requisitos

- Moodle **4.4.9+** (Build 20250718 ou superior)
- PHP **8.1+**
- Banco compatível: MySQL/MariaDB ou PostgreSQL

## Funcionalidades

- Dashboard com KPIs por programa/semestre: matriculados, aprovação, reprovação, engajamento, abandono, nunca acessaram.
- Gráficos: barras comparativas por disciplina + donuts de participação/resultado.
- Comparação entre semestres com delta visual.
- Drill-down por disciplina: lista de estudantes com nota final, status, último acesso, atividades feitas, engajamento.
- Export Excel (XLSX) por disciplina.
- Web Service REST: `local_academicpanel_get_programs` e `local_academicpanel_get_dashboard`.
- Scheduled task diária para gerar snapshots automaticamente.
- CLI export/import JSON para backup site-wide.

## Conceito de Engajamento

Engajado = acessou o curso E tem pelo menos um sinal de atividade:
- Atividade concluída (`course_modules_completion`)
- Ação create/update em logs do curso
- Curso concluído (`course_completions.timecompleted`)
- Nota lançada (atividade avaliada)

## Modelo de Permissões

| Capability | Quem |
|---|---|
| `local/academicpanel:manage` | Admin Moodle (manager archetype) |
| `local/academicpanel:viewall` | Admin Moodle (manager archetype) — vê todos programas |
| `local/academicpanel:viewassigned` | Role custom `acpanel_coordinator` — vê apenas programas associados |

A role `acpanel_coordinator` é criada automaticamente no install. Atribuição ao usuário ocorre via aba "Coordenadores" no painel.

## Instalação

1. Copiar pasta `academicpanel/` para `local/` do Moodle.
2. Acessar **Administração do Site → Notificações** → executar upgrade.
3. Habilitar Web Services REST se for usar API externa.

## Web Service REST

```bash
curl -X POST "https://moodle/webservice/rest/server.php" \
  -d "wstoken=TOKEN" \
  -d "wsfunction=local_academicpanel_get_dashboard" \
  -d "moodlewsrestformat=json" \
  -d "programid=1" \
  -d "semester=2026.1"
```

## CLI

```bash
# Gerar snapshots manualmente
php local/academicpanel/cli/generate_snapshots.php --programshortname=nutricao --semester=2026.1

# Backup configuração (JSON)
php local/academicpanel/cli/export.php --output=academicpanel-backup.json

# Restaurar configuração
php local/academicpanel/cli/import.php --input=academicpanel-backup.json

# Atribuir role acpanel_coordinator a coords legados
php local/academicpanel/cli/assign_coordinator_roles.php

# Seed dados demo (requer DEBUG_DEVELOPER ou --confirm=1)
php local/academicpanel/cli/seed_demo.php --reset=1 --confirm=1
```

## Privacidade (GDPR/LGPD)

Plugin implementa `\core_privacy\local\metadata\provider`, `plugin\provider` e `core_userlist_provider`. Coleta apenas associações de coordenadores em `local_acpanel_coord`.

## Licença

GNU GPL v3 ou superior.
