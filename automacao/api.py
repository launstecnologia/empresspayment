# api.py
# API REST para automação PagBank — Força de Vendas
# Expõe endpoints para o Laravel acionar e acompanhar os jobs de automação.
#
# Iniciar:  uvicorn api:app --host 0.0.0.0 --port 8001 --workers 1
# Prod:     gunicorn api:app -k uvicorn.workers.UvicornWorker --bind 0.0.0.0:8001 --workers 1

import json
import logging
import os
import sqlite3
import threading
import uuid
from datetime import datetime

from dotenv import load_dotenv
from fastapi import FastAPI, Header, HTTPException
from fastapi.responses import JSONResponse
from pydantic import BaseModel, Field

load_dotenv()

log = logging.getLogger('automacao_api')
logging.basicConfig(
    level=logging.INFO,
    format='%(asctime)s [%(levelname)s] %(message)s',
    datefmt='%H:%M:%S',
)

API_KEY       = os.getenv('AUTOMACAO_API_KEY', 'troque-me')
DB_PATH       = os.getenv('AUTOMACAO_DB_PATH', '/tmp/automacao_jobs.db')
SCREENSHOT_DIR = os.getenv('AUTOMACAO_SCREENSHOT_DIR', '/tmp/automacao_screenshots')

app = FastAPI(
    title='Automação PagBank FV',
    description='API para cadastro automatizado na Força de Vendas PagBank via Selenium.',
    version='1.0.0',
    docs_url='/docs',
)

# ----------------------------------------------------------------
# Banco SQLite simples para persistência dos jobs
# ----------------------------------------------------------------
def _get_conn() -> sqlite3.Connection:
    conn = sqlite3.connect(DB_PATH, check_same_thread=False)
    conn.row_factory = sqlite3.Row
    return conn


def _init_db() -> None:
    with _get_conn() as conn:
        conn.execute('''
            CREATE TABLE IF NOT EXISTS jobs (
                id                  TEXT PRIMARY KEY,
                estabelecimento_id  INTEGER,
                status              TEXT NOT NULL DEFAULT "pendente",
                dados               TEXT,
                resultado           TEXT,
                erro                TEXT,
                criado_em           TEXT NOT NULL,
                atualizado_em       TEXT NOT NULL
            )
        ''')
        conn.commit()


_init_db()
_db_lock = threading.Lock()


def _update_job(job_id: str, status: str,
                resultado: dict | None = None,
                erro: str | None = None) -> None:
    with _db_lock:
        with _get_conn() as conn:
            conn.execute(
                'UPDATE jobs SET status=?, resultado=?, erro=?, atualizado_em=? WHERE id=?',
                (
                    status,
                    json.dumps(resultado, ensure_ascii=False) if resultado else None,
                    erro,
                    datetime.now().isoformat(),
                    job_id,
                ),
            )
            conn.commit()


# ----------------------------------------------------------------
# Schemas
# ----------------------------------------------------------------
class CadastrarRequest(BaseModel):
    estabelecimento_id: int = Field(..., description='ID do estabelecimento no Laravel')
    dados: dict = Field(..., description='Dados do estabelecimento para a Força de Vendas')
    fv_usuario: str = Field(..., description='Usuário de acesso ao portal FV PagBank')
    fv_senha: str = Field(..., description='Senha do portal FV PagBank')
    webmail_url: str = Field(..., description='URL do Roundcube (ex: https://mail.seudominio.com.br)')
    webmail_usuario: str = Field(..., description='Email para login no webmail')
    webmail_senha: str = Field(..., description='Senha do webmail')
    senha_6: str = Field(..., min_length=6, max_length=6, description='Senha 6 dígitos para conta PagBank')
    headless: bool = Field(default=True, description='Rodar Chrome em modo headless')
    aguardar_email_seg: int = Field(default=90, description='Segundos para aguardar o email de confirmação')


# ----------------------------------------------------------------
# Autenticação
# ----------------------------------------------------------------
def _autenticar(x_api_key: str) -> None:
    if x_api_key != API_KEY:
        raise HTTPException(status_code=401, detail='Chave de API inválida')


