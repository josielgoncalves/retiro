# Segurança

## Autenticação administrativa

- Laravel Sanctum em modo SPA: sessão por cookie `httpOnly`, `SameSite=Lax`, protegida por CSRF (`X-XSRF-TOKEN`). Nunca um token Bearer guardado em `localStorage`.
- `SESSION_SECURE_COOKIE=true` em produção (exige HTTPS).
- Rate limiting no login: 5 tentativas/minuto por IP+e-mail (`AppServiceProvider`, limiter `login`).
- Desativar um usuário (`active=false`) bloqueia logins futuros **e** invalida imediatamente qualquer sessão já aberta (middleware `App\Http\Middleware\EnsureUserIsActive`, aplicado a toda rota autenticada) — não basta checar `active` só no momento do login.
- Sem cadastro público de administrador: usuários são criados só pelo próprio painel (por um `admin` já autenticado) ou pelo comando `php artisan app:create-admin`.
- Autorização por Policy em cada recurso (`app/Policies/`); gestão de usuários é restrita a `role=admin` (`UserPolicy`).

## Link privado do portal do casal

- O token é gerado com CSPRNG (`random_bytes(32)`, 256 bits de entropia), codificado em base64url (sem padding) — seguro para uso direto na URL.
- **Apenas o hash SHA-256 do token é armazenado** (`portal_access_tokens.token_hash`); o valor em texto puro nunca é persistido e só é retornado na resposta de `POST /access-link/regenerate`, uma única vez — `GET /access-link` nunca o recupera.
- O token é tratado como uma credencial bearer: rate limit dedicado (30 req/min/IP, limiter `portal-access`), e as respostas das rotas públicas incluem:
  - `Referrer-Policy: no-referrer` — o token nunca vaza no header `Referer` de um link clicado a partir da página do portal.
  - `Cache-Control: no-store` — nunca fica em cache de navegador/proxy.
  - `X-Robots-Tag: noindex, nofollow` — nunca indexado por buscadores.
- Regenerar o link revoga o anterior **na mesma transação** (não há janela em que dois links fiquem válidos simultaneamente por acidente).
- Token inválido, expirado e revogado retornam exatamente a mesma resposta (`404` genérico) — um atacante não consegue diferenciar "não existe" de "foi revogado".
- O portal não carrega nenhum script de terceiros.
- Os dados retornados passam por uma camada de serialização dedicada (`App\Http\Resources\Portal\*`), estruturalmente separada dos Resources administrativos — nunca expõe IDs internos, observações administrativas, histórico de auditoria, dados de outros casais ou credenciais.

## CSRF e CORS

- `VerifyCsrfToken` ativo para as rotas da SPA (via `EnsureFrontendRequestsAreStateful`).
- `config/cors.php`: `supports_credentials=true`, origem restrita a `CORS_ALLOWED_ORIGINS` (nunca `*` com credentials, o que o navegador rejeitaria de qualquer forma).

## Cabeçalhos e CSP

`index.html` do frontend define uma Content-Security-Policy restritiva (`default-src 'self'`), permitindo apenas o necessário para fontes do Google Fonts (`style-src`/`font-src`). Meta tag global `robots: noindex, nofollow` — nem o painel admin nem o portal devem ser indexados.

## Auditoria

Ações sensíveis são registradas em `audit_logs` (usuário, IP, ação, dados antes/depois): criação e estorno de pagamento, criação/atualização de usuário administrativo, publicação/despublicação de aviso, regeneração/revogação de link do portal.

## Dados pessoais

Não são coletados CPF nem outros documentos de identificação — fora de escopo por decisão explícita do projeto. Campos pessoais limitados a nomes, telefone e e-mail do casal.

## Exportação CSV

`GET /reports/financial/export` nunca inclui tokens de acesso. Campos que começam com `=`, `+`, `-`, `@` ou caracteres de controle recebem um prefixo `'` para neutralizar fórmulas maliciosas (proteção contra CSV injection ao abrir em Excel/planilhas).

## Limitações conhecidas (MVP)

- Sem 2FA para administradores.
- Sem rotação automática/expiração padrão para links do portal (`expires_at` é opcional, não obrigatório).
- Sem HSTS/HTTPS configurado no Nginx de exemplo — a responsabilidade de terminar TLS (ex.: outro proxy na frente, ou configuração adicional do Nginx) fica para quem fizer o deploy em produção; ver `docs/DEPLOY.md`.
