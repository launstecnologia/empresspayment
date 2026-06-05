#!/usr/bin/env bash
# deploy.sh — Deploy completo no VPS
# Uso:
#   Primeiro deploy:  bash deploy.sh install
#   Atualização:      bash deploy.sh update
#   Só migrations:    bash deploy.sh migrate
#   Ver logs:         bash deploy.sh logs
#   Parar tudo:       bash deploy.sh down
#   Status:           bash deploy.sh status

set -euo pipefail

COMPOSE="docker compose"
APP_CONTAINER="express-app"
BLUE='\033[0;34m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
RED='\033[0;31m'
NC='\033[0m'

log()  { echo -e "${BLUE}[deploy]${NC} $1"; }
ok()   { echo -e "${GREEN}[✔]${NC} $1"; }
warn() { echo -e "${YELLOW}[!]${NC} $1"; }
err()  { echo -e "${RED}[✗]${NC} $1"; exit 1; }

# ----------------------------------------------------------------
# Verifica pré-requisitos
# ----------------------------------------------------------------
check_requirements() {
    command -v docker >/dev/null 2>&1 || err "Docker não instalado. Instale em https://docs.docker.com/engine/install/"
    docker compose version >/dev/null 2>&1 || err "Docker Compose v2 não encontrado."
    [ -f ".env" ] || err "Arquivo .env não encontrado. Copie .env.example e preencha."
    [ -f "automacao/.env" ] || err "Arquivo automacao/.env não encontrado. Copie automacao/.env.example e preencha."
}

# ----------------------------------------------------------------
# Verifica se as chaves API batem
# ----------------------------------------------------------------
check_api_keys() {
    LARAVEL_KEY=$(grep "^AUTOMACAO_API_KEY=" .env | cut -d'=' -f2-)
    PYTHON_KEY=$(grep "^AUTOMACAO_API_KEY=" automacao/.env | cut -d'=' -f2-)

    if [ -z "$LARAVEL_KEY" ] || [ -z "$PYTHON_KEY" ]; then
        warn "AUTOMACAO_API_KEY não configurada em um dos .env!"
        return
    fi

    if [ "$LARAVEL_KEY" != "$PYTHON_KEY" ]; then
        err "AUTOMACAO_API_KEY diferente entre .env e automacao/.env — as chaves devem ser iguais!"
    fi
    ok "Chaves AUTOMACAO_API_KEY conferem"
}

# ----------------------------------------------------------------
# Primeiro deploy (build + migrate + seed)
# ----------------------------------------------------------------
cmd_install() {
    log "Iniciando primeiro deploy..."
    check_requirements
    check_api_keys

    log "Construindo imagens Docker..."
    $COMPOSE build --no-cache

    log "Subindo containers..."
    $COMPOSE up -d

    log "Aguardando MySQL ficar pronto..."
    sleep 10

    log "Rodando migrations..."
    $COMPOSE exec -T $APP_CONTAINER php artisan migrate --force

    log "Rodando seeders (admin padrão)..."
    $COMPOSE exec -T $APP_CONTAINER php artisan db:seed --force

    log "Criando link de storage..."
    $COMPOSE exec -T $APP_CONTAINER php artisan storage:link

    log "Gerando chave de aplicação (se vazia)..."
    APP_KEY=$(grep "^APP_KEY=" .env | cut -d'=' -f2-)
    if [ -z "$APP_KEY" ]; then
        $COMPOSE exec -T $APP_CONTAINER php artisan key:generate --force
    fi

    log "Otimizando para produção..."
    $COMPOSE exec -T $APP_CONTAINER php artisan config:cache
    $COMPOSE exec -T $APP_CONTAINER php artisan route:cache
    $COMPOSE exec -T $APP_CONTAINER php artisan view:cache

    ok "Deploy concluído!"
    cmd_status
}

