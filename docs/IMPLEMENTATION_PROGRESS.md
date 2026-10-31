# Progresso de Implementação — Portal do Retiro de Casais

## Fase atual

Todas as 5 fases concluídas. MVP funcional, testado (backend e frontend) e documentado.

## Resumo

| Fase | Status |
|---|---|
| 1 — Estrutura, Docker, autenticação | ✅ Concluída |
| 2 — Retiros, casais, inscrições, parcelas, regras financeiras | ✅ Concluída |
| 3 — Painel administrativo (API REST completa + frontend Quasar) | ✅ Concluída |
| 4 — Portal público do casal, avisos, tokens de acesso | ✅ Concluída |
| 5 — Testes de integração, segurança, documentação | ✅ Concluída |

**Testes**: 60 testes de backend (139 assertions) + 18 testes de frontend (Vitest) = **78 testes automatizados**, todos passando. Validação adicional end-to-end com navegador real (Playwright) cobrindo o fluxo completo: login → criar retiro → cadastrar casal → inscrever (fixa e flexível) → registrar pagamento → gerar link do portal → acessar portal no celular → ver aviso publicado → tratar link inválido.

## Concluído por área

### Infraestrutura
- `compose.yaml` (dev) e `compose.prod.yaml` (produção), ambos com healthchecks, rede privada, Postgres nunca exposto publicamente.
- Dockerfiles multi-stage (`dev`/`prod`) para API e frontend; `infra/nginx/` com configs dedicadas para dev (proxy ao Vite) e produção (serve estático + fastcgi).
- Banco de testes (`retiro_casais_testing`) completamente isolado do banco de desenvolvimento — ver seção de problemas encontrados, esse isolamento quebrou silenciosamente mais de uma vez e exigiu investigação profunda para corrigir de verdade.

### Backend (Laravel 13 / PHP 8.4)
- Migrations completas com FKs, índices e `CHECK` constraints para todas as 10 entidades do domínio.
- `FinancialService`: toda a lógica de parcelamento, pagamento, alocação, estorno e locking transacional centralizada aqui — ver `docs/FINANCIAL_RULES.md`.
- `PortalTokenService`: geração/validação de tokens do portal, hash SHA-256, nunca texto puro persistido.
- `AuditLogger`: ações sensíveis (pagamento, estorno, usuário, link, aviso) registradas em `audit_logs`.
- API REST completa (34 rotas): retiros, casais, inscrições, pagamentos, estornos, links de acesso, avisos, usuários, dashboard, relatórios (JSON + CSV), autenticação.
- Autorização via Policies (`admin` vs `operator`), com middleware `active` que corta sessões de usuários desativados imediatamente (não só bloqueia login futuro).
- Portal público desacoplado (middlewares `AuthenticatePortalToken` + `SetPrivatePortalHeaders`), Resources dedicados (`App\Http\Resources\Portal\*`) que nunca expõem dados administrativos.
- Seeder de demonstração (`DatabaseSeeder`): 1 retiro, 5 casais, 3 inscrições fixas (com pagamentos variados, incluindo uma parcela vencida), 2 flexíveis, avisos publicados e rascunho — tudo construído via `FinancialService`, nunca inserido diretamente, garantindo que os dados de demo respeitem as mesmas regras de negócio da aplicação real.
- Comando `app:create-admin` para criar/atualizar o administrador sem credenciais fixas no código.

### Frontend (Quasar 2 / Vue 3 / TypeScript / Pinia)
- Autenticação via Sanctum SPA (cookie + CSRF), guard de rotas por `meta.requiresAuth`/`requiresAdmin`/`guestOnly`.
- Painel administrativo: login, dashboard (por retiro, nunca mistura dados entre retiros), retiros (lista/form), casais (lista com busca/filtro/paginação, form, inscrições vinculadas), inscrição (criação em ambas modalidades), detalhe da inscrição (financeiro completo, registro de pagamento, estorno, geração/regeneração/revogação de link, compartilhamento WhatsApp), avisos (publicar/despublicar), relatórios (tabela + exportação CSV), usuários (admin only).
- O cadastro de retiro centraliza capa 16:9, chave PIX, favorecido e WhatsApp de comprovantes. As capas ficam em storage privado e são servidas por endpoint controlado; sem capa cadastrada, o portal não exibe imagem de outro evento.
- Portal do casal: mobile-first, saudação personalizada, resumo financeiro com barra de progresso, parcelas (quando fixa), histórico de pagamentos, avisos, tela dedicada para link inválido/expirado/revogado (resposta genérica, não distingue o motivo).
- Identidade visual: paleta verde profundo/verde suave/creme conforme especificado, tipografia Lora (títulos) + Figtree (texto), sem emojis, sem gradientes excessivos.
- `pt-BR` aplicado via `quasar/lang/pt-BR` (datas, mensagens de componentes), formatação de moeda via `Intl`/`toLocaleString`.

