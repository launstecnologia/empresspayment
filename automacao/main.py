# main.py
# Forca de Vendas PagBank — Cadastrar Cliente
# Aceita dados dinâmicos via parametro (uso pela API) ou DADOS_CLI (uso local)

import json
import re
import sys
import time
import logging
import argparse
import os
from selenium import webdriver
from selenium.webdriver.common.by import By
from selenium.webdriver.support.ui import WebDriverWait
from selenium.webdriver.support import expected_conditions as EC
from selenium.webdriver.chrome.options import Options
from selenium.webdriver.chrome.service import Service
from selenium.common.exceptions import TimeoutException
from webdriver_manager.chrome import ChromeDriverManager

from progresso import reportar as reportar_etapa

logging.basicConfig(
    level=logging.INFO,
    format='%(asctime)s [%(levelname)s] %(message)s',
    datefmt='%H:%M:%S',
)
log = logging.getLogger(__name__)

FV_URL = 'https://gestaocomercial.pagbank.com.br/login'


class ClienteInternoPagBankError(Exception):
    """Lançada quando o CNPJ/CPF pertence aos times internos do PagBank (FV-CDS-01)."""
    pass


class CadastradorFV:
    """Encapsula toda a lógica de cadastro na Força de Vendas."""

    def __init__(self, dados: dict, fv_usuario: str, fv_senha: str,
                 headless: bool = True, screenshot_dir: str = '/tmp/screenshots',
                 job_id: str | None = None):
        self.dados = dados
        self.fv_usuario = fv_usuario
        self.fv_senha = fv_senha
        self.headless = headless
        self.screenshot_dir = screenshot_dir
        self.job_id = job_id
        self.driver = None
        self.wait = None
        self.screenshots: list[str] = []

        os.makedirs(screenshot_dir, exist_ok=True)

    def _etapa(self, mensagem: str) -> None:
        reportar_etapa(self.job_id, mensagem)

    # ----------------------------------------------------------------
    # Execucao principal
    # ----------------------------------------------------------------
    def executar(self) -> dict:
        """Executa o fluxo completo. Retorna dict com resultado."""
        try:
            self._etapa('Iniciando navegador...')
            self.driver = self._iniciar_browser()
            self.wait = WebDriverWait(self.driver, 20)

            self._etapa('Acessando portal Força de Vendas...')
            self._fazer_login()
            self._etapa('Abrindo cadastro de cliente...')
            self._navegar_cadastrar_cliente()
            self._etapa('Preenchendo dados iniciais...')
            self._preencher_dados_iniciais()

            tipo = self._detectar_tipo_cliente()

            if tipo == 'pj':
                log.info('>>> Fluxo PESSOA JURÍDICA (CNPJ)')
                self._etapa('Preenchendo dados da empresa (PJ)...')
                self._preencher_dados_empresa()
                self._etapa('Preenchendo dados do proprietário...')
                self._preencher_dados_proprietario()
            else:
                log.info('>>> Fluxo PESSOA FÍSICA (CPF)')
                self._etapa('Preenchendo dados pessoais (PF)...')
                self._preencher_dados_pf()

            self._etapa('Preenchendo endereço...')
            self._preencher_endereco()
            self._etapa('Preenchendo segmento...')
            self._preencher_segmento()
            self._etapa('Preenchendo condições comerciais...')
            self._preencher_condicoes_comerciais()

            self._etapa('Cadastro PagBank concluído')
            log.info(f'CADASTRO {tipo.upper()} CONCLUÍDO!')
            return {
                'sucesso': True,
                'tipo': tipo,
                'cpf_cnpj': self.dados['cpf_cnpj'],
                'screenshots': self.screenshots,
            }

        except ClienteInternoPagBankError as e:
            return {
                'sucesso': False,
                'erro': f'CLIENTE_INTERNO: {str(e)}',
                'codigo': 'FV-CDS-01',
                'screenshots': self.screenshots,
            }
        except Exception as e:
            log.error(f'ERRO: {str(e)}')
            if self.driver:
                self._salvar_screenshot('erro_fatal')
            return {
                'sucesso': False,
                'erro': str(e),
                'screenshots': self.screenshots,
            }
        finally:
            if self.driver:
                self.driver.quit()
                log.info('Browser fechado')

    # ----------------------------------------------------------------
    # Browser
    # ----------------------------------------------------------------
    def _iniciar_browser(self):
        opcoes = Options()
        # Flags obrigatórias para Docker (sempre ativas)
        opcoes.add_argument('--no-sandbox')
        opcoes.add_argument('--disable-dev-shm-usage')
        opcoes.add_argument('--disable-gpu')
        opcoes.add_argument('--no-zygote')
        opcoes.add_argument('--disable-software-rasterizer')
        opcoes.add_argument('--disable-extensions')

        if self.headless:
            opcoes.add_argument('--headless')

        opcoes.add_argument('--window-size=1366,768')
        opcoes.add_argument('--disable-blink-features=AutomationControlled')
        opcoes.add_experimental_option('excludeSwitches', ['enable-automation'])
        opcoes.add_experimental_option('useAutomationExtension', False)

        service = Service(ChromeDriverManager().install())
        driver = webdriver.Chrome(service=service, options=opcoes)
        driver.execute_script(
            "Object.defineProperty(navigator, 'webdriver', {get: () => undefined})"
        )
        log.info('Browser iniciado')
        return driver

    def _salvar_screenshot(self, nome: str) -> str:
        caminho = os.path.join(self.screenshot_dir, f'{nome}_{int(time.time())}.png')
        self.driver.save_screenshot(caminho)
        self.screenshots.append(caminho)
        log.info(f'Screenshot: {caminho}')
        return caminho

    # ----------------------------------------------------------------
    # Helpers
    # ----------------------------------------------------------------
    def _verificar_erro_cliente_interno(self):
        erros = self.driver.find_elements(
            By.XPATH,
            '//*[@data-testid="error-input"]//h3[contains(text(), "FV-CDS-01")]'
            ' | //*[contains(@id,"feedback-title") and contains(text(), "FV-CDS-01")]'
            ' | //*[contains(text(), "gerenciado pelos times internos")]',
        )
        if erros:
            msg = erros[0].text.strip()
            log.error(f'BLOQUEIO PagBank: {msg}')
            raise ClienteInternoPagBankError(msg)

    def _clicar(self, by, seletor, descricao=''):
        try:
            el = self.wait.until(EC.element_to_be_clickable((by, seletor)))
            self.driver.execute_script('arguments[0].scrollIntoView(true);', el)
            time.sleep(0.3)
            el.click()
            log.info(f'Clicou: {descricao or seletor}')
            return el
        except TimeoutException:
            self._salvar_screenshot(f'erro_click_{descricao}')
            raise Exception(f'Nao encontrou elemento para clicar: {descricao or seletor}')

    def _preencher(self, by, seletor, valor, descricao=''):
        try:
            el = self.wait.until(EC.presence_of_element_located((by, seletor)))
            self.driver.execute_script('arguments[0].scrollIntoView(true);', el)
            time.sleep(0.3)
            el.clear()
            el.send_keys(str(valor))
            log.info(f'Preencheu {descricao or seletor}: {valor}')
            return el
        except TimeoutException:
            self._salvar_screenshot(f'erro_preencher_{descricao}')
            raise Exception(f'Nao encontrou campo: {descricao or seletor}')

    def _preencher_react(self, by, seletor, valor, descricao=''):
        """Preenche campo React disparando evento de input corretamente.
        Simula digitação humana: preenche, apaga última letra e redigita
        para forçar o React a revalidar o campo."""
        from selenium.webdriver.common.keys import Keys
        try:
            el = self.wait.until(EC.presence_of_element_located((by, seletor)))
            self.driver.execute_script('arguments[0].scrollIntoView(true);', el)
            time.sleep(0.3)
            el.clear()
            time.sleep(0.2)
            el.send_keys(str(valor))
            time.sleep(0.3)
            # Apaga última letra e redigita para disparar validação React
            el.send_keys(Keys.BACK_SPACE)
            time.sleep(0.2)
            el.send_keys(str(valor)[-1])
            time.sleep(0.3)
            log.info(f'Preencheu (react) {descricao or seletor}: {valor}')
            return el
        except TimeoutException:
            self._salvar_screenshot(f'erro_preencher_{descricao}')
            raise Exception(f'Nao encontrou campo: {descricao or seletor}')

    def _redigitar_ultimo_char(self, campo, valor: str):
        """Apaga o último caractere e redigita para forçar revalidação do React."""
        from selenium.webdriver.common.keys import Keys
        if not valor:
            return
        campo.send_keys(Keys.END)
        time.sleep(0.2)
        campo.send_keys(Keys.BACK_SPACE)
        time.sleep(0.3)
        campo.send_keys(valor[-1])
        time.sleep(0.3)

    def _coletar_erros(self):
        erros = self.driver.find_elements(By.XPATH, '//*[@data-testid="error-input"]//h3')
        msgs = [e.text.strip() for e in erros if e.text.strip()]
        if msgs:
            log.warning(f'Erros de validacao ({len(msgs)}): {msgs}')
        return msgs

    def _limpar_telefone_fixo(self):
        """Remove telefone fixo — PagBank pré-preenche via CNPJ e costuma invalidar."""
        try:
            from selenium.webdriver.common.keys import Keys
            campo = self.driver.find_element(By.ID, 'info.formattedPhone')
            valor = (campo.get_attribute('value') or '').strip()
            if not valor:
                return
            self.driver.execute_script('arguments[0].scrollIntoView(true);', campo)
            campo.click()
            time.sleep(0.2)
            campo.send_keys(Keys.CONTROL, 'a')
            campo.send_keys(Keys.BACK_SPACE)
            time.sleep(0.3)
            log.info(f'Telefone fixo ignorado (removido valor: {valor})')
        except Exception:
            pass

    def _exigir_sem_erros(self, contexto: str):
        erros = self._coletar_erros()
        if erros:
            self._salvar_screenshot(f'erro_validacao_{contexto}')
            raise Exception(f'Validação PagBank ({contexto}): {", ".join(erros)}')

    def _aguardar_campo_ou_falhar(self, by, seletor: str, contexto: str, timeout: int = 25):
        try:
            WebDriverWait(self.driver, timeout).until(
                EC.presence_of_element_located((by, seletor))
            )
        except TimeoutException:
            erros = self._coletar_erros()
            self._salvar_screenshot(f'timeout_{contexto}')
            detalhe = ', '.join(erros) if erros else f'Campo {seletor} não apareceu'
            raise Exception(f'PagBank não avançou ({contexto}): {detalhe}')

    # ----------------------------------------------------------------
    # Etapas
    # ----------------------------------------------------------------
    def _fazer_login(self):
        log.info('--- ETAPA 1: LOGIN ---')
        self.driver.get(FV_URL)
        time.sleep(2)

        try:
            radio = self.wait.until(EC.presence_of_element_located(
                (By.XPATH, "//label[contains(text(), 'Parceiro')]")
            ))
            radio.click()
            log.info('Selecionou: Parceiro(a) PagBank')
        except TimeoutException:
            radios = self.driver.find_elements(By.XPATH, "//input[@type='radio']")
            if len(radios) >= 2:
                radios[1].click()

        time.sleep(0.5)

        self._preencher(
            By.XPATH,
            "//input[@placeholder='Usuário' or @name='username' or @id='username']",
            self.fv_usuario, 'usuario',
        )
        self._preencher(
            By.XPATH,
            "//input[@placeholder='Senha' or @name='password' or @id='password' or @type='password']",
            self.fv_senha, 'senha',
        )
        time.sleep(0.5)
        self._clicar(
            By.XPATH,
            "//button[contains(text(), 'Entrar')] | //input[@value='Entrar']",
            'botao_entrar',
        )

        try:
            self.wait.until(EC.any_of(
                EC.presence_of_element_located((By.XPATH, "//*[contains(text(), 'MINHA CARTEIRA')]")),
                EC.presence_of_element_located((By.XPATH, "//*[contains(text(), 'PÁGINA INICIAL')]")),
                EC.url_contains('gestaocomercial.pagbank.com.br'),
            ))
            log.info('Login realizado com sucesso')
        except TimeoutException:
            self._salvar_screenshot('erro_pos_login')
            raise Exception('Dashboard nao carregou apos o login')

        time.sleep(1)

    def _navegar_cadastrar_cliente(self):
        log.info('--- ETAPA 2: NAVEGAR PARA CADASTRAR CLIENTE ---')
        self._clicar(By.XPATH, "//*[@id='menu']/li[3]/a/div[2]/span", 'menu_minha_carteira')
        time.sleep(1)
        self._clicar(By.XPATH, "//*[@id='registerCreate']/div/span", 'menu_cadastrar_cliente')
        self.wait.until(EC.presence_of_element_located(
            (By.XPATH, "//*[contains(text(), 'Cadastrar cliente') and not(contains(@class, 'menu'))]"
                       " | //input[@placeholder='CPF/CNPJ']")
        ))
        log.info('Pagina Cadastrar Cliente carregada')
        time.sleep(1)

    def _preencher_dados_iniciais(self):
        log.info('--- ETAPA 3: CPF/CNPJ E EMAIL ---')
        self._preencher(By.XPATH, "//*[@id='document']", self.dados['cpf_cnpj'], 'cpf_cnpj')
        time.sleep(1)
        self._verificar_erro_cliente_interno()

        self._preencher(
            By.XPATH,
            "//*[@id='__next']/div/main/div/div/form/div/div[2]/div[2]/div/div/input",
            self.dados['email'], 'email',
        )
        time.sleep(0.5)
        self._preencher(
            By.XPATH,
            "//*[@id='__next']/div/main/div/div/form/div/div[2]/div[3]/div/div/input",
            self.dados['email_confirmar'], 'email_confirmar',
        )
        time.sleep(0.5)
        self._clicar(By.XPATH, "//*[@id='__next']/div/main/div/div/form/button", 'botao_continuar')
        log.info('Clicou em Continuar — aguardando proxima tela...')
        time.sleep(3)
        self._salvar_screenshot('etapa2_dados_empresa')

    def _detectar_tipo_cliente(self) -> str:
        try:
            self.wait.until(EC.presence_of_element_located(
                (By.XPATH, '//*[contains(text(), "Cadastrar pessoa")]')
            ))
            titulo = self.driver.find_element(
                By.XPATH, '//*[contains(text(), "Cadastrar pessoa")]'
            ).text.lower()
            if 'física' in titulo or 'fisica' in titulo:
                return 'pf'
            return 'pj'
        except TimeoutException:
            return 'pj' if '/' in self.dados['cpf_cnpj'] else 'pf'

    def _preencher_dados_empresa(self):
        log.info('--- ETAPA 4: DADOS DA EMPRESA ---')
        self.wait.until(EC.presence_of_element_located((By.ID, 'info.companyName')))
        time.sleep(1)

        # Razão social já vem pré-preenchida pelo PagBank via CNPJ
        # Apenas dispara o evento de input para o React reconhecer o valor
        campo = self.driver.find_element(By.ID, 'info.companyName')
        campo.click()
        time.sleep(0.3)
        self._redigitar_ultimo_char(campo, self.dados['razao_social'])

        # Nome fantasia: preenche e força revalidação do React com backspace+redigitar
        campo_fantasia = self.wait.until(EC.presence_of_element_located((By.ID, 'info.trademark')))
        self.driver.execute_script('arguments[0].scrollIntoView(true);', campo_fantasia)
        campo_fantasia.clear()
        time.sleep(0.3)
        campo_fantasia.send_keys(self.dados['nome_fantasia'])
        time.sleep(0.5)
        self._redigitar_ultimo_char(campo_fantasia, self.dados['nome_fantasia'])
        log.info(f'Preencheu nome_fantasia: {self.dados["nome_fantasia"]}')

        cel = re.sub(r'\D', '', self.dados.get('celular') or '')

        # Telefone fixo: sempre ignorar (nunca preencher; limpa se PagBank pré-preencheu)
        self._limpar_telefone_fixo()

        if cel:
            self._preencher(By.ID, 'info.formattedCelphone', cel, 'celular')

        if self.dados.get('url_site'):
            self._preencher(By.ID, 'info.websiteURL', self.dados['url_site'], 'url_site')

        time.sleep(0.5)
        erros = self._coletar_erros()

        if any('número inválido' in (e or '').lower() or 'numero invalido' in (e or '').lower() for e in erros):
            log.warning('Erro de telefone — limpando fixo novamente')
            self._limpar_telefone_fixo()
            time.sleep(0.5)
            erros = self._coletar_erros()

        # Se o nome fantasia não é aceito, tenta com a razão social
        nome_nao_similar = any('similar' in (e or '').lower() for e in erros)
        if nome_nao_similar:
            log.warning('Nome fantasia rejeitado — tentando com razão social como nome fantasia')
            campo_fantasia = self.driver.find_element(By.ID, 'info.trademark')
            campo_fantasia.clear()
            time.sleep(0.3)
            campo_fantasia.send_keys(self.dados['razao_social'])
            time.sleep(0.5)
            self._redigitar_ultimo_char(campo_fantasia, self.dados['razao_social'])
            erros = self._coletar_erros()

        self._exigir_sem_erros('dados da empresa')

        self._clicar(
            By.XPATH,
            "//*[@id='__next']/div/main/div/div/form/div[2]/div/button[2]",
            'botao_continuar_etapa2',
        )
        time.sleep(2)
        self._aguardar_campo_ou_falhar(By.ID, 'info.cpf', 'dados do proprietário')
        self._salvar_screenshot('etapa3')

    def _preencher_dados_proprietario(self):
        log.info('--- ETAPA 5: DADOS DO PROPRIETARIO ---')
        self._aguardar_campo_ou_falhar(By.ID, 'info.cpf', 'dados do proprietário', timeout=5)
        time.sleep(1)

        self._preencher(By.ID, 'info.cpf', self.dados['cpf_socio'], 'cpf_socio')

        try:
            self.wait.until(EC.element_to_be_clickable((By.ID, 'info.birthDate')))
        except TimeoutException:
            pass

        time.sleep(0.5)
        self._preencher(By.ID, 'info.birthDate', self.dados['nascimento'], 'nascimento')

        try:
            self.wait.until(EC.element_to_be_clickable((By.ID, 'info.name')))
        except TimeoutException:
            pass

        time.sleep(0.5)
        self._preencher(By.ID, 'info.name', self.dados['nome_socio'], 'nome_socio')
        time.sleep(0.5)

        dropdown = self.wait.until(EC.element_to_be_clickable(
            (By.XPATH, '//*[@data-testid="dropdown-select"]//div[@role="button"][1]')
        ))
        self.driver.execute_script('arguments[0].scrollIntoView(true);', dropdown)
        time.sleep(0.3)
        dropdown.click()

        opcao = self.wait.until(EC.element_to_be_clickable(
            (By.XPATH, f'//li//div[@role="button"][@aria-label="{self.dados["faturamento"]}"]')
        ))
        opcao.click()

        time.sleep(0.5)
        self._coletar_erros()
        self._clicar(
            By.XPATH,
            "//*[@id='__next']/div/main/div/div/form/div[3]/div/button[2]",
            'botao_continuar_etapa3',
        )
        time.sleep(3)
        self._coletar_erros()
        self._salvar_screenshot('etapa4')

    def _preencher_dados_pf(self):
        log.info('--- ETAPA PF-1: DADOS PESSOAIS E DE CONTATO ---')
        self.wait.until(EC.presence_of_element_located((By.ID, 'info.name')))
        time.sleep(0.5)

        dropdown = self.wait.until(EC.element_to_be_clickable(
            (By.XPATH, '//*[@id="info.monthlyRevenue__label"]/ancestor::div[@role="button"][1]')
        ))
        self.driver.execute_script('arguments[0].scrollIntoView(true);', dropdown)
        time.sleep(0.3)
        dropdown.click()

        opcao = self.wait.until(EC.element_to_be_clickable(
            (By.XPATH, f'//li//div[@role="button"][@aria-label="{self.dados["faturamento"]}"]')
        ))
        opcao.click()
        time.sleep(0.5)

        campo_cel = self.wait.until(EC.presence_of_element_located((By.ID, 'info.formattedCelphone')))
        self.driver.execute_script('arguments[0].scrollIntoView(true);', campo_cel)
        campo_cel.clear()
        time.sleep(0.3)
        cel = re.sub(r'\D', '', self.dados.get('celular') or '')
        campo_cel.send_keys(cel)

        self._limpar_telefone_fixo()

        if self.dados.get('url_site'):
            self._preencher(By.ID, 'info.websiteURL', self.dados['url_site'], 'url_site')

        time.sleep(0.5)
        self._coletar_erros()
        self._clicar(
            By.XPATH, '//*[@data-testid="nextButtonFormNavigation"]', 'botao_continuar_pf1'
        )
        time.sleep(3)
        self._coletar_erros()
        self._salvar_screenshot('pf_etapa2_endereco')

    def _preencher_endereco(self):
        log.info('--- ETAPA: ENDERECO ---')
        from selenium.webdriver.support.ui import Select

        self.wait.until(EC.presence_of_element_located((By.ID, 'address.postalCode')))
        time.sleep(1)

        self._preencher(By.ID, 'address.postalCode', self.dados['cep'], 'cep')

        try:
            self.wait.until(lambda d: d.find_element(By.ID, 'address.city').get_attribute('value') != '')
        except TimeoutException:
            log.warning('API do CEP nao respondeu a tempo')

        time.sleep(0.5)

        campo_end = self.driver.find_element(By.ID, 'address.address')
        if not campo_end.get_attribute('value'):
            if self.dados.get('endereco'):
                self._preencher(By.ID, 'address.address', self.dados['endereco'], 'endereco')
            if self.dados.get('bairro'):
                self._preencher(By.ID, 'address.districtName', self.dados['bairro'], 'bairro')

        time.sleep(0.5)
        self._preencher(By.ID, 'address.addressNumber', self.dados['numero'], 'numero')

        if self.dados.get('complemento'):
            self._preencher(By.ID, 'address.addressComplement', self.dados['complemento'], 'complemento')

        try:
            select_estado = Select(self.driver.find_element(By.ID, 'address.federationUnit'))
            if not select_estado.first_selected_option.get_attribute('value') and self.dados.get('estado'):
                select_estado.select_by_value(self.dados['estado'])
        except Exception as e:
            log.warning(f'Estado: {e}')

        time.sleep(0.5)
        self._coletar_erros()
        self._clicar(
            By.XPATH,
            "//*[@id='__next']/div/main/div/div/form/div[2]/div/button[2]",
            'botao_continuar_endereco',
        )
        time.sleep(3)
        self._coletar_erros()
        self._salvar_screenshot('etapa5_endereco')

    def _preencher_segmento(self):
        log.info('--- ETAPA: SEGMENTO ---')
        self.wait.until(EC.presence_of_element_located(
            (By.ID, 'business.productMainCategoryId__label')
        ))
        time.sleep(1)

        dropdown = self.wait.until(EC.element_to_be_clickable(
            (By.XPATH, '//*[@id="business.productMainCategoryId__label"]/ancestor::div[@role="button"][1]')
        ))
        self.driver.execute_script('arguments[0].scrollIntoView(true);', dropdown)
        time.sleep(0.3)
        dropdown.click()

        opcao = self.wait.until(EC.element_to_be_clickable(
            (By.XPATH, f'//li//div[@role="button"][@aria-label="{self.dados["segmento"]}"]')
        ))
        opcao.click()

        time.sleep(0.5)
        self._coletar_erros()
        self._clicar(
            By.XPATH,
            "//*[@id='__next']/div/main/div/div/form/div[2]/div/button[2]",
            'botao_continuar_segmento',
        )
        time.sleep(3)
        self._coletar_erros()
        self._salvar_screenshot('etapa6_segmento')

    def _preencher_condicoes_comerciais(self):
        log.info('--- ETAPA: CONDICOES COMERCIAIS ---')
        self.wait.until(EC.presence_of_element_located(
            (By.XPATH, '//*[@data-testid="btn_linkMobile"]')
        ))
        time.sleep(1)

        testid_map = {
            'Link Web': 'btn_linkWeb',
            'Link Mobile': 'btn_linkMobile',
            'Link Híbrido': 'btn_linkHybrid',
        }
        testid = testid_map.get(self.dados.get('tipo_link', 'Link Mobile'), 'btn_linkMobile')
        chip = self.driver.find_element(By.XPATH, f'//*[@data-testid="{testid}"]')
        if 'chips_chipsSelected' not in chip.get_attribute('class') and not chip.get_attribute('disabled'):
            self.driver.execute_script('arguments[0].click();', chip)

        time.sleep(0.5)

        dropdown = self.wait.until(EC.element_to_be_clickable(
            (By.XPATH, '//*[@data-testid="promotion-mobile-select"]//*[@role="button"][1]')
        ))
        self.driver.execute_script('arguments[0].scrollIntoView(true);', dropdown)
        time.sleep(0.3)
        dropdown.click()

        if self.dados.get('promocao'):
            opcao = self.wait.until(EC.element_to_be_clickable(
                (By.XPATH, f'//li//div[@role="button"][@aria-label="{self.dados["promocao"]}"]')
            ))
            opcao.click()
        else:
            primeira = self.wait.until(EC.element_to_be_clickable(
                (By.XPATH,
                 '//*[@data-testid="promotion-mobile-select"]'
                 '//li//div[@role="button" and contains(@class,"styles_item")]')
            ))
            primeira.click()

        self.wait.until(EC.element_to_be_clickable(
            (By.XPATH, '//*[@data-testid="nextButtonFormNavigation" and not(@disabled)]')
        ))
        self._coletar_erros()
        self._clicar(
            By.XPATH, '//*[@data-testid="nextButtonFormNavigation"]', 'botao_continuar_condicoes'
        )
        time.sleep(3)
        self._coletar_erros()
        self._salvar_screenshot('etapa_confirmacao')

        log.info('--- ETAPA FINAL: CONFIRMACAO ---')
        try:
            self._clicar(
                By.XPATH,
                "//*[@id='__next']/div/main/div/div/form/div[6]/div/button[2]",
                'botao_confirmar_cadastro',
            )
            log.info('Cadastro confirmado!')
            time.sleep(3)
            self._salvar_screenshot('cadastro_concluido')
        except Exception:
            log.warning('Botao de confirmacao nao encontrado — pode ja ter avancado')
            self._salvar_screenshot('pos_confirmacao')


