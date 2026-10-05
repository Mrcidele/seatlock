# SeatLock

Venda de passagens rodoviárias com reserva de assentos **por trecho** e garantia de que nenhum assento é vendido duas vezes.

> **Princípio:** o banco é a fonte da verdade e o Redis é só otimização. O lock no Redis reduz conflitos e melhora a UX; a garantia final vem do `UNIQUE(trip_id, seat_id, segment_index)` em `seat_segments` no PostgreSQL.

## Stack

PHP 8.4 · Laravel 13 · PostgreSQL 17 · Redis 7 · Octane (FrankenPHP) · Reverb · Horizon · Pulse · Sanctum · Scramble · Vue 3 + TypeScript (Pinia, TanStack Query) · Pest · Larastan (nível max) · Pint · k6.

## Rodando

```bash
cp .env.example .env
docker compose up -d --build
docker compose exec app composer install
docker compose exec app php artisan key:generate
docker compose exec app php artisan migrate --seed
```

- App: http://localhost:8000 (front em Vue servido pelo Vite em `:5173` no modo dev)
- Documentação da API: http://localhost:8000/docs/api (OpenAPI exportado em `docs/openapi.json`)
- Horizon: `/horizon` · Pulse: `/pulse` (admins em produção)
- Usuários do seed: `cliente@seatlock.test` e `operador@seatlock.test`, senha `password`

Qualidade:

```bash
composer lint        # Pint
composer analyse     # Larastan nível max
composer test        # Pest (Unit + Feature + Concurrency)
npm run typecheck && npm test && npm run build
```

## Como funciona

### Trechos e segmentos

Cada viagem percorre as paradas da linha. O segmento `i` liga a parada `i` à `i+1`. Comprar A(0)→C(2) ocupa os segmentos 0 e 1, então o mesmo assento pode ser vendido para A→B e B→C. Um assento está livre para um trecho se **nenhum** dos seus segmentos estiver em `seat_segments`.

### Fluxo de compra

1. `POST /api/v1/trips/{trip}/locks` — trava os assentos no Redis (`lock:{trip}:{seat}:{segment}`, `SET NX EX`, valor = dono). Um script Lua trava todos ou nenhum; liberar e renovar só pelo dono. TTL de 10 min.
2. `POST /api/v1/orders` (com `Idempotency-Key`) — cria o pedido `Pending` com prazo igual ao do lock e agenda o job de expiração.
3. `POST /api/v1/orders/{order}/payments` (com `Idempotency-Key`) — Pix (BR Code) ou cartão tokenizado no cliente.
4. Pagamento aprovado (resposta síncrona ou webhook) → confirmação numa transação com `SELECT ... FOR UPDATE`; a inserção em `seat_segments` roda num savepoint. Violação do `UNIQUE` = "assento indisponível" → pedido cancelado e **estorno automático**.

Cabeçalhos: `Authorization: Bearer <token>`, `X-Cart-Id` (carrinho, dono dos locks) e `Idempotency-Key`. Erros seguem RFC 9457 (`application/problem+json`); conflitos de assento trazem `conflicting_seat_ids` e `alternatives`.

### Falhas tratadas

| Cenário | Comportamento |
| --- | --- |
| Redis fora do ar | `ResilientSeatLockService` segue sem lock (fail-open); o `UNIQUE` barra a venda dupla e o perdedor é estornado |
| Clique duplo / retry | `Idempotency-Key` devolve a mesma resposta |
| Webhook duplicado | `UNIQUE(provider, event_id)`: processado uma vez |
| Webhook fora de ordem | Status de pagamento monotônico; webhook antes do pagamento ser gravado é reprocessado |
| Pagamento depois de expirar | Reativa os assentos se ainda livres; senão estorna |
| Job de expiração perdido | `orders:expire-stale` a cada minuto |
| Dois pagamentos para o mesmo pedido | O segundo é estornado |

### Tempo real

Eventos `SeatLocked`, `SeatReleased` e `SeatSold` no canal público `trip.{id}` (Reverb), transmitidos após o commit. O front aplica o evento no mapa em cache e reconcilia com a API.

### Pós-venda

Bilhete em PDF com QR assinado (HMAC-SHA256), validação no embarque com uso único (`POST /api/v1/boarding/validate`), cancelamento com reembolso por antecedência (100% ≥ 72 h, 95% ≥ 24 h, 80% ≥ 3 h) e remarcação para outra viagem da mesma linha.

## Testes de concorrência e carga

- `tests/Concurrency`: 12 processos PHP reais disputam o mesmo assento (com e sem Redis, e em trechos sobrepostos) — exatamente 1 vencedor.
- k6 (500 usuários no mesmo assento):

```bash
php artisan seatlock:loadtest-prepare --users=500
RATE_LIMIT_LOCKS_PER_IP=100000 php artisan octane:frankenphp   # todos os VUs saem do mesmo IP
k6 run -e BASE_URL=http://localhost:8000 loadtest/k6/same-seat.js
php artisan seatlock:verify --trip=<trip_id>
```

## Observabilidade e produção

- **Horizon** com supervisores separados: `critical` (payments, webhooks, orders) e `background` (broadcasts, default).
- **Pulse** com card de negócio: conversão do lock, pedidos expirados, estornos por conflito e tempo até pagar. `GET /api/v1/admin/metrics` calcula as mesmas métricas no banco.
- **Logs JSON** (`LOG_STACK=json`) com `request_id` e `user_id` via Context; header `X-Request-Id` em toda resposta.
- **Sentry** (`SENTRY_LARAVEL_DSN`); erros de domínio 4xx não são reportados.
- **Health check** `/up` falha se o PostgreSQL cair; Redis fora só gera alerta (a app segue degradada).
- **Octane/FrankenPHP**: serviços singleton são sem estado de request; ações de domínio são `final readonly` (verificado em teste).

### Deploy

A imagem `production` do `Dockerfile` é construída e publicada no GHCR pelo CI a cada push na `main`. Em cada deploy, rode `release` (migrations com `--isolated` + caches) uma vez antes de trocar as instâncias.

Migrations sem downtime seguem **expand/contract**: adicionar colunas/tabelas compatíveis com a versão anterior, publicar o código que usa as duas formas, migrar os dados e só então remover o que ficou obsoleto num deploy seguinte. Índices grandes no PostgreSQL devem usar `CREATE INDEX CONCURRENTLY` numa migration com `$withinTransaction = false`.