## Testes executados

### Backend — `docker compose exec api php artisan test` (60 passed, 139 assertions)
Cobre todos os 25 itens da lista obrigatória de testes de backend da especificação: cadastro de casal; inscrição fixa e flexível; arredondamento de parcelas; pagamento integral, parcial, múltiplos pagamentos numa parcela, um pagamento quitando várias parcelas, antecipação; pagamento flexível; rejeição de valor acima do saldo; rejeição de valor zero/negativo; rejeição de alocação para outra inscrição; estorno com recálculo e preservação de histórico; rejeição de estorno duplicado; **duas requisições de pagamento simultâneas** (testado com processos de SO reais, não apenas chamadas sequenciais simuladas); bloqueio de edição financeira indevida; isolamento entre retiros (dashboard e relatório); token válido, revogado, inexistente e expirado (todos retornam a mesma resposta genérica); ausência de dados administrativos no portal; operador sem permissão de gerenciar usuários; apenas avisos publicados e dentro da validade; dashboard exclui pagamentos estornados; CSV não mistura dados de outros retiros; auditoria de ações sensíveis (pagamento, estorno, usuário, link).

### Frontend — `docker compose exec web npm test` (18 passed)
Login e logout (store + componente), cadastro de casal, exibição de avisos no portal, tratamento de link inválido, utilitários de formatação (moeda, data, status de parcela, normalização de telefone para WhatsApp).

### Validação end-to-end com navegador real
Rodada com Playwright (headless Chromium), acessando a stack via `http://localhost:8080` exatamente como um usuário real acessaria — não apenas testes mockados. Capturou e permitiu corrigir **6 bugs reais** que nenhum teste automatizado teria pego sozinho (ver seção seguinte). Cobriu: login, dashboard, criação de retiro, criação de casal, inscrição em modalidade fixa com 3 parcelas, registro de pagamento parcial com recálculo correto de saldo, geração de link do portal, acesso ao portal em viewport mobile (390×844), avisos (criação/publicação), tela de link inválido.

## Critérios de aceite da especificação (seção 16)

Todos os 20 itens foram exercidos manualmente (via navegador real) e/ou cobertos por teste automatizado durante o desenvolvimento:

1. ✅ Projeto inicia via `docker compose up -d --build`
2. ✅ Login no painel administrativo
3. ✅ Criação de retiro
4. ✅ Cadastro de casal
5. ✅ Inscrição em modalidade fixa
6. ✅ Geração correta das parcelas (arredondamento determinístico testado)
7. ✅ Registro de pagamento parcial
8. ✅ Consulta do saldo atualizado
9. ✅ Cadastro de outro casal em modalidade livre
10. ✅ Registro de valores variados (flexível)
11. ✅ Geração de link privado
12. ✅ Link aberto em viewport mobile
13. ✅ Consulta de todos os pagamentos registrados
14. ✅ Publicação de aviso
15. ✅ Visualização do aviso no portal correto
16. ✅ Revogação de link antigo (testado: `test_regenerating_access_link_revokes_the_previous_one`)
17. ✅ Acesso bloqueado com token revogado
18. ✅ Estorno de pagamento com recálculo de totais
19. ✅ Exportação de relatório financeiro (CSV)
20. ✅ Suíte de testes obrigatórios passando (78/78)

## Problemas encontrados e resolvidos

### Infraestrutura / setup inicial
- `composer create-project` bloqueado pela auditoria de segurança padrão do Composer 2.7+ — resolvido com `--no-audit` no provisionamento (não afeta o app final).
- Laravel instalado (13.x) exige PHP ^8.4; imagem base da API ajustada de `php:8.3` para `php:8.4-fpm-alpine`.
- Porta 5432 já em uso no host — porta de dev publicada em `55432` via `POSTGRES_HOST_PORT`.
- Bind mounts do host (dev) são donos por UID do host, mas o worker do php-fpm roda como `www-data` por padrão — causava erro 500 ao compilar views Blade. Corrigido recriando `www-data` com UID 1000 na imagem de dev.

### Nginx / roteamento
- nginx só roteava `/api` para o PHP-FPM; `/up` e `/sanctum/*` caíam incorretamente no dev server do frontend — corrigido com regex cobrindo os três prefixos.

### Sanctum / autenticação
- Teste de logout com múltiplas requisições simuladas no mesmo processo PHPUnit expôs uma particularidade do Sanctum (`Auth::shouldUse('sanctum')` troca o guard padrão) que não ocorre em produção; corrigido com `forgetGuards()` no teste.
- `Payment::reversed_at/reversed_by/reversal_reason` ficam fora do `#[Fillable]` de propósito; `FinancialService::reversePayment()` usa `forceFill()`.