# ----------------------------------------------------------------
# Atualização (pull + rebuild + migrate)
# ----------------------------------------------------------------
cmd_update() {
    log "Atualizando aplicação..."
    check_requirements

    log "Baixando código mais recente..."
    git pull origin main 2>/dev/null || warn "git pull falhou — verifique manualmente"

    log "Reconstruindo imagens alteradas..."
    $COMPOSE build

    log "Reiniciando containers..."
    $COMPOSE up -d --remove-orphans

    log "Aguardando app subir..."
    sleep 5

    log "Rodando migrations..."
    $COMPOSE exec -T $APP_CONTAINER php artisan migrate --force

    log "Limpando e recriando caches..."
    $COMPOSE exec -T $APP_CONTAINER php artisan config:cache
    $COMPOSE exec -T $APP_CONTAINER php artisan route:cache
    $COMPOSE exec -T $APP_CONTAINER php artisan view:cache

    ok "Atualização concluída!"
    cmd_status
}

# ----------------------------------------------------------------
# Só migrations
# ----------------------------------------------------------------
cmd_migrate() {
    log "Rodando migrations..."
    $COMPOSE exec -T $APP_CONTAINER php artisan migrate --force
    ok "Migrations concluídas"
}

# ----------------------------------------------------------------
# Logs
# ----------------------------------------------------------------
cmd_logs() {
    SERVICE="${2:-}"
    if [ -n "$SERVICE" ]; then
        $COMPOSE logs -f "$SERVICE"
    else
        $COMPOSE logs -f app queue automacao
    fi
}

# ----------------------------------------------------------------
# Status
# ----------------------------------------------------------------
cmd_status() {
    echo ""
    echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
    echo "  Status dos containers"
    echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
    $COMPOSE ps
    echo ""

    # Testa saúde da automação
    if $COMPOSE ps automacao | grep -q "Up"; then
        HEALTH=$($COMPOSE exec -T automacao curl -s http://localhost:8001/health 2>/dev/null || echo '{}')
        if echo "$HEALTH" | grep -q '"ok":true'; then
            ok "API Automação: saudável"
        else
            warn "API Automação: não respondeu (pode estar iniciando)"
        fi
    fi
}

# ----------------------------------------------------------------
# Parar tudo
# ----------------------------------------------------------------
cmd_down() {
    log "Parando todos os containers..."
    $COMPOSE down
    ok "Containers parados"
}

# ----------------------------------------------------------------
# Backup do banco
# ----------------------------------------------------------------
cmd_backup() {
    TIMESTAMP=$(date +%Y%m%d_%H%M%S)
    BACKUP_FILE="backup_${TIMESTAMP}.sql.gz"

    log "Criando backup: $BACKUP_FILE"
    DB_USER=$(grep "^DB_USERNAME=" .env | cut -d'=' -f2-)
    DB_PASS=$(grep "^DB_PASSWORD=" .env | cut -d'=' -f2-)
    DB_NAME=$(grep "^DB_DATABASE=" .env | cut -d'=' -f2-)

    $COMPOSE exec -T mysql mysqldump -u"$DB_USER" -p"$DB_PASS" "$DB_NAME" | gzip > "$BACKUP_FILE"
    ok "Backup salvo em: $BACKUP_FILE"
}

# ----------------------------------------------------------------
# Artisan helper
# ----------------------------------------------------------------
cmd_artisan() {
    shift
    $COMPOSE exec $APP_CONTAINER php artisan "$@"
}

# ----------------------------------------------------------------
# Main
# ----------------------------------------------------------------
COMMAND="${1:-help}"

case "$COMMAND" in
    install)  cmd_install ;;
    update)   cmd_update ;;
    migrate)  cmd_migrate ;;
    logs)     cmd_logs "$@" ;;
    status)   cmd_status ;;
    down)     cmd_down ;;
    backup)   cmd_backup ;;
    artisan)  cmd_artisan "$@" ;;
    *)
        echo ""
        echo "Uso: bash deploy.sh <comando>"
        echo ""
        echo "Comandos disponíveis:"
        echo "  install   — Primeiro deploy (build + migrate + seed)"
        echo "  update    — Atualiza código e reinicia (git pull + migrate)"
        echo "  migrate   — Roda apenas as migrations"
        echo "  status    — Mostra status dos containers"
        echo "  logs      — Acompanha logs (add nome do serviço para filtrar)"
        echo "  down      — Para todos os containers"
        echo "  backup    — Faz backup do banco de dados"
        echo "  artisan   — Executa php artisan no container app"
        echo ""
        echo "Exemplos:"
        echo "  bash deploy.sh install"
        echo "  bash deploy.sh logs queue"
        echo "  bash deploy.sh artisan tinker"
        echo ""
        ;;
esac
