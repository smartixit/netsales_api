# Security Policy

## Reporting

Do not publish exploitable vulnerability details in public issues. Report privately to the repository owner using GitHub's private vulnerability reporting feature when available, or the private contact channel designated by the owner.

## Secrets

Never commit `.env`, API tokens, signing keys, service-account files, database dumps, or production data. Rotate any credential accidentally committed; deleting the file from the latest commit is not sufficient.

## Scope

Security fixes should include a regression test where practical. Authentication, authorization, payment, cryptography, file upload, and tenant/isolation changes require explicit security review.
