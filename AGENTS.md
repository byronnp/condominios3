# Repository Guidelines

## Project Structure & Module Organization

This is a Laravel 13 backend for condominium administration. Core PHP code lives in `app/`.

- `app/Http/Controllers/Api/Auth`: JWT authentication endpoints.
- `app/Http/Controllers/Api/Admin`: administrative APIs for condominiums, houses, residents, fees, and payments.
- `app/Http/Controllers/Api/Resident`: resident-facing APIs for owned/authorized houses and invitations.
- `app/Models/Auth`, `app/Models/Condominium`, `app/Models/Billing`, `app/Models/Catalog`: domain models.
- `app/Models/Audit`: audit log model for per-condominium activity history.
- `app/Models/Menu`: database-driven navigation menu model.
- `app/Support`: API response helpers such as `ApiResponder`.
- `app/Transformers`: response transformers for stable API payloads.
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
- `php artisan db:seed`: create/update catalog defaults, the senior admin, and sample data.
- `php artisan fee-charges:generate-monthly --period=2026-06`: generate monthly fee charges for all active condominiums using their active rates. The scheduler runs this command on day 1 of each month at 00:05.

## Coding Style & Naming Conventions

Follow Laravel conventions and PSR-12 style. Use 4-space indentation for PHP. Run Laravel Pint before committing PHP formatting changes:

```bash
./vendor/bin/pint
```

Use singular model names (`House`, `Payment`) and plural table names (`houses`, `payments`). Keep API controllers grouped by role/module under `app/Http/Controllers/Api`. Use explicit namespaces when adding models to domain folders.

API controllers should return responses through `$this->responder`, not `response()->json()`. Use transformers from `app/Transformers`, for example:

```php
return $this->responder
    ->success($houses, [HouseTransformer::class, 'transform'])
    ->message('Casas obtenidas correctamente.')
    ->respond();
```

## Testing Guidelines

Use PHPUnit through Laravel’s test runner. Place HTTP/API behavior tests in `tests/Feature` and isolated logic tests in `tests/Unit`. Name tests after the behavior being verified, for example `ResidentCanAcceptHouseInvitationTest.php`.

Run tests before handoff:

```bash
php artisan test
```

Add focused tests when changing authentication, permissions, payments, balances, monthly fee generation, or invitation flows.

## Commit & Pull Request Guidelines

The current history only shows `carga inicial`, so no strict convention exists yet. Prefer short imperative commits, for example `Add resident invitation flow` or `Fix payment balance recalculation`.

Pull requests should include a concise summary, affected routes/models, migration notes, test results, and any required environment changes. Include screenshots only for UI-facing changes.

## Security & Configuration Tips

Do not commit secrets from `.env`. JWT uses `JWT_SECRET` or falls back to `APP_KEY`; production should define a strong `JWT_SECRET`. Admin routes must remain behind `jwt.auth` and `admin.access`. Resident routes must always verify house membership and pivot permissions such as `can_view_balance`, `can_make_payments`, and `can_invite_users`.

## Roles, Catalogs & Seed Data

The global administrator role is `senior_admin`; condominium-level administrators use `condominium_admin` through the `condominium_user` pivot and scoped permissions. Residents use `resident`.

Catalogs live in `catalogs` and `catalog_items`. User identification type is stored as `users.identification_type_id`, referencing a `catalog_items` row from `identification_types` (`cedula`, `ruc`, `passport`). House membership and invitation relationship type is stored as `relationship_type_id`, referencing `house_relationship_types` (`owner`, `spouse`, `family`, `tenant`, `representative`). Do not reintroduce text columns for catalog-backed values.

When a response field references a catalog item, expose it only as:

```json
{"id": 1, "name": "Cedula"}
```

This applies to `identification_type` and any future catalog-backed field, such as payment methods, relationship types, statuses, or custom-field options. Do not include catalog metadata such as `code`, `catalog_id`, `sort_order`, or `is_active` outside catalog-specific endpoints.

When a response field references a condominium through `condominium_id`, expose it as an object with only `id` and `name`, not as a raw integer:

```json
{"id": 1, "name": "Condominio Los Ceibos"}
```

Apply this rule to any future transformer that returns `condominium_id`.

Monthly alicuota values are configured in `condominium_fee_rates`. New active rates must start on day 1 of a month and are stored with `ends_at = null`; when a new rate is created, the previous open rate for the same condominium is closed automatically on the last day of the prior month. `fee_charges` are the per-house monthly charges generated from the active rate for a period; do not edit generated historical charges when a future rate changes. Use `POST /api/admin/condominium-fee-rates` to create a new rate and `POST /api/admin/fee-charges/generate-month` to generate charges for a specific period. Resident advance payments use `POST /api/resident/houses/{house}/advance-payments/preview` to calculate future months and `POST /api/resident/houses/{house}/advance-payments` to create missing charges only for that house and register payment details grouped by `payment_batches`.

Payment methods are catalog-backed but configured per condominium. `catalog_items` defines the generic type (`cash`, `transfer`, `deuna`, `kushpago`), and `condominium_payment_methods` stores the condominium-specific display name, instructions, enabled flag, and encrypted `config`. Store `condominium_payment_method_id` on `payments` and `payment_batches`; keep `payment_method_id` only as the generic catalog reference. Validate that the configured method belongs to the same house condominium and is enabled. Use `GET /api/admin/condominiums/{condominium}/payment-methods` to list configured methods, `POST /api/admin/condominiums/{condominium}/payment-methods` to configure one, and `PATCH /api/admin/condominium-payment-methods/{id}` to update it. Never return decrypted provider secrets in API responses; expose only `has_config`.

Audit logs live in `audit_logs` and are written through `App\Services\Audit\AuditLogger`. Use action names like `payment.created`, `payment.advance_created`, `fee_rate.created`, `fee_charge.generated`, `house.updated`, and `payment_method.updated`. API errors are also audited as `error.validation`, `error.not_found`, `error.http`, or `error.unhandled` with source `api_error`, sanitized input, route details, status code, and short stack traces only for 500 errors. Always include `condominium_id` when the action belongs to a condominium. Senior admins may query all logs through `GET /api/admin/audit-logs`; condominium admins only see logs for their managed condominiums. Never store secrets, passwords, tokens, provider keys, or decrypted payment method config in `old_values`, `new_values`, or `metadata`.

Navigation menus live in `menus`. Store icon names only, such as `home`, `building-2`, or `credit-card`, and let the frontend map them to the selected icon library. `GET /api/auth/me/menus` returns only the menus allowed for the logged-in user based on `required_role` and `required_permission`. Senior admins can manage menu records with `GET|POST|PATCH /api/admin/menus`; do not store SVG markup or frontend-specific icon components in the database.

Seed credentials:

- Senior admin: `admin@condominios.test` / `admin123`
- Condominium admin: `admin.ceibos@test.com` / `admin123`
- Resident sample: `juan.perez@test.com` / `resident123`

Sample data includes `Condominio Los Ceibos`, `Condominio Los Prados`, their admins, houses, fee rates, fee charges, one payment, and a house invitation.
