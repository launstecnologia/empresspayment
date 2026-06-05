#!/usr/bin/env bash
# setup.sh — Configuração da automação PagBank no VPS (Ubuntu/Debian)
# Executar como root ou com sudo:  bash setup.sh

set -euo pipefail

AUTOMACAO_DIR="/var/www/automacao"
SERVICE_FILE="/etc/systemd/system/automacao-pagbank.service"
PYTHON_BIN="python3"
PORT=8001

echo "================================================"
echo "  Configuração — Automação PagBank FV"
echo "================================================"

# ----------------------------------------------------------------
# 1. Dependências do sistema
# ----------------------------------------------------------------
echo "[1/6] Atualizando sistema e instalando dependências..."
apt-get update -qq
apt-get install -y -qq \
    python3 python3-pip python3-venv \
    wget curl unzip gnupg2 \
    libglib2.0-0 libnss3 libatk1.0-0 libatk-bridge2.0-0 \
    libcups2 libdrm2 libgbm1 libxkbcommon0 \
    libx11-xcb1 libxcomposite1 libxdamage1 libxfixes3 \
    libxrandr2 libpango-1.0-0 libcairo2 libasound2

# ----------------------------------------------------------------
# 2. Google Chrome (headless)
# ----------------------------------------------------------------
echo "[2/6] Instalando Google Chrome..."
if ! command -v google-chrome-stable &>/dev/null; then
    wget -q https://dl.google.com/linux/direct/google-chrome-stable_current_amd64.deb
    apt-get install -y -qq ./google-chrome-stable_current_amd64.deb
    rm -f google-chrome-stable_current_amd64.deb
    echo "Chrome instalado: $(google-chrome-stable --version)"
else
    echo "Chrome já instalado: $(google-chrome-stable --version)"
fi

# ----------------------------------------------------------------
# 3. Diretório e virtualenv
# ----------------------------------------------------------------
echo "[3/6] Preparando diretório da automação..."
mkdir -p "$AUTOMACAO_DIR"
mkdir -p "$AUTOMACAO_DIR/screenshots"

# Copia arquivos (assume que já estão em /tmp/automacao ou ajuste conforme necessário)
# cp -r /tmp/automacao/* "$AUTOMACAO_DIR/"

cd "$AUTOMACAO_DIR"

if [ ! -d "venv" ]; then
    $PYTHON_BIN -m venv venv
    echo "Virtualenv criado"
fi

source venv/bin/activate
pip install --upgrade pip -q
pip install -r requirements.txt -q
deactivate

echo "Dependências Python instaladas"

# ----------------------------------------------------------------
# 4. Arquivo .env
# ----------------------------------------------------------------
echo "[4/6] Configurando .env..."
if [ ! -f "$AUTOMACAO_DIR/.env" ]; then
    cp "$AUTOMACAO_DIR/.env.example" "$AUTOMACAO_DIR/.env"
    # Gera chave aleatória
    NOVA_CHAVE=$(openssl rand -hex 32)
    sed -i "s/troque-me-por-chave-segura/$NOVA_CHAVE/" "$AUTOMACAO_DIR/.env"
    echo ""
    echo "  ATENÇÃO: .env criado com chave gerada automaticamente."
    echo "  Copie a AUTOMACAO_API_KEY para o .env do Laravel:"
    echo ""
    echo "  AUTOMACAO_API_KEY=$NOVA_CHAVE"
    echo ""
else
    echo ".env já existe — não foi sobrescrito"
fi

# ----------------------------------------------------------------
# 5. Serviço systemd
# ----------------------------------------------------------------
echo "[5/6] Configurando serviço systemd..."
cat > "$SERVICE_FILE" << EOF
[Unit]
Description=Automação PagBank — Força de Vendas API
After=network.target

[Service]
Type=simple
User=www-data
WorkingDirectory=$AUTOMACAO_DIR
Environment="PATH=$AUTOMACAO_DIR/venv/bin"
EnvironmentFile=$AUTOMACAO_DIR/.env
ExecStart=$AUTOMACAO_DIR/venv/bin/uvicorn api:app --host 127.0.0.1 --port $PORT --workers 1
Restart=always
RestartSec=5
StandardOutput=journal
StandardError=journal

[Install]
WantedBy=multi-user.target
EOF

# Permissões corretas
chown -R www-data:www-data "$AUTOMACAO_DIR"
chmod 600 "$AUTOMACAO_DIR/.env"

systemctl daemon-reload
systemctl enable automacao-pagbank
systemctl restart automacao-pagbank

# ----------------------------------------------------------------
# 6. Verificação
# ----------------------------------------------------------------
echo "[6/6] Verificando serviço..."
sleep 3

if systemctl is-active --quiet automacao-pagbank; then
    echo ""
    echo "================================================"
    echo "  ✔  Automação rodando na porta $PORT"
    echo "================================================"
    echo ""
    echo "  Comandos úteis:"
    echo "  - Status:  systemctl status automacao-pagbank"
    echo "  - Logs:    journalctl -u automacao-pagbank -f"
    echo "  - Parar:   systemctl stop automacao-pagbank"
    echo ""
    echo "  Teste de saúde:"
    echo "  curl http://127.0.0.1:$PORT/health"
    echo ""
else
    echo "ERRO: serviço não iniciou. Verifique: journalctl -u automacao-pagbank -n 50"
    exit 1
fi
