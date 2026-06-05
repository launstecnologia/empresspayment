# Express

Implementacao Laravel 11 baseada nos documentos `arquitetura_sistema_completo.md` e `arquitetura_sistema_completo-2.md`.

## Escopo implementado nesta fundacao

- Estrutura Laravel 11 com Blade, filas Redis, MySQL e scheduling.
- Schema principal: usuarios, hierarquia, estabelecimentos, planos, comissoes, EDI, agregados, permissoes e logs.
- Models Eloquent com relacionamentos principais e scope de hierarquia para dados agregados/estabelecimentos.
- Jobs de EDI PagBank, processamento paginado, calculo de comissoes e agregacao de faturamento.
- Services para DirectAdmin, EDI, comissoes, auditoria e agregacao.
- Rotas e telas iniciais de login, dashboard, estabelecimentos, planos e relatorios.

## Proximos comandos quando PHP estiver instalado

```bash
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate --seed
php artisan queue:work
php artisan schedule:work
```

## Rodando com Docker

```bash
docker compose build app
docker compose run --rm app composer install
docker compose run --rm app php artisan migrate --seed --force
docker compose up -d app queue
```

A aplicacao fica disponivel em `http://localhost:8080`.
MySQL fica exposto em `localhost:3307` e Redis em `localhost:6380`.

Usuario inicial do seeder:

- E-mail: `admin@example.com`
- Senha: `password`

## Observacao de arquitetura

A tabela tecnica `estabelecimento_royalties` ganhou `plano_taxa_id`, porque a regra de comissao e definida por plano + instituicao + tipo + parcelas. Sem esse vinculo, o calculo por transacao ficaria ambiguo.
