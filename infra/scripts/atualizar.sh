#!/usr/bin/env bash
# Atualiza a produção com segurança: backup, fast-forward, build, subida, health check e migrations.
# Uso: ./infra/scripts/atualizar.sh [--sempre] [--sem-backup] [--sem-limpeza] [--dry-run] [--branch=NOME]
set -euo pipefail

RAIZ="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
cd "$RAIZ"

SEMPRE=0
BACKUP=1
DRY=0
LIMPEZA=1
BRANCH="${BRANCH:-main}"

for argumento in "$@"; do
  case "$argumento" in
    --sempre) SEMPRE=1 ;;
    --sem-backup) BACKUP=0 ;;
    --sem-limpeza) LIMPEZA=0 ;;
    --dry-run) DRY=1 ;;
    --branch=*) BRANCH="${argumento#--branch=}" ;;
    -h|--help) sed -n '2,3p' "$0"; exit 0 ;;
    *) echo "Opção desconhecida: $argumento" >&2; exit 2 ;;
  esac
done

ARQUIVO_ENV="${ENV_FILE:-.env.production}"
[ -f "$ARQUIVO_ENV" ] || { echo "ERRO: $ARQUIVO_ENV não existe." >&2; exit 1; }

COMPOSE=(docker compose -f compose.prod.yaml --env-file "$ARQUIVO_ENV")
executar() { if [ "$DRY" = 1 ]; then echo "[dry-run] $*"; else "$@"; fi; }
passo() { echo; echo "==> $*"; }
valor_env() {
  grep -E "^$1=" "$ARQUIVO_ENV" | tail -n 1 | cut -d= -f2- | sed -E 's/[[:space:]]+#.*$//; s/^"//; s/"$//; s/[[:space:]]+$//'
}

exec 9>/tmp/retiro-atualizar.lock
flock -n 9 || { echo "Já existe uma atualização em andamento." >&2; exit 1; }

passo "Estado atual"
COMMIT_ANTERIOR="$(git rev-parse HEAD)"
git fetch --quiet origin "$BRANCH"
COMMIT_ALVO="$(git rev-parse "origin/$BRANCH")"
echo "local : ${COMMIT_ANTERIOR:0:8} ($(git log -1 --format=%s HEAD))"
echo "remoto: ${COMMIT_ALVO:0:8} ($(git log -1 --format=%s "origin/$BRANCH"))"

if [ -n "$(git status --porcelain --untracked-files=no)" ]; then
  echo "ERRO: existem alterações locais em arquivos versionados." >&2
  git status --short --untracked-files=no >&2
  exit 1
fi

if [ "$COMMIT_ANTERIOR" = "$COMMIT_ALVO" ] && [ "$SEMPRE" = 0 ]; then
  echo "Já está atualizado. Use --sempre para reconstruir mesmo assim."
  exit 0
fi

if [ "$BACKUP" = 1 ]; then
  passo "Backup do banco e das capas"
  BANCO="$(valor_env DB_DATABASE)"
  USUARIO="$(valor_env DB_USERNAME)"
  mkdir -p backups
  DATA_BACKUP="$(date +%F-%H%M%S)"

  if [ "$DRY" = 1 ]; then
    echo "[dry-run] backup do banco para backups/${BANCO:-retiro}-DATABASE.sql.gz"
    echo "[dry-run] backup das capas para backups/retiro-uploads-DATA.tar.gz"
  elif "${COMPOSE[@]}" ps --status running postgres 2>/dev/null | grep -q postgres; then
    ARQUIVO_BANCO="backups/${BANCO:-retiro}-${DATA_BACKUP}.sql.gz"
    "${COMPOSE[@]}" exec -T postgres pg_dump -U "$USUARIO" "$BANCO" | gzip > "$ARQUIVO_BANCO"
    echo "Banco: $ARQUIVO_BANCO"

    if "${COMPOSE[@]}" ps --status running api 2>/dev/null | grep -q api; then
      ARQUIVO_UPLOADS="backups/retiro-uploads-${DATA_BACKUP}.tar.gz"
      "${COMPOSE[@]}" exec -T api tar -czf - -C storage/app/private . > "$ARQUIVO_UPLOADS"
      echo "Capas: $ARQUIVO_UPLOADS"
    else
      echo "API ainda não está ativa: backup das capas ignorado (primeira implantação)."
    fi

    ls -1t backups/*.sql.gz 2>/dev/null | tail -n +8 | xargs -r rm -f
    ls -1t backups/retiro-uploads-*.tar.gz 2>/dev/null | tail -n +8 | xargs -r rm -f
  else
    echo "Serviços ainda não estão ativos: backup ignorado (primeira implantação)."
  fi
fi

passo "Atualizando código ($BRANCH)"
executar git merge --ff-only "origin/$BRANCH"

passo "Construindo imagens"
executar "${COMPOSE[@]}" build --pull

passo "Subindo serviços"
executar "${COMPOSE[@]}" up -d --remove-orphans

passo "Aguardando a API"
if [ "$DRY" = 0 ]; then
  PORTA="$(valor_env NGINX_HTTP_PORT)"
  PORTA="${PORTA:-8083}"
  for tentativa in $(seq 1 40); do
    if curl -fsS "http://127.0.0.1:$PORTA/up" >/dev/null 2>&1; then
      echo "API respondendo."
      break
    fi
    [ "$tentativa" = 40 ] && { echo "ERRO: a API não respondeu em /up." >&2; exit 1; }
    sleep 3
  done
fi

passo "Migrations"
executar "${COMPOSE[@]}" exec -T api php artisan migrate --force

passo "Conferência"
if [ "$DRY" = 0 ]; then
  curl -fsS "http://127.0.0.1:$PORTA/up" && echo
  "${COMPOSE[@]}" ps
fi
if [ "$LIMPEZA" = 1 ]; then
  passo "Limpando imagens e cache Docker antigos"
  executar docker image prune -af --filter "until=168h"
  executar docker builder prune -af --filter "until=168h"
  echo "Volumes não foram removidos."
fi

echo
echo "Pronto: ${COMMIT_ANTERIOR:0:8} -> $(git rev-parse --short HEAD)"
echo "Rollback do código: git reset --hard ${COMMIT_ANTERIOR:0:8} && ./infra/scripts/atualizar.sh --sempre --sem-backup"
echo "Restaure o banco e as capas pelos arquivos correspondentes em backups/ se necessário."