### Isolamento do banco de testes (o problema mais sério encontrado)
Os testes chegaram a **apagar o banco de desenvolvimento silenciosamente, mais de uma vez**, apesar de `phpunit.xml` declarar `force="true"` para `DB_DATABASE=retiro_casais_testing`. Causa raiz, em cadeia: (1) `compose.yaml` define `DB_DATABASE` como variável de ambiente real do container `api`; (2) o repositório de ambiente do Laravel é construído como **imutável**, então nada que o Dotenv carregue de um arquivo depois consegue sobrescrever uma env var já presente no processo; (3) isso torna o `force="true"` do PHPUnit insuficiente aqui, porque a aplicação do `.env`/`.env.testing` pelo Dotenv roda depois e, sendo imutável, os valores do container (dev) vencem de qualquer forma. A correção definitiva (`apps/api/tests/bootstrap.php`) faz `putenv()`/`$_ENV`/`$_SERVER` diretamente para `APP_ENV` e todos os `DB_*`, antes mesmo de `vendor/autoload.php` ser carregado — nenhum arquivo precisa sobrescrever nada, os valores corretos já estão lá desde o início. Documentado em detalhe em `CLAUDE.md` para nunca mais precisar re-investigar do zero.

### Frontend (encontrados via teste real em navegador, não por testes automatizados)
- **Alias de import errado em todos os arquivos**: o scaffold do Quasar só define o alias `@` → `src/`; vários arquivos foram escritos usando `src/...`, quebrando a compilação silenciosamente (só visível no log do container).
- **Vite `allowedHosts`**: como tudo passa pelo Nginx (que repassa o `Host` original do navegador, não `localhost:9000`), o dev server do Vite bloqueava a requisição com 403. Corrigido com `devServer.allowedHosts: true`.
- **`LoginPage` sem `QLayout`**: usava `<q-page>` fora de um `QLayout`/`QPageContainer` (tela de login é standalone, fora do `AdminLayout`) — Quasar não renderizava o conteúdo. Corrigido trocando por uma `<div>` simples.
- **CSP bloqueando Google Fonts**: a Content-Security-Policy gerada pelo Quasar só permitia `style-src 'self' 'unsafe-inline'`; adicionado `https://fonts.googleapis.com`/`https://fonts.gstatic.com`.
- **`no-referrer` global quebrando a autenticação**: uma `<meta name="referrer" content="no-referrer">` global (pensada para a privacidade do portal) suprimia o header `Referer`/`Origin` em **todas** as chamadas da SPA, inclusive as do painel admin — e isso quebra a detecção de "requisição do frontend" do Sanctum para requisições GET (login funcionava, por ser POST; todo GET autenticado passava a voltar 401). Removida; a política de `no-referrer` do portal privado já estava corretamente implementada só como header de resposta HTTP no backend (`SetPrivatePortalHeaders`), que é o lugar certo.
- **`$q.notify`/`$q.dialog` sem os plugins registrados**: `quasar.config.ts` tinha `framework.plugins: []`. Toda chamada a `$q.notify(...)` lançava uma exceção síncrona que abortava silenciosamente o resto da função — inclusive o `router.push()` que viria depois. Sintoma: formulários pareciam "não fazer nada" ao salvar, sem nenhum erro visível na tela (só no console). Corrigido registrando `['Notify', 'Dialog']`.

## Limitações conhecidas do MVP

- Sem fluxo de "revisão financeira" para alterar modalidade/valor contratado após pagamentos — bloqueado com mensagem explicativa, por decisão explícita do escopo do MVP.
- Sem HTTPS/TLS configurado no `compose.prod.yaml` — depende de infraestrutura externa (proxy/balanceador na frente), documentado em `docs/DEPLOY.md`.
- Sem 2FA para administradores.
- Sem expiração padrão obrigatória para links do portal (`expires_at` é opcional).
- Sem fila assíncrona/worker — não há jobs de longa duração no MVP.
- Sem monitoramento/alertas integrados (Sentry, Prometheus, etc.).
- Backup é manual/via cron documentado, não automatizado por um serviço gerenciado.

## Próximos passos sugeridos para produção

1. Configurar TLS (proxy na frente do stack, ou adicionar suporte a certificado diretamente no Nginx).
2. Avaliar 2FA para contas administrativas.
3. Automatizar backup do PostgreSQL (cron + upload para armazenamento externo).
4. Adicionar monitoramento básico (healthchecks já existem; faltam alertas).
5. Revisar `expires_at` padrão para links do portal, se a organização preferir expiração automática.
