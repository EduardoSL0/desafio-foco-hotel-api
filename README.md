# Foco Hotel API

[![CI](https://github.com/EduardoSL0/desafio-foco-hotel-api/actions/workflows/ci.yml/badge.svg)](https://github.com/EduardoSL0/desafio-foco-hotel-api/actions/workflows/ci.yml)

API de gestão hoteleira feita em **Laravel 12** para o desafio da Foco. Ela importa hotéis, quartos e reservas
dos XMLs (de hora em hora, via cron), tem o CRUD de quartos e um motor de reservas com disponibilidade,
promoções, cupons, taxas e pagamentos. Todas as respostas são em JSON.

## Como rodar

Precisa só do Docker.

```bash
docker compose up -d --build
```

Na primeira vez leva 1 ou 2 minutos: instala as dependências, cria o banco, importa os XMLs e cria usuários de teste.
Depois abra **http://localhost:8080**: é a documentação interativa (Swagger), onde dá para testar tudo pelo navegador.

| Usuário | Senha | O que pode fazer |
|---|---|---|
| `admin@foco.test` | `password` | tudo, em todos os hotéis |
| `gerente@foco.test` | `password` | quartos, promoções, cupons, equipe e relatório do Hotel Foco Prime |
| `recepcao@foco.test` | `password` | ver reservas e registrar pagamentos do Hotel Foco Prime |

Na página, é só clicar em **Entrar** ao lado de um usuário: o token é aplicado sozinho nas rotas protegidas.
Para testar o "minha reserva", já existe uma reserva de exemplo: localizador `FH7K3Q9X`, sobrenome `de Tal`.

Outros comandos úteis:

```bash
docker compose exec app php artisan test        # testes
docker compose exec app php artisan import:xml  # importar os XMLs na hora
docker compose exec app php artisan migrate:fresh --seed  # recomeçar o banco do zero
```

<details>
<summary>Rodar sem Docker</summary>

Com PHP 8.2+, Composer e MySQL 8:

```bash
composer install
cp .env.example .env      # ajuste os dados do banco
php artisan key:generate
php artisan migrate --seed
php artisan serve         # http://localhost:8000
php artisan schedule:work # em outro terminal: é o cron
```

</details>

## Importação dos XMLs e o cron

Os arquivos ficam em `database/xml` (`hotels.xml`, `rooms.xml`, `reserves.xml`). O comando é:

```bash
php artisan import:xml
```

- Ele valida os três arquivos antes de gravar e grava tudo numa transação: se algo grave falhar, nada muda no banco.
- Pode rodar quantas vezes quiser sem duplicar nada. O `id` de cada registro do XML fica guardado em `external_code`
  e é usado para atualizar o que já existe.
- O agendamento está em `routes/console.php` (de hora em hora). No Docker, o container `scheduler` faz o papel do
  cron. Num servidor Linux bastaria esta linha no crontab:

```cron
* * * * * cd /caminho/do/projeto && php artisan schedule:run >> /dev/null 2>&1
```

Para mudar a frequência, altere `XML_IMPORT_CRON` no `.env` (ex.: `*/15 * * * *`).

## Modelagem do banco

As tabelas são criadas pelas migrations em `database/migrations`. O mesmo modelo está em
[`database/model/schema.sql`](database/model/schema.sql), que abre no **MySQL Workbench** como diagrama
(*File → Import → Reverse Engineer MySQL Create Script*).

```mermaid
erDiagram
    HOTELS ||--o{ ROOMS : possui
    HOTELS ||--o{ RESERVES : recebe
    HOTELS ||--o{ USERS : emprega
    HOTELS ||--o{ PROMOTIONS : oferece
    HOTELS |o--o{ COUPONS : emite
    ROOMS ||--o{ RESERVES : "é reservado em"
    COUPONS |o--o{ RESERVES : "aplicado em"
    RESERVES ||--o{ DAILIES : tem
    RESERVES ||--o{ PAYMENTS : tem
    RESERVES }o--o{ GUESTS : hospeda
```

Do XML para o banco: `Hotel` → `hotels`, `Room` → `rooms`, `Reserve` → `reserves`, `Guests/Guest` → `guests`
(ligado à reserva), `Dailies/Daily` → `dailies` e `Payments/Payment` → `payments`.

## Como cadastrar um quarto

Com o token de um admin ou gerente:

```bash
curl -X POST http://localhost:8080/api/v1/rooms \
  -H "Authorization: Bearer $TOKEN" -H "Content-Type: application/json" \
  -d '{"hotel_id": 1, "name": "Standard Casal", "capacity": 2, "inventory": 10, "daily_price": 189.90}'
```

`inventory` é quantas unidades iguais existem desse quarto (o "Standard tem 10 disponibilidades" do desafio).
Para editar: `PATCH /rooms/{id}`. Para remover: `DELETE /rooms/{id}` (não deixa se houver reservas futuras).

## Como criar uma reserva

Essa rota é pública, como num site de hotel:

```bash
curl -X POST http://localhost:8080/api/v1/reserves \
  -H "Content-Type: application/json" \
  -d '{"room_id": 1, "check_in": "2027-03-10", "check_out": "2027-03-13", "coupon_code": "BEMVINDO10",
       "guests": [{"name": "Maria", "last_name": "Souza", "phone": "5571999990000"}]}'
```

A resposta traz o localizador (`code`), as diárias e os valores. O cliente não envia o total: quem calcula é o
servidor, senão daria para pagar R$ 1 numa suíte. Para só simular o preço, use `POST /reserves/quote`.
As datas precisam ser futuras e no máximo 2 anos à frente.

## Rotas

| Método | Rota | Login | Para quê |
|---|---|:---:|---|
| POST | `/auth/login` · `/auth/logout` · GET `/auth/me` | | entrar, sair, ver quem sou |
| GET | `/hotels` · `/hotels/{id}` | | hotéis |
| GET · POST · PATCH · DELETE | `/rooms` · `/rooms/{id}` | POST, PATCH e DELETE | CRUD de quartos |
| GET | `/rooms/{id}/availability` | | unidades livres no período |
| GET | `/availability` | | busca de quartos livres já com o preço |
| POST | `/reserves/quote` · `/reserves` | | cotar e criar reserva |
| POST | `/reserves/lookup` | | "minha reserva" (localizador + sobrenome) |
| GET · PATCH | `/reserves` · `/reserves/{id}` · `/reserves/{id}/cancel` | 🔒 | consultar e cancelar |
| GET · POST | `/reserves/{id}/payments` | 🔒 | pagamentos |
| GET · POST · DELETE | `/coupons` | 🔒 | cupons |
| CRUD | `/promotions` · `/users` | 🔒 | promoções e equipe do hotel |
| GET | `/hotels/{id}/report` | 🔒 | ocupação, diária média e RevPAR |

Base: `http://localhost:8080/api/v1`. Os detalhes de cada rota estão no Swagger.

## Regras de negócio

- **Disponibilidade:** cada quarto tem um número de unidades (`inventory`). A ocupação é conferida noite a noite e
  o dia do check-out não conta. Na hora de gravar, o quarto fica travado no banco para duas pessoas não reservarem
  a última unidade ao mesmo tempo.
- **Preço, nesta ordem:** diárias → promoção (a maior do dia) → cupom → taxa de serviço do hotel.
  Ex.: 3 × R$ 100 com promoção de 20%, cupom de 10% e taxa de 10% = **R$ 237,60**.
- **Pagamentos:** 1 crédito, 2 débito, 3 pix, 4 dinheiro, 5 boleto. No crédito dá para parcelar em até 12×;
  acima de 3× entram juros de 1,99% por parcela a mais. Não dá para pagar acima do saldo. O status vai de
  `pending` → `partially_paid` → `paid`.
- **Permissões:** admin vê tudo; gerente e recepção só o próprio hotel. As regras ficam em `app/Policies`.
- As contas de dinheiro são feitas em centavos, para não ter erro de arredondamento.

## Como o código está organizado

```
app/
├── Console/Commands/   # import:xml (o que o cron roda)
├── Http/Controllers/   # recebem o pedido e chamam os services
├── Http/Requests/      # validação de cada rota
├── Http/Resources/     # formato do JSON de resposta
├── Models/             # uma classe por tabela
├── Policies/           # quem pode fazer o quê
└── Services/           # as regras: reserva, pagamento, disponibilidade,
    ├── Import/         #   importação do XML (um importador por arquivo)
    └── Pricing/        #   cálculo do preço (uma classe por regra)
```

Separei as regras dos controllers (services) e fiz cada regra de preço e cada importador como uma classe
própria. Para criar uma nova taxa, por exemplo, basta uma classe nova em `Services/Pricing/Rules`.

## Testes, logs e Git

- **133 testes** com PHPUnit (`tests/`). Usam um banco em memória, então não mexem nos seus dados.
  Rodam também no GitHub Actions a cada push, junto com o padrão de código (Pint) e a validação do Swagger.
- **Logs** em `storage/logs`: `api-*.log` (cada acesso), `import-*.log` (cada importação) e `laravel-*.log`
  (eventos e erros). Toda resposta traz um `X-Request-Id`, que aparece em todas as linhas de log daquela requisição.
- **Commits** no padrão Conventional Commits (`feat:`, `fix:`, `docs:`, `test:`).

## Decisões sobre os XMLs

- **Reserva 6:** tem uma diária em 03/12, fora do período 01/10 a 04/10. Importei mesmo assim, com um aviso no log.
- **`rooms.xml` não tem preço:** a diária do quarto vem da última diária importada dele.
- **Formas de pagamento:** o XML só traz o número (`<Method>1</Method>`), então defini 1 crédito, 2 débito, 3 pix,
  4 dinheiro e 5 boleto.
- **Mesmo hóspede em duas reservas** (mesmo nome e telefone): vira um hóspede só, ligado às duas.
- **Reserva 1:** total de R$ 300 com R$ 100 pago, então fica como `partially_paid`.

## Segurança

Login por token com validade de 8 h, permissões por perfil e por hotel, limite de requisições (inclusive contra
força bruta no login), validação de tudo que entra, proteção contra XXE na leitura do XML e respostas de erro
sem detalhes internos. O `docker-compose.yml` é para avaliação; em produção use `APP_DEBUG=false`, senhas fortes e
`SEED_ON_START=false` (o primeiro admin é criado com `php artisan user:create-admin email@hotel.com`).
