# PayoutService

Сервис обработки заявок на выплату через нестабильный внешний payment provider.
Стек: **PHP 8.2 / Laravel 11 / MySQL 8 / Redis 7 / Docker Compose**.

---

## TL;DR

`POST /api/payouts` создаёт заявку (синхронно, идемпотентно), кладёт `SendPayoutToProviderJob` в очередь `payouts`. Воркер делает HTTP-вызов к провайдеру, классифицирует исключение и либо releas-ит job c экспоненциальным backoff'ом, либо переводит payout в `failed`. Provider присылает webhook → `POST /api/webhooks/{provider}` → подпись HMAC-SHA256 проверяется, событие пишется в inbox по `event_id` (PK), ставится `ProcessWebhookEventJob` в очередь `webhooks`, который через FSM переводит payout в финальный статус.

---

## Запуск

Требования: Docker + Docker Compose **v2.23+** (нужен `configs:` inline).

```bash
cp .env.example .env
docker compose up -d --build              # app, worker, mysql, redis
./composer.sh install
./artisan.sh key:generate
./artisan.sh migrate                      # БД payouts
```

База `payouts_test` создаётся автоматически инициализационным скриптом MySQL (см. `docker-compose.yml`, секция `configs:`).

API на `http://localhost:8080`.

### Команды (всё через docker exec — обёртки в корне)

```bash
./artisan.sh <cmd>          # php artisan ... в контейнере app
./composer.sh <cmd>
./phpunit.sh [args]         # тесты (PHPUnit 11)
./phpcs.sh                  # стиль (Squiz/IDF + Slevomat)
./phpcbf.sh                 # автофикс стиля
```

Запускать `php`/`vendor/bin/*` напрямую с хоста **нежелательно**

### Тестовое окружение

- `RefreshDatabase` проганяет миграции один раз против `payouts_test`, далее каждый тест в rolled-back транзакции.
- `QUEUE_CONNECTION=sync`, `CACHE_STORE=array`, Redis на DB index `1` (dev/prod — `0`).
- Чтобы пересоздать тестовую БД с нуля: `docker compose down -v && docker compose up -d`.

---

## Архитектура

Слоистая (≈ hexagonal). Доменные контракты — в `App\Domain\*`, реализации — в `App\Infrastructure\*` и `Processors\*`. Composer PSR-4: `App\` → `app/`, `Processing\` → `processing/`, `Processors\` → `processors/`.

```
HTTP            app/Http/Controllers, app/Http/Middleware
   PayoutController, WebhookController
   RequestIdMiddleware, IdempotencyMiddleware
                    │
Application     app/Services, app/DTO
   CreatePayoutHandler, PayoutService
   CreatePayoutCommand (spatie/laravel-data + валидация), PayoutData
                    │
