# Portal do Retiro de Casais

Sistema de gestão para uma organização de retiros de casais: inscrições, controle financeiro (parcelado ou livre), portal privado de acompanhamento para os casais e avisos do retiro.

- **Backend**: Laravel 13 (PHP 8.4), PostgreSQL, Laravel Sanctum (autenticação em modo SPA).
- **Frontend**: Vue 3 + Quasar 2 + TypeScript + Pinia, servido pelo Vite em desenvolvimento.
- **Infraestrutura**: Docker Compose, Nginx como proxy reverso único.

## Instalação (desenvolvimento)

Pré-requisitos: Docker e Docker Compose.

```bash
cp .env.example .env
docker compose up -d --build
docker compose exec api php artisan migrate --force
docker compose exec api php artisan db:seed --force      # dados de demonstração
docker compose exec api php artisan app:create-admin      # cria o administrador (usa DEMO_ADMIN_* do .env)
```

Acesse:
- Painel administrativo: http://localhost:8080/app (login com o e-mail/senha definidos em `DEMO_ADMIN_EMAIL`/`DEMO_ADMIN_PASSWORD`)
- Portal do casal: http://localhost:8080/c/{token} (o token é gerado pelo painel, na tela de detalhes da inscrição)

Se a porta `5432` já estiver em uso no host, ajuste `POSTGRES_HOST_PORT` no `.env` (o PostgreSQL do container não precisa estar acessível para a aplicação funcionar; a publicação de porta é só uma conveniência para ferramentas de administração de banco).

## Comandos úteis

```bash
# Migrations
docker compose exec api php artisan migrate
docker compose exec api php artisan migrate:fresh --seed   # recria o schema com dados de demo

# Testes do backend (rodam contra um banco de testes dedicado, retiro_casais_testing)
docker compose exec api php artisan test

# Testes do frontend
docker run --rm -v "$PWD/apps/web":/app -w /app node:22-alpine npm test
# ou, com o container já rodando:
docker compose exec web npm test

# Build de produção do frontend (gerado dentro da imagem nginx, não precisa rodar manualmente)
docker compose -f compose.prod.yaml build
```

## Estrutura do repositório

```
retiro-casais/
  apps/
    api/     # Laravel (API REST)
    web/     # Quasar/Vue 3 (painel administrativo + portal do casal)
  infra/
    nginx/   # Configuração do proxy reverso (dev e produção)
    postgres/ # Script de inicialização (cria o banco de testes dedicado)
  docs/      # Documentação detalhada (veja abaixo)
  compose.yaml       # Desenvolvimento
  compose.prod.yaml  # Produção
```

## Documentação

- [docs/ARCHITECTURE.md](docs/ARCHITECTURE.md) — decisões técnicas e visão geral da arquitetura
- [docs/DATABASE.md](docs/DATABASE.md) — modelo de dados
- [docs/FINANCIAL_RULES.md](docs/FINANCIAL_RULES.md) — regras financeiras (parcelamento, pagamentos, estornos)
- [docs/API.md](docs/API.md) — endpoints da API REST
- [docs/SECURITY.md](docs/SECURITY.md) — segurança (portal privado, tokens, autenticação)
- [docs/DEPLOY.md](docs/DEPLOY.md) — deploy em produção, backup e restauração
- [docs/IMPLEMENTATION_PROGRESS.md](docs/IMPLEMENTATION_PROGRESS.md) — progresso da implementação, por fase

## Limitações conhecidas do MVP

Veja a seção final de `docs/IMPLEMENTATION_PROGRESS.md` para a lista completa de pendências e próximos passos sugeridos para produção.
