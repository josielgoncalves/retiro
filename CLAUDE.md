# CLAUDE.md

Orientações para quem (humano ou assistente de IA) for continuar o desenvolvimento deste repositório.

## Visão geral

Monorepo com API Laravel (`apps/api`) e frontend Quasar/Vue 3 (`apps/web`), orquestrados via Docker Compose. Veja `docs/ARCHITECTURE.md` para o raciocínio por trás das escolhas técnicas.

## Como rodar

```bash
cp .env.example .env
docker compose up -d --build
docker compose exec api php artisan migrate --force
docker compose exec api php artisan db:seed --force
docker compose exec api php artisan app:create-admin
```

Tudo passa pelo Nginx em `http://localhost:8080` — não acesse `apps/web`'s porta 9000 nem `apps/api` diretamente; o Sanctum depende do cookie de sessão ser same-origin com o frontend.

## Regras de negócio financeiras — NÃO contornar

Toda lógica de cálculo financeiro (parcelas, saldos, alocações, estornos) vive em `apps/api/app/Services/FinancialService.php`. Controllers nunca calculam totais sozinhos nem confiam em valores vindos do frontend. Qualquer alteração nessa área deve:
1. Rodar `docker compose exec api php artisan test --filter=FinancialServiceTest` antes e depois.
2. Preservar os invariantes testados em `apps/api/tests/Feature/Financial/`, especialmente o locking transacional em `registerPayment()` (testado em `ConcurrentPaymentTest`, que testa concorrência real via processos de SO separados — ver comentário no topo do arquivo antes de "simplificar").
3. Nunca rodar `php artisan test`/`db:seed` em sequência sem reconferir que o banco de dev sobreviveu (`php artisan tinker --execute="echo App\Models\User::count();"`) — ver "Isolamento do banco de testes" abaixo; esse isolamento já quebrou silenciosamente uma vez.

## Armadilhas já encontradas (não repetir)

- **Imports do frontend**: o único alias configurado é `@` → `apps/web/src`. Não existe alias `src`. Erros de import silenciosos em dev geralmente aparecem só no log do container (`docker compose logs web`), não na tela.
- **`$q.notify` / `$q.dialog`**: exigem os plugins `Notify`/`Dialog` registrados em `apps/web/quasar.config.ts` (`framework.plugins`). Sem isso, a chamada lança uma exceção síncrona que aborta silenciosamente o resto da função — incluindo o `router.push()` que viria depois. Se um formulário "não navega após salvar" sem erro visível, comece por aqui.
- **`Referrer-Policy: no-referrer` é só para o portal privado** (`SetPrivatePortalHeaders`, no backend). Nunca aplique isso como `<meta>` global no `index.html`: isso suprime o header `Referer`/`Origin` em todas as chamadas da SPA, inclusive as do próprio painel admin, e quebra a detecção de "requisição do frontend" do Sanctum (`EnsureFrontendRequestsAreStateful`) — login funciona (é POST), mas todo GET autenticado passa a voltar 401.
- **Vite `allowedHosts`**: como tudo passa pelo Nginx (que repassa o `Host` original do cliente), o dev server do Vite precisa de `devServer.allowedHosts: true` em `quasar.config.ts`. Sem isso, o Vite bloqueia a requisição com 403 antes mesmo dela chegar no Vue Router.
- **`#[Fillable]`**: campos de auditoria/estorno (`Payment::reversed_at/reversed_by/reversal_reason`) ficam fora de propósito da lista `#[Fillable]`, para o cliente nunca conseguir setá-los via mass assignment. O `FinancialService` usa `forceFill()` explicitamente para esses casos — é o único lugar que deveria fazer isso.
- **Isolamento do banco de testes (o bug mais traiçoeiro deste projeto)**: os testes do backend devem rodar contra `retiro_casais_testing` (banco separado, criado pelo script `infra/postgres/init/`), nunca contra o banco de desenvolvimento. Isso **quebrou silenciosamente** e apagou o banco de dev várias vezes durante o desenvolvimento, apesar de `phpunit.xml` ter `<env name="DB_DATABASE" value="retiro_casais_testing" force="true"/>`. Causa raiz completa, em cadeia:
  1. `compose.yaml` define `DB_DATABASE`/`DB_HOST`/etc. como variáveis de ambiente **reais do container** `api` (não só do arquivo `.env`).
  2. O repositório de ambiente do Laravel (`Illuminate\Support\Env::getRepository()`) é construído como **imutável** (`$builder->immutable()->make()`) — uma vez que uma env var já existe no processo, nada que o Dotenv carregue de um arquivo depois consegue sobrescrevê-la.
  3. Isso por si só já torna `<env force="true">` do PHPUnit pouco confiável aqui: mesmo com `force`, a aplicação de `.env`/`.env.testing` pelo Dotenv roda DEPOIS, e por ser imutável, os valores do container (dev) vencem de qualquer forma.
  4. A correção verificada (`apps/api/tests/bootstrap.php`) não depende de `.env.testing` nem de `phpunit.xml`: faz `putenv()` + `$_ENV` + `$_SERVER` diretamente para `APP_ENV` e todos os `DB_*`, **antes** de `require vendor/autoload.php`. Isso vence tanto o container quanto a imutabilidade do Dotenv, porque nenhum arquivo precisa sobrescrever nada — os valores corretos já estão lá desde o início do processo.
  - Se precisar depurar isso de novo: adicione um `fwrite(STDERR, ...)` no `setUp()` de `tests/TestCase.php` imprimindo `DB::connection('pgsql')->selectOne('select current_database() as db')->db` (o nome real reportado pelo Postgres, não o que o Laravel *acha* que está configurado) — foi assim que a causa raiz foi finalmente confirmada, depois de várias hipóteses erradas (RefreshDatabase, `ConcurrentPaymentTest` especificamente, cache de config) não baterem com a evidência.
  - Nunca "resolva" isso de novo com `config(['database.connections.pgsql.database' => ...])` dentro de um teste específico — não é robusto (depende da ordem dos testes) e não ataca a causa raiz. Edite `tests/bootstrap.php`.

## Antes de considerar uma mudança de frontend "pronta"

Testes automatizados (Vitest) não pegam vários dos bugs acima — todos foram encontrados rodando a aplicação de verdade num navegador (ver `docs/IMPLEMENTATION_PROGRESS.md`). Sempre que possível, valide a mudança com a stack rodando via `docker compose up`, não só com os testes.

## Padrões de código

- Sem comentários explicando o óbvio; comentários só para decisões não óbvias (ver exemplos acima).
- Datas/valores: formatação pt-BR, fuso `America/Sao_Paulo`, valores monetários sempre em centavos (inteiros) no banco e na API; conversão para reais só na camada de apresentação.
- Autorização: Policies no backend, nunca checks só no frontend.
