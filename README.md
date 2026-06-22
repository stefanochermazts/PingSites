# PingSites — Web Monitor interno

Applicazione interna Laravel 13 + Filament 5 per monitorare siti web pubblici HTTP/HTTPS.

## Funzionalità

- Gestione monitor con check manuali e automatici
- Scheduler + queue Redis (code: `checks`, `notifications`, `cleanup`)
- Incidenti automatici con soglie configurabili
- Email down/recovery via SMTP (Elastic Email su Cloudways)
- Status page pubblica su `/status`
- Manutenzioni programmate
- Retention automatica check e log notifiche

## Requisiti

- PHP 8.3+
- Composer
- MySQL/MariaDB
- Redis
- Node.js (per asset Filament, opzionale in dev)

## Setup locale

```bash
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate --seed
php artisan serve
```

Admin panel: `/admin`

Credenziali default (seeder):

- Email: `admin@pingsites.local`
- Password: `password`

## Configurazione produzione (Cloudways)

### Database

```env
DB_CONNECTION=mysql
DB_HOST=...
DB_DATABASE=...
DB_USERNAME=...
DB_PASSWORD=...
```

### Redis queue/cache

```env
QUEUE_CONNECTION=redis
CACHE_STORE=redis
REDIS_HOST=127.0.0.1
REDIS_PORT=6379
```

### Email (Elastic Email via Cloudways)

```env
MAIL_MAILER=smtp
MAIL_HOST=...
MAIL_PORT=587
MAIL_USERNAME=...
MAIL_PASSWORD=...
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS=monitor@example.com
MAIL_FROM_NAME="Devisia Monitor"
MONITOR_ALERT_RECIPIENTS=team@example.com,ops@example.com
```

### Cron

```bash
* * * * * cd /path/to/app && php artisan schedule:run >> /dev/null 2>&1
```

### Supervisor workers

```ini
[program:pingsites-checks]
command=php /path/to/app/artisan queue:work --queue=checks --timeout=30 --tries=2
autostart=true
autorestart=true
numprocs=2

[program:pingsites-notifications]
command=php /path/to/app/artisan queue:work --queue=notifications --timeout=60 --tries=3
autostart=true
autorestart=true

[program:pingsites-cleanup]
command=php /path/to/app/artisan queue:work --queue=cleanup --timeout=120 --tries=1
autostart=true
autorestart=true
```

## Comandi utili

```bash
php artisan monitors:dispatch-checks
php artisan checks:prune
php artisan queue:work --queue=checks,notifications,cleanup
```

## Documentazione funzionale

Vedi [documents/analisi-funzionale.md](documents/analisi-funzionale.md).
