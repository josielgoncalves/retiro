# Modelo de dados

PostgreSQL 16. Todas as migrations estão em `apps/api/database/migrations/`. Valores monetários são sempre `BIGINT` em centavos.

## Tabelas

### `retreats`

Além dos dados gerais do evento, concentra a identidade e os canais financeiros: `cover_image_path` (caminho relativo no storage privado), `pix_key_type`, `pix_key`, `pix_beneficiary_name` e `receipt_whatsapp`. O banco não armazena Base64 nem URLs de ambiente.

### `users`
Administradores do painel. `role` (`admin`|`operator`, `CHECK`), `active` (boolean — desativar um usuário bloqueia login **e** invalida sessões já abertas, via middleware `active`).

### `retreats`
Um retiro. `status` (`draft`|`active`|`finished`|`archived`, `CHECK`), `CHECK (end_date >= start_date)`.

### `couples`
Dados do casal. `notes` é administrativo — nunca exposto no portal público. Sem CPF ou outros documentos (fora do escopo, por decisão explícita).

### `enrollments`
Inscrição de um casal em um retiro. `UNIQUE(retreat_id, couple_id)` — um casal não pode se inscrever duas vezes no mesmo retiro. `payment_mode` (`fixed`|`flexible`), `status` (`active`|`cancelled`, cancelamento preserva histórico). `total_amount_cents > 0` (`CHECK`).

### `installments`
Parcelas de uma inscrição **fixa** (inscrições flexíveis não têm linhas aqui). `UNIQUE(enrollment_id, installment_number)`. Não tem coluna `status`: o status (`pending`/`partial`/`paid`/`overdue`) é sempre calculado em runtime a partir de `amount_cents`, das alocações ativas e de `due_date` — nunca armazenado, para nunca dessincronizar.

### `payments`
Um pagamento recebido. `paid_at` é a data informada pelo admin (pode ser retroativa); `created_at` é o instante real do lançamento no sistema — os dois são propositalmente campos distintos. Estorno não apaga a linha: `reversed_at`/`reversed_by`/`reversal_reason` são preenchidos, e um `CHECK` garante que `reversed_at` e `reversed_by` sejam ambos nulos ou ambos preenchidos (todo estorno tem um responsável).

### `payment_allocations`
Como um pagamento se distribui entre parcelas (modalidade fixa). `UNIQUE(payment_id, installment_id)` — um pagamento não aloca duas vezes na mesma parcela. Inscrições flexíveis nunca têm linhas aqui.

### `portal_access_tokens`
Apenas o hash SHA-256 do token é armazenado (`token_hash`, `UNIQUE`) — nunca o valor em texto puro. `revoked_at`/`expires_at`/`last_access_at` controlam validade. Ver `docs/SECURITY.md`.

### `announcements`
Avisos por retiro. Visível no portal só quando `published_at` não é nulo, já passou, e (`visible_until` é nulo ou ainda não passou).

### `audit_logs`
Log append-only (sem `updated_at`) de ações sensíveis: criação/estorno de pagamento, criação/atualização de usuário, regeneração/revogação de link, publicação de aviso. `before_data`/`after_data` em `JSONB`.

## Índices e integridade

Toda FK tem `ON DELETE` explícito:
- `RESTRICT` onde a exclusão não deveria ser possível via aplicação de qualquer forma (`enrollments.retreat_id/couple_id`, `payments.enrollment_id`, `payment_allocations.installment_id`) — a API não expõe `DELETE` para essas entidades.
- `CASCADE` onde faz sentido limpar dados dependentes de um registro raiz removido manualmente no banco (`installments`, `payment_allocations.payment_id`, `portal_access_tokens`, `announcements`).
- `SET NULL` para referências a `users` em registros que devem sobreviver à remoção do usuário (`payments.created_by/reversed_by`, `announcements.created_by`, `audit_logs.user_id`).

Índices adicionais: `retreats(status)`, `retreats(start_date, end_date)`, `couples(partner_one_name)`, `couples(partner_two_name)`, `enrollments(couple_id)`, `enrollments(status)`, `installments(due_date)`, `payments(enrollment_id)`, `payments(paid_at)`, `payments(reversed_at)`, `payment_allocations(installment_id)`, `portal_access_tokens(enrollment_id)`, `announcements(retreat_id)`, `announcements(published_at)`, `audit_logs(entity_type, entity_id)`, `audit_logs(action)`.
