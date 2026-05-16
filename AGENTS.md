# Repository Guidelines

## Project Structure & Module Organization

This is a Laravel 13 backend for condominium administration. Core PHP code lives in `app/`.

- `app/Http/Controllers/Api/Auth`: JWT authentication endpoints.
- `app/Http/Controllers/Api/Admin`: administrative APIs for condominiums, houses, residents, fees, and payments.
- `app/Http/Controllers/Api/Resident`: resident-facing APIs for owned/authorized houses and invitations.
- `app/Models/Auth`, `app/Models/Condominium`, `app/Models/Billing`: domain models.
- `app/Services`: shared services such as JWT handling.
- `routes/api.php` loads module route files from `routes/api/`.
- `database/migrations`, `database/seeders`, and `database/factories` contain database setup.
- `tests/Feature` and `tests/Unit` contain PHPUnit tests.
- `resources/` and `public/` hold frontend assets and public files.

## Build, Test, and Development Commands

- `composer install`: install PHP dependencies.
- `npm install`: install frontend tooling.
- `composer run dev`: run Laravel server, queue listener, logs, and Vite together.
- `php artisan serve`: run only the Laravel HTTP server.
- `npm run dev`: start Vite for frontend assets.
- `npm run build`: build production assets.
- `composer run test` or `php artisan test`: run the PHPUnit test suite.
- `php artisan migrate`: apply database migrations.
- `php artisan db:seed`: create/update the default admin user.

## Coding Style & Naming Conventions

Follow Laravel conventions and PSR-12 style. Use 4-space indentation for PHP. Run Laravel Pint before committing PHP formatting changes:

```bash
./vendor/bin/pint
```

Use singular model names (`House`, `Payment`) and plural table names (`houses`, `payments`). Keep API controllers grouped by role/module under `app/Http/Controllers/Api`. Use explicit namespaces when adding models to domain folders.

## Testing Guidelines

Use PHPUnit through Laravel’s test runner. Place HTTP/API behavior tests in `tests/Feature` and isolated logic tests in `tests/Unit`. Name tests after the behavior being verified, for example `ResidentCanAcceptHouseInvitationTest.php`.

Run tests before handoff:

```bash
php artisan test
```

Add focused tests when changing authentication, permissions, payments, balances, or invitation flows.

## Commit & Pull Request Guidelines

The current history only shows `carga inicial`, so no strict convention exists yet. Prefer short imperative commits, for example `Add resident invitation flow` or `Fix payment balance recalculation`.

Pull requests should include a concise summary, affected routes/models, migration notes, test results, and any required environment changes. Include screenshots only for UI-facing changes.

## Security & Configuration Tips

Do not commit secrets from `.env`. JWT uses `JWT_SECRET` or falls back to `APP_KEY`; production should define a strong `JWT_SECRET`. Admin-only routes must remain behind `jwt.auth` and `role:admin`. Resident routes must always verify house membership and pivot permissions such as `can_view_balance`, `can_make_payments`, and `can_invite_users`.
