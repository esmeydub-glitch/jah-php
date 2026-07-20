#!/usr/bin/env bash

set -Eeuo pipefail

# JAH MemoryAgent — automated deployment for Alibaba Cloud Linux 3 ECS.
# Run as root from the cloned repository:
#   chmod 755 deploy_alibaba_ecs.sh
#   ./deploy_alibaba_ecs.sh
#
# QWEN_API_KEY and JAH_API_KEY are requested with hidden input. They are
# written only to the ignored .env file and are never printed by this script.

PORT="${JAH_PORT:-8000}"
SERVICE_NAME="jah-memoryagent"
APP_DIR="$(cd -- "$(dirname -- "${BASH_SOURCE[0]}")" && pwd)"
ENV_FILE="$APP_DIR/.env"
RUNTIME_DIR="$APP_DIR/runtime"
DEPLOYMENT_DIR="$RUNTIME_DIR/deployment"
AUTH_CONFIG=""
QWEN_KEY=""
JAH_KEY=""

cleanup() {
    QWEN_KEY=""
    JAH_KEY=""
    if [[ -n "$AUTH_CONFIG" && -f "$AUTH_CONFIG" ]]; then
        rm -f -- "$AUTH_CONFIG"
    fi
}
trap cleanup EXIT

log() { printf '\n\033[1;32m==> %s\033[0m\n' "$1"; }
die() { printf '\nERROR: %s\n' "$1" >&2; exit 1; }

require_ecs_root() {
    [[ "$EUID" -eq 0 ]] || die "Ejecuta este script como root."
    command -v dnf >/dev/null 2>&1 || die "Se requiere Alibaba Cloud Linux 3 con dnf."
    [[ -f "$APP_DIR/public/api.php" ]] || die "Ejecuta el script desde el clon de jah-php."
    [[ -f "$APP_DIR/.env.example" ]] || die "Falta .env.example."
    if git -C "$APP_DIR" rev-parse --is-inside-work-tree >/dev/null 2>&1; then
        git -C "$APP_DIR" check-ignore -q .env \
            || die ".env no está ignorado por Git; se cancela para proteger las claves."
    fi
}

configure_remi_php82_repo() {
    # Alibaba Cloud Linux 3 is EL8-compatible, but its system-release does not
    # satisfy the dependency declared by remi-release-8.rpm. Configure only the
    # required signed repositories directly instead of forcing that RPM.
    local repo_file="/etc/yum.repos.d/jah-remi-php82.repo"
    cat > "$repo_file" <<'REPO'
[jah-remi-safe]
name=Remi Safe for JAH (Enterprise Linux 8)
baseurl=https://rpms.remirepo.net/enterprise/8/safe/$basearch/
enabled=1
gpgcheck=1
gpgkey=https://rpms.remirepo.net/RPM-GPG-KEY-remi2018

[jah-remi-php82]
name=Remi PHP 8.2 for JAH (Enterprise Linux 8)
baseurl=https://rpms.remirepo.net/enterprise/8/php82/$basearch/
enabled=1
gpgcheck=1
gpgkey=https://rpms.remirepo.net/RPM-GPG-KEY-remi2018
module_hotfixes=1
REPO
    # Remi signs Enterprise Linux 8 packages with its official 2018 key.
    rpm --import https://rpms.remirepo.net/RPM-GPG-KEY-remi2018
    dnf clean metadata
}

install_dependencies() {
    log "Instalando herramientas y PHP 8.2"
    dnf install -y git curl unzip yum-utils

    local needs_php=yes
    if command -v php >/dev/null 2>&1 \
        && php -r 'exit(version_compare(PHP_VERSION, "8.1.0", ">=") ? 0 : 1);'; then
        needs_php=no
    fi

    if [[ "$needs_php" == yes ]]; then
        configure_remi_php82_repo
    fi

    dnf install -y --allowerasing php php-cli php-common php-mbstring php-process

    php -r 'exit(version_compare(PHP_VERSION, "8.1.0", ">=") ? 0 : 1);' \
        || die "JAH requiere PHP 8.1 o superior."
    php -m | grep -qi '^curl$' || die "La extensión PHP cURL no está activa."
    php -m | grep -qi '^zlib$' || die "La extensión PHP zlib no está activa."

    php -v
    php -m | grep -Ei '^(curl|zlib|mbstring)$' || true
    git --version
    curl --version | sed -n '1p'
}

env_value() {
    local key="$1"
    [[ -f "$ENV_FILE" ]] || return 0
    awk -F= -v wanted="$key" '$1 == wanted {sub(/^[^=]*=/, ""); print; exit}' "$ENV_FILE"
}

