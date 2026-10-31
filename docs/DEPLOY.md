# Deploy em produção

## Pré-requisitos

- Um host com Docker + Docker Compose.
- Um domínio apontando para o host, com TLS terminado **na frente** deste stack (ex.: outro Nginx/Caddy/Traefik, ou um balanceador de carga do provedor de nuvem) — `compose.prod.yaml` não configura HTTPS diretamente; o Nginx interno serve HTTP simples e só é publicado em `127.0.0.1` (nunca em `0.0.0.0`), exatamente para ser alcançado apenas por esse proxy externo rodando na mesma máquina.

## Proxy reverso externo (HTTPS)

O Nginx deste stack (`infra/nginx/prod.conf`) espera rodar atrás de um proxy externo que termina HTTPS e encaminha os headers `X-Forwarded-Proto`/`X-Forwarded-Host`/`X-Forwarded-For`. Dois pontos exigem atenção ao configurar esse proxy:

1. **`set_real_ip_from` em `infra/nginx/prod.conf`** precisa cobrir o IP/rede de onde o proxy externo realmente se conecta. O padrão (`172.16.0.0/12`) cobre a rede Docker própria deste projeto; se o proxy externo rodar fora do Docker (direto no host, conectando via `127.0.0.1:${NGINX_HTTP_PORT}`), adicione `set_real_ip_from 127.0.0.1;` também — mas confirme a configuração de rede do seu ambiente antes, já que confiar num endereço errado permite que um cliente malicioso forje `X-Forwarded-For`.
2. **`NGINX_HTTP_PORT=8083`** (ou a porta que preferir) em `.env.production` — esse é o único ponto por onde o proxy externo acessa o stack.

Depois de qualquer alteração em `infra/nginx/prod.conf`, valide a sintaxe antes de reconstruir:

```bash
docker run --rm -v "$PWD/infra/nginx/prod.conf":/etc/nginx/conf.d/default.conf:ro nginx:1.27-alpine nginx -t
```

## Variáveis de ambiente

Copie `.env.example` para `.env.production` e preencha **valores reais de produção** (nunca reutilize as credenciais de desenvolvimento):

```bash
cp .env.example .env.production
```

Gere uma `APP_KEY` nova (não reutilize a de desenvolvimento):

```bash
docker run --rm -v "$PWD/apps/api":/app -w /app composer:2 exec -- php artisan key:generate --show
```

Cole o valor gerado em `APP_KEY` no `.env.production`. Ajuste também:
- `APP_URL` — URL pública real (`https://...`).
- `DB_PASSWORD` — senha forte, gerada aleatoriamente.
- `SESSION_DOMAIN` e `SANCTUM_STATEFUL_DOMAINS` — o domínio real, sem porta se usar 443 padrão.
- `DEMO_ADMIN_*` — **não use em produção**; em vez disso, crie o primeiro admin manualmente (veja abaixo) e não rode o seeder de demonstração.
- `NGINX_HTTP_PORT=8083` — porta local por onde o proxy reverso externo acessa o stack (ver seção "Proxy reverso externo" abaixo).

## Subir o stack

```bash
docker compose -f compose.prod.yaml --env-file .env.production up -d --build
docker compose -f compose.prod.yaml --env-file .env.production exec api php artisan migrate --force
docker compose -f compose.prod.yaml --env-file .env.production exec api php artisan app:create-admin --email=admin@suaorganizacao.org --password='<senha forte>' --name='Nome do Admin'
```

**Não rode `db:seed` em produção** — ele cria dados fictícios de demonstração.

## Backup

O único estado persistente é o volume do PostgreSQL (`postgres_data`). Backup lógico recomendado (roda fora do container, requer `pg_dump` disponível ou via `docker compose exec`):

```bash
docker compose -f compose.prod.yaml exec postgres \
  pg_dump -U "$DB_USERNAME" "$DB_DATABASE" | gzip > backup-$(date +%Y%m%d-%H%M).sql.gz
```

Automatize isso via cron no host, com rotação de arquivos antigos. Nada além do banco precisa de backup — as imagens Docker são reproduzíveis a partir do código-fonte.

## Restauração

```bash
gunzip -c backup-20270101-0300.sql.gz | docker compose -f compose.prod.yaml exec -T postgres \
  psql -U "$DB_USERNAME" "$DB_DATABASE"
```

Restaure sempre em um banco vazio/novo, nunca sobre um banco com dados ativos, a menos que a intenção seja realmente substituí-lo por completo.

## Atualizações

```bash
git pull
docker compose -f compose.prod.yaml --env-file .env.production up -d --build
docker compose -f compose.prod.yaml --env-file .env.production exec api php artisan migrate --force
```

## Limitações conhecidas do MVP para produção

- Sem HTTPS/TLS configurado neste repositório — deve ser resolvido pela infraestrutura que recebe o tráfego antes deste stack.
- Sem rotação automática de logs do Nginx/PHP-FPM configurada além do padrão das imagens base.
- Sem monitoramento/alertas (ex.: Sentry, Prometheus) integrados.
- Sem fila assíncrona (não há jobs de longa duração no MVP; se isso mudar, será necessário um worker dedicado).
- Backup é manual/cron, não automatizado via um serviço gerenciado.