Domain          app/Domain/*
   Payout (entity), PayoutStatus, PayoutStateManager (FSM)
   RetryPolicyContract + ExponentialBackoffRetryPolicy, Classification
   IdempotencyStoreContract, WebhookInboxContract
   PayoutException + иерархия (см. ниже)
                    │
Infrastructure  app/Infrastructure/*
   PayoutRepository (Eloquent), PayoutModel
   RedisIdempotencyStore, EloquentWebhookInbox
                    │
Processing      processing/   (абстракция «провайдер выплат»)
   ConfigDrivenProcessorRegistry, ProcessorFactoryContract,
   PaymentProcessorContract, NotificationProcessorContract,
   OutboundPaymentCommand, CanonicalWebhookEvent, PaymentResult
                    │
Processors      processors/PaymentProviderDummy/
   Factory, PaymentProcessor (HTTP), NotificationProcessor (HMAC),
   Api/Requests/*, Api/Responses/*
```

**Биндинги** — `App\Providers\AppServiceProvider`. **Регистрация провайдера** — отдельный `PaymentProviderDummyServiceProvider`, мерджит `config/providers/dummy.php` в `providers.dummy`. Список включённых провайдеров — `config/providers.php` → `providers.enabled` (через ENV `PROVIDERS_ENABLED`).

### Endpoints

| Метод | Путь                          | Назначение                          |
|-------|-------------------------------|-------------------------------------|
| POST  | `/api/payouts`                | Создать payout (идемпотентно).      |
| GET   | `/api/payouts/{uuid}`         | Прочитать (только не-prod).         |
| POST  | `/api/webhooks/{provider}`    | Webhook от провайдера.              |
| GET   | `/api/ping`                   | health.                             |

Тело `POST /api/payouts`:
```json
{ "provider":"dummy", "user_id":123, "amount":"150.00", "currency":"USDT",
  "wallet":"TRX-...", "external_reference":"order-10001" }
```
Заголовок `Idempotency-Key: <uuid>`. Ответ `202 Accepted` + `PayoutData`.

### Очереди

- `payouts`  — `SendPayoutToProviderJob` (HTTP-вызов провайдера, retry).
- `webhooks` — `ProcessWebhookEventJob` (применение состояния через FSM).

Воркер из `docker-compose.yml`: `queue:work --queue=payouts,webhooks --tries=1 --max-time=3600`.
Job'ы сами держат `$tries=10` и сами вызывают `release($delay)` — Laravel'овский `tries=1` лишь отключает «слепой» повтор фреймворка.

---

## Идемпотентность

**Три независимых уровня — ни один не доверяет другому.**

1. **Запрос `POST /api/payouts`** — `IdempotencyMiddleware` + `RedisIdempotencyStore`.
   - Ключ: `idem:payouts:<Idempotency-Key>`, TTL **86400s**.
   - `SET NX` атомарно «застолбляет» ключ со state=`in_flight` и `fingerprint = sha256(raw body)`.
   - После выполнения handler'а кешируется `state=done` + status + body, реплеи возвращают точно тот же JSON-ответ без побочных эффектов.
   - Тот же ключ с другим fingerprint → `IdempotencyConflictException` → `409`.

2. **Доменный fallback** — уникальный индекс `payouts.idempotency_key`. Если Redis не сработал (TTL истёк, рестарт), `INSERT` всё равно упадёт с `QueryException`, `PayoutService::create` подхватывает и возвращает существующий payout.

3. **Webhook** — `EloquentWebhookInbox` (`webhook_events`, PK = `event_id`).
   - `INSERT ... ON DUPLICATE KEY IGNORE` → `recordOrIgnore()` возвращает `false` для дубля → `200 OK` без enqueue (safe replay).
   - `markProcessed()` ставит `processed_at` после успеха `ProcessWebhookEventJob`.

> ⚠️ Колонка `webhook_events.payload` имеет тип `JSON` — MySQL канонизирует JSON при вставке (порядок ключей, пробелы), 
> поэтому **повторно проверить HMAC по сохранённому payload нельзя**. Подпись сохраняется только как audit-артефакт; 
> верификация происходит ровно один раз — синхронно в контроллере, до записи. 
> Подробности и план миграции на `LONGTEXT`/`LONGBLOB` — в комментарии в `2026_05_08_200900_create_webhook_events_table.php`.

---

## Retry / Backoff / Классификация ошибок

Вся orchestration — в `SendPayoutToProviderJob`, контроллер чистый.

**Политика** — `ExponentialBackoffRetryPolicy`:
```
delay = retryAfter ?? random_int(0, min(cap, base * 2^(attempt-1)))
base = 1s, cap = 300s, max_attempts = 10  (full jitter)
```

**Классификация исключений** — каждое доменное исключение наследует `PayoutException` и возвращает `Classification::{Transient,Terminal}`:

| Исключение                            | HTTP-сценарий               | Класс       |
|---------------------------------------|-----------------------------|-------------|
| `ProviderRateLimitedException`        | `429` + `Retry-After`       | Transient   |
| `ProviderUnavailableException`        | `5xx`                       | Transient   |
| `ProviderTimeoutException`            | таймаут соединения/чтения   | Transient   |
| `ProviderNetworkException`            | сетевая ошибка              | Transient   |
| `ProviderValidationException`         | `4xx` (кроме 429)           | Terminal    |
| `ProviderRejectedException`           | provider rejected явно      | Terminal    |
| `ProviderContractViolationException`  | malformed JSON / bad shape  | Terminal    |
| любое прочее `Throwable`              | unexpected                  | Terminal    |

**Поведение Job**:
- `Transient` + `attempts < maxAttempts` → `incrementAttempts()`, `release($delay)`, лог `payout.retrying`.
- `Terminal` или исчерпан лимит → `markFailed(code, msg)`, лог `payout.failed`, метрика `payout.failed`.
- Если payout уже в терминальном статусе (`success`/`failed`) на момент захвата job'а — лог `payout.already_terminal` и тихий выход (защита от webhook-`success`, прилетевшего до ответа провайдера).

**Таймауты HTTP**: `connect=2.0s`, `read=5.0s` (см. `PROVIDER_TIMEOUT_*`).

---

## Webhook security

- Заголовок `x-provider-signature: hex(hmac_sha256(secret, raw_body))`.
- `NotificationProcessor::verifyAndDecode` — `hash_equals` (constant-time), невалидная подпись → `InvalidWebhookSignatureException` → `401`.
- Секрет — `PROVIDER_WEBHOOK_SECRET` (per-provider config: `config/providers/dummy.php`).
- Полная декомпозиция: `verifyAndDecode` возвращает `CanonicalWebhookEvent` — нейтральный DTO. Заменив провайдера, контракт верхних слоёв не меняется.

---

## Мониторинг

- **Канал логов** `payouts` — `PayoutsLoggerFactory` + `PayoutsJsonFormatter`. JSON со встроенными `request_id`, `payout_id`, `attempt`, `provider_status`. Файл `storage/logs/payouts.log`, ротация 14 дней.
- **`RequestIdMiddleware`** — кладёт/прокидывает `X-Request-Id`, `RequestIdProcessor` подмешивает его в каждую запись.
- **Метрики** — `RedisCounter` (`INCR`):
  `payout.attempts`, `payout.dispatched`, `payout.success`, `payout.failed`, `payout.unexpected`,
  `provider.429`, `provider.5xx`, `provider.contract_violation`.
- **Ключевые лог-события**: `payout.dispatched`, `payout.retrying`, `payout.failed`, `payout.already_terminal`, `payout.not_found`, `payout.unexpected`, `webhook.duplicate`, `webhook.unknown_payout`, `webhook.illegal_transition`.
- Структура ошибок ответа единая — `App\Support\Http\ErrorResponse` (`code`, `message`, `request_id`).

---

## Расширяемость / Scaling

- **Pluggable provider** — `ConfigDrivenProcessorRegistry` (slug → Factory). Добавить интеграцию: каталог `processors/<NewProvider>/` (`Factory`, `PaymentProcessor`, `NotificationProcessor`), отдельный ServiceProvider с `mergeConfigFrom`, прописать в `config/providers.php` и `PROVIDERS_ENABLED`. Доменный код не трогать.
- **Stateless workers** — сколько угодно одинаковых `worker` контейнеров. Очереди `payouts` и `webhooks` можно развести по разным пулам (HTTP-bound vs DB-bound).
- **MySQL** — индекс `(status, updated_at)` под backlog-сканы; уникальные индексы на `uuid`, `idempotency_key`, `provider_payout_id`.
- **Redis** — TTL 24h на idempotency-ключи (низкий memory footprint), счётчики на `INCR`, очереди.
- **Higher load**: вынести retry в **Temporal/Workflow engine** (durable execution, exactly-once semantics), добавить **outbox** для гарантии доставки апдейтов между записью в payouts и enqueue, **circuit breaker** перед провайдером, **DLQ** для permanently-failed.

---

## Тесты

`./phpunit.sh` — Feature + Unit:

- `CreatePayoutTest` — happy path, валидация, идемпотентность (replay, conflict), enqueue.
- `WebhookControllerTest` — happy path, дубль, невалидная подпись, неизвестный provider.
- `PayoutLifecycleTest` — end-to-end pending → processing → success.
- `LoggingStructureTest` — JSON-формат логов и обязательные поля.
- `SendPayoutToProviderJobTest`, `ProcessWebhookEventJobTest` — поведение job'ов, классификация, release/fail.
- `ExponentialBackoffRetryPolicyTest`, `ExceptionClassificationTest` — политика retry и classification per exception.

Запускать желательно через `./phpunit.sh`

---

## Допущения / упрощения

- **Один встроенный провайдер** `dummy` (`PROVIDER_FAKE_SCENARIO=success|rate_limit_once|unavailable_thrice|malformed_body|terminal_4xx`). Реальный провайдер не поднимается.
- **Без аутентификации/авторизации API** (по ТЗ).
- **Без фронта**.
- `GET /api/payouts/{uuid}` доступен **только не в production** (для отладки/проверки lifecycle руками).
- **Подпись webhook'а сохраняется как audit-only** — повторно валидировать по записанному `payload` нельзя (см. предупреждение выше). Trust-anchor — сам факт наличия строки в `webhook_events`.
- **Outbox не реализован** — между `payouts.save()` и `Job::dispatch()` есть теоретическое окно (если процесс упадёт между ними, payout «зависнет» в `pending`). На практике обе операции внутри HTTP-цикла, перезапуск API не приведёт к потере, но строгая гарантия требует outbox.
- **Circuit breaker отсутствует** — при долгом 5xx у провайдера job'ы будут крутиться до `max_attempts`, нагружая воркеров.
- **Retry-only-on-write** — webhook-job сейчас НЕ повторяется при transient DB-ошибках; полагается на ретраи Laravel при `release` (фактически `tries=1` от воркера). Для жёсткой гарантии — поднять до `tries=N` отдельно.
- **Нет DLQ** — терминально упавшие payout'ы остаются в `failed` со ссылкой на `last_error`, ручной разбор.
- **Метрики только в Redis** — нет экспорта в Prometheus/StatsD; добавляется отдельным adapter'ом без правки бизнес-кода.
- **Без авторизации провайдером по mTLS / IP allowlist** — доверяем только HMAC.
