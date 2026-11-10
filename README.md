# Aira Admin

Laravel 12 admin panel for **Aira**, the HealthTalk voice assistant. It has five pages:

| Page | URL |
|---|---|
| Content Library: what Aira may answer from (text or PDF) | `/healthtalk/content` |
| Guardrails, plus the default message when one blocks an answer | `/healthtalk/guardrails` |
| Introductory message (start pitch), with generated audio | `/healthtalk/start-pitch` |
| Conversation reports: usage, ratings, wrong answers, gaps | `/healthtalk/reports` |
| SH Users: everyone in the central users table | `/sh-users` |

`/` redirects to the Content Library.

> **There is no login.** Anyone who can open this app can change what Aira says and read every user's conversations. Run it on a private network, behind a VPN, or behind your web server's or load balancer's authentication (for example HTTP basic auth or an identity-aware proxy).

The panel keeps no data of its own. Every page reads and writes through the **Aira API** (`saathi-laravel-api`), server to server, sending the shared `ADMIN_API_KEY` in an `X-Admin-Key` header. The browser never sees the key.

## Requirements

- PHP 8.4 with `pdo_pgsql`, `openssl`, `mbstring` and `curl`
- Composer 2 and Node.js 20+ (to build the CSS and JS)
- A running Aira API
- PostgreSQL: the Aira database, used only for this panel's `sessions`, `cache` and `jobs` tables

## Setup

```bash
composer install
npm install
npm run build                  # builds public/build (CSS, JS, plugins)
cp .env.example .env           # then fill in every YOUR_* value
php artisan key:generate
php artisan serve --port=8001
```

In `.env`:

- `AIRA_API_URL`: the API's base URL, without `/api/v1`, e.g. `http://127.0.0.1:8000`.
- `ADMIN_API_KEY`: the same value as `ADMIN_API_KEY` in the API's `.env`. Generate it once with `openssl rand -hex 32`.
- `DB_*`: the same Aira database as the API. The schema comes from the API project (`database/sql/aira_schema.sql`); this project has no migrations.

If every page shows empty tables, check that `ADMIN_API_KEY` matches on both sides; the API answers 401 otherwise. The cause is logged in `storage/logs/laravel.log`.

## Tests

```bash
php artisan test
```

The tests fake the API, so they need neither the API nor a database.

## Toaster messages

Controllers can flash a toast for the next page:

```php
session()->flash('toaster', [
    'type' => 'success',   // success, error, warning, info
    'message' => 'Saved!',
    'duration' => 3000,    // milliseconds, optional
]);
```

From JavaScript: `Toaster.success('Saved!')`, `Toaster.error(...)`, `Toaster.warning(...)`, `Toaster.info(...)`.
