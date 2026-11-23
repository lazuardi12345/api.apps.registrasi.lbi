# LBI Registration API

Laravel API for the LBI FIB UI registration, enrollment, and administration applications.

## Requirements

- PHP 8.2 or newer
- Composer
- A database supported by Laravel (SQLite is convenient for local development)

## Local setup

From the backend project root:

```powershell
composer install
Copy-Item .env.example .env
New-Item -ItemType File -Path database\database.sqlite
php artisan key:generate
php artisan migrate
php artisan serve
```

Configure `.env` for your local database and integrations before running migrations. Never commit `.env` or put production credentials in `.env.example`.

Run the test suite with:

```powershell
php artisan test
```

## Legacy data imports

Legacy import files can include personal data and are intentionally kept outside this repository in `../private-backend-data/`. To validate the local import sources without writing to the database:

```powershell
php artisan legacy:imports --directory=../private-backend-data/legacy-imports --sql-file=../private-backend-data/legacy_webform_subset.sql --dry-run
```

Only run the command without `--dry-run` when you intend to import those records into the selected database and have verified the target. Do not copy private import data or test tokens to a shared server.

The placement-test token seeder expects its local test data at `../private-backend-data/testing/placement-test-tokens.json` by default. It fails explicitly when the file is absent; do not run it in production.

## Production

Set production environment variables outside the repository. In particular, use unique `APP_KEY` and `JWT_SECRET` values, set `APP_DEBUG=false`, and configure production database, mail, storage, and CORS settings before deploying.