# ----------------------------------------------------------------
# Lógica de execução (roda em thread separada)
# ----------------------------------------------------------------
def _executar_job(job_id: str, req: dict) -> None:
    screenshot_dir = os.path.join(SCREENSHOT_DIR, job_id)
    os.makedirs(screenshot_dir, exist_ok=True)

    try:
        _update_job(job_id, 'em_andamento')

        # --- Etapa 1: Cadastro na Força de Vendas ---
        log.info(f'[{job_id}] Iniciando cadastro FV para estab {req["estabelecimento_id"]}')
        from main import cadastrar_fv

        fv_resultado = cadastrar_fv(
            dados=req['dados'],
            fv_usuario=req['fv_usuario'],
            fv_senha=req['fv_senha'],
            headless=req.get('headless', True),
            screenshot_dir=screenshot_dir,
        )

        if not fv_resultado.get('sucesso'):
            _update_job(
                job_id, 'erro',
                resultado={'etapa': 'cadastro_fv', 'detalhe': fv_resultado},
                erro=fv_resultado.get('erro', 'Erro desconhecido no cadastro FV'),
            )
            return

        log.info(f'[{job_id}] Cadastro FV concluído — aguardando email...')

        # --- Etapa 2: Validação de email e criação de senha ---
        from validacao_email import validar_email

        email_resultado = validar_email(
            webmail_url=req['webmail_url'],
            webmail_usuario=req['webmail_usuario'],
            webmail_senha=req['webmail_senha'],
            senha_6=req['senha_6'],
            headless=req.get('headless', True),
            screenshot_dir=screenshot_dir,
            aguardar_email_seg=req.get('aguardar_email_seg', 90),
        )

        resultado_final = {
            'etapa_fv': fv_resultado,
            'etapa_email': email_resultado,
            'senha_6': req['senha_6'],
        }

        if email_resultado.get('sucesso'):
            _update_job(job_id, 'concluido', resultado=resultado_final)
            log.info(f'[{job_id}] Job concluído com sucesso!')
        else:
            _update_job(
                job_id, 'erro_email',
                resultado=resultado_final,
                erro=email_resultado.get('erro', 'Erro na validação de email'),
            )

    except Exception as e:
        log.exception(f'[{job_id}] Erro inesperado')
        _update_job(job_id, 'erro', erro=str(e))


# ----------------------------------------------------------------
# Endpoints
# ----------------------------------------------------------------
@app.get('/health', tags=['Sistema'])
async def health():
    """Verificação de disponibilidade da API."""
    return {'ok': True, 'timestamp': datetime.now().isoformat()}


@app.post('/cadastrar', tags=['Automação'], status_code=202)
async def iniciar_cadastro(request: CadastrarRequest, x_api_key: str = Header(...)):
    """
    Inicia um job de cadastro assíncrono na Força de Vendas PagBank.
    Retorna o `job_id` para acompanhar via GET /status/{job_id}.
    """
    _autenticar(x_api_key)

    # Evita job duplicado para o mesmo estabelecimento em andamento
    with _db_lock:
        with _get_conn() as conn:
            em_andamento = conn.execute(
                "SELECT id FROM jobs WHERE estabelecimento_id=? AND status IN ('pendente','em_andamento')",
                (request.estabelecimento_id,),
            ).fetchone()

    if em_andamento:
        return JSONResponse(
            status_code=409,
            content={
                'detail': 'Já existe um job em andamento para este estabelecimento.',
                'job_id': em_andamento['id'],
            },
        )

    job_id = str(uuid.uuid4())
    agora  = datetime.now().isoformat()

    with _db_lock:
        with _get_conn() as conn:
            conn.execute(
                'INSERT INTO jobs (id, estabelecimento_id, status, dados, criado_em, atualizado_em)'
                ' VALUES (?, ?, ?, ?, ?, ?)',
                (job_id, request.estabelecimento_id,
                 'pendente', json.dumps(request.model_dump(), ensure_ascii=False),
                 agora, agora),
            )
            conn.commit()

    thread = threading.Thread(
        target=_executar_job,
        args=(job_id, request.model_dump()),
        daemon=True,
    )
    thread.start()

    log.info(f'Job {job_id} criado para estab {request.estabelecimento_id}')
    return {'job_id': job_id, 'status': 'pendente'}


@app.get('/status/{job_id}', tags=['Automação'])
async def consultar_status(job_id: str, x_api_key: str = Header(...)):
    """
    Retorna o status atual de um job.

    Valores de `status`:
    - `pendente`     — aguardando início
    - `em_andamento` — automation em execução
    - `concluido`    — cadastro + email + senha concluídos com sucesso
    - `erro`         — falha no cadastro FV
    - `erro_email`   — cadastro FV ok, mas falha no email/senha
    """
    _autenticar(x_api_key)

    with _get_conn() as conn:
        row = conn.execute('SELECT * FROM jobs WHERE id=?', (job_id,)).fetchone()

    if not row:
        raise HTTPException(status_code=404, detail='Job não encontrado')

    return {
        'job_id':             row['id'],
        'estabelecimento_id': row['estabelecimento_id'],
        'status':             row['status'],
        'resultado':          json.loads(row['resultado']) if row['resultado'] else None,
        'erro':               row['erro'],
        'criado_em':          row['criado_em'],
        'atualizado_em':      row['atualizado_em'],
    }


@app.get('/jobs', tags=['Administração'])
async def listar_jobs(
    status: str | None = None,
    limite: int = 50,
    x_api_key: str = Header(...),
):
    """Lista jobs recentes (uso administrativo)."""
    _autenticar(x_api_key)

    query = 'SELECT id, estabelecimento_id, status, erro, criado_em, atualizado_em FROM jobs'
    params: list = []

    if status:
        query += ' WHERE status=?'
        params.append(status)

    query += ' ORDER BY criado_em DESC LIMIT ?'
    params.append(limite)

    with _get_conn() as conn:
        rows = conn.execute(query, params).fetchall()

    return [dict(r) for r in rows]