# ----------------------------------------------------------------
# Funcao publica — usada pela API
# ----------------------------------------------------------------
def cadastrar_fv(dados: dict, fv_usuario: str, fv_senha: str,
                 headless: bool = True, screenshot_dir: str = '/tmp/screenshots',
                 job_id: str | None = None) -> dict:
    """
    Ponto de entrada publico para a API FastAPI.
    Retorna dict com chaves: sucesso, tipo, cpf_cnpj, screenshots, erro (se falhou).
    """
    cadastrador = CadastradorFV(
        dados=dados,
        fv_usuario=fv_usuario,
        fv_senha=fv_senha,
        headless=headless,
        screenshot_dir=screenshot_dir,
        job_id=job_id,
    )
    return cadastrador.executar()


# ----------------------------------------------------------------
# CLI — uso local para testes
# ----------------------------------------------------------------
DADOS_CLI = {
    'cpf_cnpj':        '018.118.831-76',
    'email':           'cv@expresspag.com.br',
    'email_confirmar': 'cv@expresspag.com.br',
    'celular':         '89981254658',
    'telefone':        '',
    'url_site':        '',
    'faturamento':     'De R$ 1 mil até R$ 5 mil',
    'cep':             '64993-000',
    'endereco':        'Av São Gonçalo',
    'bairro':          'Centro',
    'numero':          '10',
    'complemento':     '',
    'estado':          '',
    'segmento':        'Outras atividades empresariais',
    'tipo_link':       'Link Mobile',
    'promocao':        'nnexpresspay7399d028retorno',
    'razao_social':    'L. R. DE MORAES ONLINE TECNOLOGIA LTDA',
    'nome_fantasia':   'Express Payments',
    'cpf_socio':       '433.476.928-45',
    'nascimento':      '29/04/1995',
    'nome_socio':      'LUCAS RAMOS DE MORAES',
}

FV_USUARIO_CLI = 'expresspayments_02'
FV_SENHA_CLI   = 'nm4NmYTKFFg'


if __name__ == '__main__':
    parser = argparse.ArgumentParser(description='Cadastro Forca de Vendas PagBank')
    parser.add_argument('--dados', type=str, help='JSON com os dados do estabelecimento')
    parser.add_argument('--fv-usuario', type=str, default=FV_USUARIO_CLI)
    parser.add_argument('--fv-senha', type=str, default=FV_SENHA_CLI)
    parser.add_argument('--headless', action='store_true', default=False)
    parser.add_argument('--screenshot-dir', type=str, default='screenshots')
    args = parser.parse_args()

    dados = json.loads(args.dados) if args.dados else DADOS_CLI

    resultado = cadastrar_fv(
        dados=dados,
        fv_usuario=args.fv_usuario,
        fv_senha=args.fv_senha,
        headless=args.headless,
        screenshot_dir=args.screenshot_dir,
    )

    print(json.dumps(resultado, ensure_ascii=False, indent=2))
    sys.exit(0 if resultado['sucesso'] else 1)
