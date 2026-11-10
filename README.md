# Aira API

Laravel 12 backend for **Aira**, the HealthTalk voice assistant. It serves three callers:

| Caller | How it authenticates | What it uses |
|---|---|---|
| Aira web app and healthtalk-relay | JWT from the OTP login (`Authorization: Bearer <token>`) | Login, profile, saved conversations, ratings, Content Library search, guardrails, start pitch |
| Aira admin panel (server to server) | Shared secret in the `X-Admin-Key` header (`ADMIN_API_KEY`) | Content Library, guardrails, start pitch, conversation reports, SH users |
| Anyone | Nothing | `POST /api/v1/auth/send-otp`, `POST /api/v1/auth/chatbot/verify-otp`, `POST /api/v1/auth/chatbot/register`, `GET /api/v1/auth/states`, `GET /up` |

Run `php artisan route:list` for the full list (35 routes).

## Requirements

- PHP 8.4 with the `pdo_pgsql`, `openssl`, `mbstring`, `curl` and `fileinfo` extensions
- Composer 2
- PostgreSQL 15 or newer with the [pgvector](https://github.com/pgvector/pgvector) extension installed
- A Google Cloud project with Vertex AI, Cloud Storage and Text-to-Speech enabled, and a service-account JSON key
- An SMS Gateway Hub account for login OTPs (not needed when `APP_ENV=local`)

## Setup

```bash
composer install
cp .env.example .env            # then fill in every YOUR_* value
php artisan key:generate        # skip if you reuse an existing APP_KEY (see below)
php artisan jwt:secret          # writes JWT_SECRET to .env
```

### Database

The project has no migrations. The schema is in one SQL file:

```bash
createdb aira_db
psql -d aira_db -v ON_ERROR_STOP=1 -f database/sql/aira_schema.sql
php artisan db:seed             # the 36 states for the registration form (safe to re-run)
```

To use an existing Aira database instead, point `DB_*` at it and skip the steps above.

**`APP_KEY` and existing data.** Mobile numbers in `users`, `saathi_beneficiary` and `saathi_community_entrepreneur` are encrypted with a key derived from `APP_KEY`. On an existing database, use the same `APP_KEY` that encrypted them. A different key makes those numbers unreadable, so returning users can't log in.

### Run

```bash
php artisan serve --port=8000
```

Content Library indexing (PDF text extraction and embeddings) runs after the HTTP response, inside the same PHP process, so no queue worker is needed. To re-index by hand:

```bash
php artisan healthtalk:index-content          # pending and failed items
php artisan healthtalk:index-content --all    # everything (after changing the embedding model)
```

## Connecting the other apps

- **Admin panel:** set its `AIRA_API_URL` to this API's base URL and its `ADMIN_API_KEY` to the same value as here.
- **Aira web app and healthtalk-relay:** point their API base URL at `<APP_URL>/api/v1`. They only pass the user's JWT back, so they don't need `JWT_SECRET`.

## Login OTP

`POST /api/v1/auth/send-otp` with `mobile_no` and `role_slug=ai-chatbot`. With `APP_ENV=local`, the OTP is always `102025` and nothing is sent, so never deploy with `APP_ENV=local`. In every other environment a random 6-digit OTP goes out by SMS and WhatsApp.

## Tests

```bash
php artisan test
```

## Database tables

The schema has 27 tables:

- **Used by the app:** the nine `ht_*` tables, `users`, `saathi_states`, `saathi_beneficiary`, `saathi_community_entrepreneur`, `saathi_login_log`, `sessions`, `cache`, `cache_locks`, `jobs`, `job_batches`, `failed_jobs`, `migrations`.
- **Kept but unused:** `roles`, `permissions`, `role_has_permissions`, `model_has_roles`, `model_has_permissions`, `password_resets`. They're kept so the schema matches the cleaned database.
