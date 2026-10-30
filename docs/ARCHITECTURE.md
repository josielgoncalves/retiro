# Arquitetura

## Visão geral

```
                      ┌─────────────────────────┐
  Admin / Casal  ───▶ │  Nginx (porta 8080)     │
  (navegador)         │  proxy reverso único     │
                      └───────────┬──────────────┘
                     /api,/sanctum│       │ demais rotas
                                  ▼       ▼
                      ┌──────────────┐ ┌──────────────┐
                      │ API (PHP-FPM)│ │ Web (Vite dev│
                      │ Laravel 13   │ │ ou estático) │
                      └──────┬───────┘ └──────────────┘
                             │
                      ┌──────▼───────┐
                      │ PostgreSQL 16│
                      └──────────────┘
```

Um único ponto de entrada HTTP (Nginx) serve tanto a API quanto o frontend, garantindo que o cookie de sessão do Sanctum seja sempre same-origin — não há CORS entre domínios diferentes neste MVP.

## Decisões técnicas

### Monorepo com dois apps, não microsserviços
`apps/api` (Laravel) e `apps/web` (Quasar) são projetos independentes com seus próprios `Dockerfile`, mas compartilham um único `compose.yaml`. Para a escala do projeto (poucas dezenas de participantes), separar em mais serviços (filas, cache distribuído, etc.) adicionaria complexidade operacional sem benefício real.

### Laravel 13 / PHP 8.4
Versões estáveis mais recentes disponíveis no momento da implementação. O instalador do Composer resolveu `laravel/framework` em `^13.17`, que por sua vez exige `symfony/*` em `^8.0`, que exige PHP `>=8.4.1` — por isso a imagem base passou de `php:8.3` (planejado inicialmente) para `php:8.4-fpm-alpine`.

### Autenticação: Sanctum em modo SPA (cookie + CSRF), não tokens Bearer
O painel administrativo é servido pelo mesmo domínio da API (via Nginx), então a sessão de cookie do Sanctum (`statefulApi()`) é mais simples e mais segura que gerenciar tokens Bearer no localStorage (que ficariam expostos a XSS). O fluxo é: `GET /sanctum/csrf-cookie` → `POST /api/v1/auth/login` com `X-XSRF-TOKEN` → cookie de sessão `httpOnly` passa a autenticar as requisições seguintes.

O portal do casal usa um mecanismo **completamente separado**: tokens opacos de alta entropia (ver `docs/SECURITY.md`), não Sanctum, não sessão — cada acesso é stateless e autenticado só pelo token na URL.

### Valores monetários em centavos (BIGINT)
Todo valor financeiro é armazenado e manipulado como inteiro de centavos (`amount_cents`, `total_amount_cents`), nunca como `float`/`decimal` diretamente em cálculos. Isso elimina uma classe inteira de bugs de arredondamento de ponto flutuante. A conversão para reais (divisão por 100, formatação pt-BR) acontece só na camada de apresentação (`Intl`/`number_format` no backend para CSV, `toLocaleString` no frontend).

### `FinancialService` como única fonte de verdade para cálculos
Nenhum controller soma pagamentos, calcula saldo ou decide se uma parcela está quitada por conta própria — tudo passa por `App\Services\FinancialService` e pelos métodos de cálculo nos próprios models (`Enrollment::balanceCents()`, `Installment::status()`), que sempre recalculam a partir dos dados brutos (nunca de um campo `status` armazenado, que poderia ficar dessincronizado). Ver `docs/FINANCIAL_RULES.md`.

### Portal público desacoplado do admin
As rotas `/api/v1/public/portal/{token}*` não usam `auth:sanctum`; usam um middleware próprio (`AuthenticatePortalToken`) que resolve o token, e outro (`SetPrivatePortalHeaders`) que aplica os headers de privacidade. Os dados retornados passam por `App\Http\Resources\Portal\*`, uma camada de serialização separada dos Resources administrativos — isso torna estruturalmente impossível vazar um campo administrativo por esquecimento num resource compartilhado.

### Frontend: Quasar com Vite, não webpack
O scaffold oficial atual do Quasar CLI usa Vite (`@quasar/app-vite`); build mais rápido, HMR melhor. O dev server do Vite roda atrás do Nginx (único ponto de entrada), o que exigiu configurar `devServer.allowedHosts: true` porque o Nginx repassa o `Host` original do navegador, não `localhost:9000`.

### Docker Compose único para dev, variante separada para produção
`compose.yaml` (dev): bind-mounts do código-fonte, hot-reload, Postgres publicado só em `127.0.0.1`. `compose.prod.yaml`: imagens com build multi-stage (sem bind mount, sem dependências de dev, autoloader otimizado), Postgres nunca publicado.

## Fluxo de dados: registro de pagamento (exemplo)

1. Frontend envia `POST /api/v1/enrollments/{id}/payments` com valor, data, forma de pagamento.
2. `StorePaymentRequest` valida formato e autoriza via `EnrollmentPolicy::managePayments`.
3. `PaymentController::store` delega para `FinancialService::registerPayment`, que:
   - abre uma transação e aplica `lockForUpdate()` na linha da inscrição (serializa requisições concorrentes);
   - recalcula o saldo a partir dos pagamentos ativos já existentes;
   - rejeita se o novo valor ultrapassar o saldo;
   - cria o pagamento e, se a modalidade for fixa, aloca automaticamente nas parcelas mais antigas não quitadas (ou nas parcelas explicitamente escolhidas pelo admin);
4. `AuditLogger` registra a ação em `audit_logs`.
5. A resposta usa `PaymentResource`, nunca o model bruto.