set_env_value() {
    local key="$1" value="$2" tmp
    tmp="$(mktemp "$APP_DIR/.env.tmp.XXXXXX")"
    awk -F= -v wanted="$key" '$1 != wanted {print}' "$ENV_FILE" > "$tmp"
    printf '%s=%s\n' "$key" "$value" >> "$tmp"
    chmod 600 "$tmp"
    mv -f -- "$tmp" "$ENV_FILE"
}

read_hidden() {
    local prompt="$1" target="$2" value=""
    while [[ -z "$value" ]]; do
        read -r -s -p "$prompt" value
        printf '\n'
        [[ "$value" != *$'\n'* && "$value" != *$'\r'* ]] || value=""
    done
    printf -v "$target" '%s' "$value"
}

configure_environment() {
    log "Configurando .env con entrada oculta"
    [[ -f "$ENV_FILE" ]] || cp "$APP_DIR/.env.example" "$ENV_FILE"
    chmod 600 "$ENV_FILE"

    QWEN_KEY="$(env_value QWEN_API_KEY)"
    if [[ -z "$QWEN_KEY" ]]; then
        read_hidden "Pega tu QWEN_API_KEY (entrada oculta): " QWEN_KEY
        set_env_value QWEN_API_KEY "$QWEN_KEY"
    else
        printf 'QWEN_API_KEY existente: ******** (se reutiliza)\n'
    fi

    JAH_KEY="$(env_value JAH_API_KEY)"
    if [[ -z "$JAH_KEY" ]]; then
        while true; do
            read_hidden "Crea una JAH_API_KEY de al menos 16 caracteres (entrada oculta): " JAH_KEY
            if [[ ${#JAH_KEY} -ge 16 && "$JAH_KEY" =~ ^[A-Za-z0-9._~-]+$ ]]; then
                break
            fi
            printf 'Usa letras, números, punto, guion, guion bajo o ~.\n'
            JAH_KEY=""
        done
        set_env_value JAH_API_KEY "$JAH_KEY"
    else
        printf 'JAH_API_KEY existente: ******** (se reutiliza)\n'
    fi

    set_env_value QWEN_MODEL qwen-max
    set_env_value JAH_ENV production
    set_env_value JAH_DEBUG false
    printf 'QWEN_MODEL=qwen-max\nJAH_ENV=production\nJAH_DEBUG=false\n'
    printf 'QWEN_API_KEY=********\nJAH_API_KEY=********\n'
    ls -l "$ENV_FILE"
}

prepare_runtime() {
    log "Preparando almacenamiento persistente"
    mkdir -p "$RUNTIME_DIR" "$DEPLOYMENT_DIR"
    chmod 775 "$RUNTIME_DIR" "$DEPLOYMENT_DIR"
}

validate_and_test() {
    log "Validando sintaxis PHP"
    find "$APP_DIR/app" "$APP_DIR/public" "$APP_DIR/src" \
        "$APP_DIR/php_actionscript_php_doc" "$APP_DIR/tests" \
        -type f -name '*.php' -print0 | xargs -0 -n1 php -l

    log "Ejecutando pruebas JAH MemoryAgent"
    php "$APP_DIR/tests/run.php" | tee "$DEPLOYMENT_DIR/product-tests.txt"
    grep -q 'SUMMARY 18/18' "$DEPLOYMENT_DIR/product-tests.txt" \
        || die "La suite principal no terminó con 18/18."

    log "Ejecutando pruebas ActionScript PHP"
    php "$APP_DIR/php_actionscript_php_doc/tests/run.php" \
        | tee "$DEPLOYMENT_DIR/actionscript-tests.txt"
    grep -q 'SUMMARY 7/7' "$DEPLOYMENT_DIR/actionscript-tests.txt" \
        || die "La suite ActionScript no terminó con 7/7."

    log "Ejecutando ejemplos ActionScript"
    php "$APP_DIR/php_actionscript_php_doc/examples/actions.php" \
        | tee "$DEPLOYMENT_DIR/actionscript-actions.txt"
    php "$APP_DIR/php_actionscript_php_doc/examples/scheduler.php" \
        | tee "$DEPLOYMENT_DIR/actionscript-scheduler.txt"
}

install_service() {
    log "Creando servicio systemd en 0.0.0.0:${PORT}"
    local php_bin unit
    php_bin="$(command -v php)"
    unit="/etc/systemd/system/${SERVICE_NAME}.service"

    {
        printf '[Unit]\nDescription=JAH MemoryAgent on Alibaba Cloud ECS\n'
        printf 'After=network-online.target\nWants=network-online.target\n\n'
        printf '[Service]\nType=simple\nWorkingDirectory=%s\n' "$APP_DIR"
        printf 'ExecStart=%s -S 0.0.0.0:%s -t %s/public\n' "$php_bin" "$PORT" "$APP_DIR"
        printf 'Restart=on-failure\nRestartSec=3\nNoNewPrivileges=true\n'
        printf 'PrivateTmp=true\nProtectSystem=full\nReadWritePaths=%s/runtime\n\n' "$APP_DIR"
        printf '[Install]\nWantedBy=multi-user.target\n'
    } > "$unit"

    systemctl daemon-reload
    systemctl enable --now "$SERVICE_NAME"
    sleep 2
    systemctl is-active --quiet "$SERVICE_NAME" \
        || { systemctl status "$SERVICE_NAME" --no-pager -l; die "El servicio no inició."; }
    systemctl status "$SERVICE_NAME" --no-pager -l | sed -n '1,18p'
}

configure_network_access() {
    log "Manteniendo ${PORT}/tcp cerrado al acceso público"
    if systemctl is-active --quiet firewalld; then
        firewall-cmd --permanent --remove-port="${PORT}/tcp" 2>/dev/null || true
        firewall-cmd --reload
        firewall-cmd --list-ports
    else
        printf 'firewalld no está activo.\n'
    fi
    printf 'No agregues TCP %s al Security Group de ECS.\n' "$PORT"
    printf 'Acceso recomendado desde tu equipo: ssh -N -L %s:127.0.0.1:%s root@IP_PUBLICA_ECS\n' "$PORT" "$PORT"
}

create_auth_config() {
    AUTH_CONFIG="$(mktemp /tmp/jah-curl-auth.XXXXXX)"
    chmod 600 "$AUTH_CONFIG"
    printf 'header = "X-JAH-API-Key: %s"\n' "$JAH_KEY" > "$AUTH_CONFIG"
}

api_get() { curl -sS --fail --config "$AUTH_CONFIG" "$1"; }

api_post() {
    local output="$1"
    shift
    curl -sS --fail --config "$AUTH_CONFIG" -X POST "$@" \
        "http://127.0.0.1:${PORT}/api.php" > "$output"
}

run_live_demo() {
    log "Probando estado, estadísticas y SALK"
    create_auth_config
    api_get "http://127.0.0.1:${PORT}/api.php?action=status" \
        | tee "$DEPLOYMENT_DIR/status.txt"
    api_get "http://127.0.0.1:${PORT}/api.php?action=stats&collection=deployment-proof" \
        | tee "$DEPLOYMENT_DIR/stats-before.txt"
    api_get "http://127.0.0.1:${PORT}/api.php?action=salk_status" \
        | tee "$DEPLOYMENT_DIR/salk-status.txt"

    log "Sesión 1: acumulando memoria con Qwen"
    api_post "$DEPLOYMENT_DIR/chat-session-1.txt" \
        --data-urlencode 'action=chat' \
        --data-urlencode 'collection=deployment-proof' \
        --data-urlencode 'conversation_id=ecs-session-1' \
        --data-urlencode 'message=Recuerda permanentemente que JAH MemoryAgent está ejecutándose en una instancia Alibaba Cloud ECS con PHP 8.2 y Qwen Cloud.'
    grep -E '^(status|model|response|context_used|conversation_used|stored\.)' \
        "$DEPLOYMENT_DIR/chat-session-1.txt" || true

    log "Sesión 2: recuperando memoria entre sesiones"
    api_post "$DEPLOYMENT_DIR/chat-session-2.txt" \
        --data-urlencode 'action=chat' \
        --data-urlencode 'collection=deployment-proof' \
        --data-urlencode 'conversation_id=ecs-session-2' \
        --data-urlencode 'message=¿Qué recuerdas sobre Alibaba Cloud ECS y dónde está ejecutándose JAH?'
    grep -E '^(status|model|response|context_used|conversation_used|stored\.)' \
        "$DEPLOYMENT_DIR/chat-session-2.txt" || true

    log "Guardando y recuperando una memoria Cold"
    api_post "$DEPLOYMENT_DIR/cold-save.txt" \
        --data-urlencode 'action=save' \
        --data-urlencode 'collection=deployment-proof' \
        --data-urlencode 'id=alibaba-ecs-deployment' \
        --data-urlencode 'content=JAH MemoryAgent was deployed and tested on Alibaba Cloud ECS using PHP 8.2 and Qwen Cloud.' \
        --data-urlencode 'tier=cold'
    cat "$DEPLOYMENT_DIR/cold-save.txt"
    api_get "http://127.0.0.1:${PORT}/api.php?action=retrieve&collection=deployment-proof&id=alibaba-ecs-deployment" \
        | tee "$DEPLOYMENT_DIR/cold-retrieve.txt"
    api_get "http://127.0.0.1:${PORT}/api.php?action=search&collection=deployment-proof&query=Alibaba%20Cloud%20ECS" \
        | tee "$DEPLOYMENT_DIR/search.txt"
    api_get "http://127.0.0.1:${PORT}/api.php?action=stats&collection=deployment-proof" \
        | tee "$DEPLOYMENT_DIR/stats-after.txt"
}

create_cli() {
    log "Creando CLI interactivo"
    local cli="$DEPLOYMENT_DIR/jah_chat_cli.sh"
    {
        printf '#!/usr/bin/env bash\nset -Eeuo pipefail\n'
        printf 'APP_DIR=%q\nPORT=%q\n' "$APP_DIR" "$PORT"
        cat <<'CLI'
JKEY="$(awk -F= '$1 == "JAH_API_KEY" {sub(/^[^=]*=/, ""); print; exit}' "$APP_DIR/.env")"
AUTH="$(mktemp /tmp/jah-cli-auth.XXXXXX)"
trap 'rm -f "$AUTH"; unset JKEY' EXIT
chmod 600 "$AUTH"
printf 'header = "X-JAH-API-Key: %s"\n' "$JKEY" > "$AUTH"
echo "JAH MemoryAgent CLI — escribe salir para terminar"
while true; do
    read -r -p 'tu> ' MESSAGE || break
    [[ "$MESSAGE" == salir || "$MESSAGE" == exit || "$MESSAGE" == quit ]] && break
    curl -sS --config "$AUTH" -X POST \
        --data-urlencode 'action=chat' \
        --data-urlencode 'collection=deployment-proof' \
        --data-urlencode 'conversation_id=ecs-interactive-cli' \
        --data-urlencode "message=$MESSAGE" \
        "http://127.0.0.1:${PORT}/api.php"
done
CLI
    } > "$cli"
    chmod 700 "$cli"
    bash -n "$cli"
}

show_evidence() {
    log "Archivos persistentes y auditoría"
    find "$RUNTIME_DIR" -maxdepth 5 -type f | sort
    if [[ -f "$RUNTIME_DIR/security/salk_audit.jahl" ]]; then
        tail -n 15 "$RUNTIME_DIR/security/salk_audit.jahl"
    fi

    log "Creando reporte de despliegue sin secretos"
    local report="$DEPLOYMENT_DIR/alibaba-ecs-proof.txt"
    {
        printf 'JAH MemoryAgent — Alibaba Cloud ECS Deployment Proof\nGenerated UTC: '
        date -u '+%Y-%m-%dT%H:%M:%SZ'
        printf '\n=== HOST ===\n'; hostnamectl; uname -a
        printf '\n=== PHP ===\n'; php -v
        php -m | grep -Ei '^(curl|zlib|mbstring)$' || true
        printf '\n=== SOURCE ===\nRepository: https://github.com/esmeydub/jah-php.git\n'
        printf 'Commit: '; git -C "$APP_DIR" rev-parse HEAD
        printf '\n=== SERVICE ===\n'
        systemctl is-active "$SERVICE_NAME"
        systemctl is-enabled "$SERVICE_NAME"
        ss -ltnp | grep ":${PORT}" || true
        printf '\n=== TESTS ===\n'
        grep 'SUMMARY' "$DEPLOYMENT_DIR/product-tests.txt"
        grep 'SUMMARY' "$DEPLOYMENT_DIR/actionscript-tests.txt"
    } > "$report"
    chmod 644 "$report"
    cat "$report"
}

finish() {
    log "Despliegue terminado"
    printf 'Interfaz: http://IP_PUBLICA_ECS:%s/index.php\n' "$PORT"
    printf 'Reporte: %s/alibaba-ecs-proof.txt\n' "$DEPLOYMENT_DIR"
    printf 'Chat CLI: %s/jah_chat_cli.sh\n' "$DEPLOYMENT_DIR"
    printf 'Estado: systemctl status %s --no-pager -l\n' "$SERVICE_NAME"
    printf 'Logs: journalctl -u %s -n 100 --no-pager -l\n' "$SERVICE_NAME"
    printf 'Security Group: mantén TCP %s cerrado.\n' "$PORT"
    printf 'Túnel SSH: ssh -N -L %s:127.0.0.1:%s root@IP_PUBLICA_ECS\n' "$PORT" "$PORT"
    printf 'Navegador local: http://127.0.0.1:%s/index.php\n' "$PORT"
}

main() {
    require_ecs_root
    install_dependencies
    configure_environment
    prepare_runtime
    validate_and_test
    install_service
    configure_network_access
    run_live_demo
    create_cli
    show_evidence
    finish
}

main "$@"
