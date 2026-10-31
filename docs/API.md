# API REST

Base path: `/api/v1`. Todas as respostas são JSON. Rotas autenticadas usam Laravel Sanctum em modo SPA (cookie de sessão + CSRF) — veja `docs/SECURITY.md`.

## Autenticação

| Método | Rota | Descrição |
|---|---|---|
| GET | `/sanctum/csrf-cookie` | Obtém o cookie CSRF (chamar antes do login) |
| POST | `/auth/login` | `{ email, password }` → define a sessão. Rate limit: 5/min por IP+e-mail |
| POST | `/auth/logout` | Invalida a sessão |
| GET | `/auth/me` | Usuário autenticado atual |

## Retiros

Criação e edição aceitam `multipart/form-data`. Além dos dados gerais, o contrato inclui:

- `cover_image`: JPG, PNG ou WebP, máximo de 5 MB;
- `remove_cover_image`: booleano disponível na edição;
- `pix_key_type`: `cpf|cnpj|email|phone|random`;
- `pix_key`, `pix_beneficiary_name` e `receipt_whatsapp`.

Somente administradores podem criar ou alterar retiros. A resposta fornece `cover_image_url`, nunca o caminho interno armazenado. A capa é lida por `GET /public/retreats/{id}/cover`.

| Método | Rota | Observações |
|---|---|---|
| GET | `/retreats` | Paginado, `?per_page=` |
| POST | `/retreats` | |
| GET | `/retreats/{id}` | |
| PATCH | `/retreats/{id}` | |

## Casais

| Método | Rota | Observações |
|---|---|---|
| GET | `/couples` | `?search=`, `?retreat_id=`, `?payment_mode=`, `?financial_status=paid|pending`, `?sort=`, `?direction=`, paginado |
| POST | `/couples` | |
| GET | `/couples/{id}` | |
| PATCH | `/couples/{id}` | |

## Inscrições

| Método | Rota | Observações |
|---|---|---|
| GET | `/enrollments` | `?retreat_id=`, `?couple_id=`, `?status=` |
| POST | `/enrollments` | Ver payload abaixo |
| GET | `/enrollments/{id}` | Inclui parcelas, pagamentos, totais calculados |
| PATCH | `/enrollments/{id}` | Bloqueado se já houver pagamentos e a alteração envolver `payment_mode`/`total_amount_cents` |
| POST | `/enrollments/{id}/cancel` | Preserva histórico financeiro |
| POST | `/enrollments/{id}/installments/regenerate` | Prévia/confirmação da nova quantidade e dos vencimentos |

Payload de criação (modalidade fixa):
```json
{
  "retreat_id": 1,
  "couple_id": 5,
  "payment_mode": "fixed",
  "total_amount_cents": 90000,
  "installments": [
    { "due_date": "2027-01-10" },
    { "due_date": "2027-02-10" },
    { "due_date": "2027-03-10" }
  ]
}
```
Modalidade flexível: omitir `installments`.

Para regenerar parcelas de uma inscrição fixa, envie primeiro `confirm: false` para conferir a prévia e repita com `confirm: true` para aplicar:

```json
{
  "installment_count": 8,
  "first_due_date": "2027-01-10",
  "periodicity": "monthly",
  "confirm": false
}
```

`installment_count` é a quantidade total final. Parcelas que possuem histórico de alocação de pagamento são preservadas e contabilizadas nesse total; o saldo restante é redistribuído entre as demais parcelas sem perda de centavos.

## Pagamentos

| Método | Rota | Observações |
|---|---|---|
| GET | `/enrollments/{id}/payments` | Histórico (inclui estornados, marcados) |
| POST | `/enrollments/{id}/payments` | `{ amount_cents, paid_at, payment_method, reference?, notes?, installment_ids? }` |
| POST | `/payments/{id}/reverse` | `{ reason }` — estorno, não exclusão |

`payment_method`: `pix` \| `cash` \| `transfer` \| `other`.

## Link privado do portal

| Método | Rota | Observações |
|---|---|---|
| GET | `/enrollments/{id}/access-link` | Metadados só (**nunca** o token em texto puro) |
| POST | `/enrollments/{id}/access-link/regenerate` | Revoga o link anterior e retorna o novo token em texto puro **uma única vez** |
| POST | `/enrollments/{id}/access-link/revoke` | Revoga sem gerar um novo |

## Avisos

| Método | Rota |
|---|---|
| GET, POST | `/announcements` (`?retreat_id=`) |
| GET, PATCH | `/announcements/{id}` |
| POST | `/announcements/{id}/publish` |
| POST | `/announcements/{id}/unpublish` |

## Dashboard e relatórios

| Método | Rota | Observações |
|---|---|---|
| GET | `/dashboard?retreat_id=` | **Obrigatório** informar o retiro — nunca mistura dados entre retiros |
| GET | `/reports/financial?retreat_id=` | Lista de inscrições + parcelas vencidas |
| GET | `/reports/financial/export?retreat_id=` | CSV (UTF-8 com BOM, separador `;`, protegido contra CSV injection), sem tokens de acesso |

## Usuários administrativos (somente `admin`)

| Método | Rota |
|---|---|
| GET, POST | `/users` |
| PATCH | `/users/{id}` |

## Rotas públicas (portal do casal)

Sem autenticação Sanctum — autenticadas pelo token na própria URL. Rate limit: 30/min por IP. Headers de resposta: `Referrer-Policy: no-referrer`, `Cache-Control: no-store`, `X-Robots-Tag: noindex, nofollow`.

| Método | Rota |
|---|---|
| GET | `/public/portal/{token}` |
| GET | `/public/portal/{token}/announcements` |

Token inválido, revogado ou expirado: sempre `404` com a mesma mensagem genérica (as três situações são indistinguíveis pela resposta).

## Erros

Formato padrão Laravel: `422` para validação (`{ message, errors: { campo: [mensagens] } }`), `401` não autenticado, `403` não autorizado, `404` não encontrado, `429` rate limit excedido.
