# Laravel Cloud deploy settings (source of truth)

Laravel Cloud is configured in its dashboard, not from a committed manifest. This file records the
exact settings to enter so they are version-controlled and reproducible. Full walkthrough:
[docs/runbooks/go-live-piotrack-com.md](../docs/runbooks/go-live-piotrack-com.md).

Repository: `Dalbeirdev/piotrack` · Branch: `main` · Environment: `production`

## Build command
```bash
composer install --no-dev --optimize-autoloader --no-interaction
npm ci
npm run build
```

## Deploy (release) command
```bash
php artisan migrate --force
php artisan config:cache
php artisan route:cache
php artisan event:cache
```

## Infrastructure
- PostgreSQL 16 (managed) — `DB_*` injected by Cloud; external DB needs `DB_SSLMODE=require`.
- Redis (KeyValue) — `REDIS_*` injected by Cloud; `CACHE_STORE=redis`, `QUEUE_CONNECTION=redis`.
- Queue worker: process the `redis` queue.
- Scheduler: run `php artisan schedule:run` every minute.

## Required environment variables
See [.env.production.example](../.env.production.example). Must be set:
`APP_KEY`, `APP_ENV=production`, `APP_DEBUG=false`, `APP_URL=https://piotrack.com`,
`SESSION_DOMAIN=.piotrack.com`, `SESSION_SECURE_COOKIE=true`,
`SANCTUM_STATEFUL_DOMAINS=piotrack.com,www.piotrack.com`, and a real `MAIL_*` provider.

## Health check
`GET /health` → 200 `{"status":"ok"}`. Liveness: `GET /up`.
