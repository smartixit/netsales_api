# Laravel API Test Strategy

## Required layers

- Unit tests for isolated domain/service logic where isolation provides value.
- Feature tests for routes, middleware, validation, authentication, authorization, JSON responses, database changes, and error handling.
- Controlled integration tests for external services or infrastructure boundaries.
- A small end-to-end suite for the highest-value real workflows when the deployed test environment supports it.

## API behavior to test

For every important endpoint, cover the relevant subset of:

- Successful request and exact expected status code.
- Invalid input and field-level validation errors.
- Unauthenticated request (401) and authenticated but unauthorized request (403).
- Resource ownership / tenant boundaries; never allow cross-user data access.
- Not found (404), conflict (409), rate limiting (429), and server failure behavior where relevant.
- Response envelope, required fields, data types, nullability, enum values, date/time format, pagination, and headers.
- Database side effects, transactions, duplicate requests, and idempotency where applicable.

## Isolation

- Use a dedicated test database. Never point `php artisan test` or migrations at production.
- Prefer factories and Laravel's documented database testing patterns.
- Mock external services in routine tests or use their official sandbox.
- Keep secrets out of source control and CI logs.

## Baseline commands

```powershell
composer validate --strict
composer audit
vendor/bin/pint --test
vendor/bin/phpstan analyse --no-progress --memory-limit=1G
php artisan test
```

## API contract documentation

When this project exposes an API, consider installing Laravel Scramble as a development dependency and exporting an OpenAPI 3.1 document. Follow `docs/api/API_CONTRACT_AR.md`. Keep the contract review and Laravel feature tests in the same pull request as endpoint changes.
