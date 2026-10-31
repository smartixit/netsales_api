# Laravel Repository Review Notes

> Fill in this file for each API. Keep it short and specific to this repository.

## Project decisions

- Laravel / PHP versions: [exact supported versions]
- Authentication mechanism: [Sanctum / Passport / custom / other]
- Authorization model: [policies, gates, roles, ownership, tenancy]
- API versioning and response envelope: [record contract]
- Database and migration policy: [record engine, supported versions, rollback rules]
- Queues, scheduler, and retry/idempotency policy: [record details]
- File storage and visibility: [record disks, authorization, signed URL policy]

## High-risk areas to review

- Server-side authorization for every resource/action; never trust client-supplied ownership or role fields.
- Mass assignment, validation, serialization, and hidden/visible model attributes.
- Token/session lifecycle, revocation, rate limiting, and enumeration.
- Tenant/provider boundaries and data isolation, if applicable.
- Transactions, race conditions, locking, unique constraints, and duplicate requests.
- Upload/download authorization, MIME/content checks, size limits, and path isolation.
- Queue retries, idempotency, scheduled jobs, and external API calls.
- Sensitive logging, exception disclosure, secrets, backups, and environment configuration.

## Generated or excluded files

- Generated files: [list paths and how they are regenerated]
- Files that reviewers should normally skip: [list only justified paths]

## Verification commands

- Dependency validation: `composer validate --strict`
- Dependency audit: `composer audit`
- Formatting: `vendor/bin/pint --test`
- Static analysis: `vendor/bin/phpstan analyse --no-progress --memory-limit=1G`
- Tests: `php artisan test`
- Database/integration tests: [document isolated test database and setup]

## Repository-specific failure patterns

- [Add concrete defects that have occurred in this repository and how to detect them.]
