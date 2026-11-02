---
trigger: always_on
description: Mandatory backend authorization, tenant scoping, and membership isolation rules.
---

# Rule: Security and Tenant Isolation Invariants

## 1. Backend Authorization is Sole Security Boundary
- All authentication and authorization decisions must be made authoritatively on the Laravel API backend.
- Client-supplied workspace selectors, UI flags, and `role_scope` are UX presentation aids, **never** security controls.
- Never trust client-side claims regarding tenant ownership, active role, or granted permissions.

## 2. Multi-Tenant Scoping and Active Membership
- Every request targeting a specific Network or POS resource must authorize the authenticated user against active tenant membership (`NetworkUser` or `PosUser`).
- Permissions are evaluated within the active tenant context (`User::hasPermission($key, $tenantId)`). Global user permissions or primary roles do not automatically grant rights across tenant boundaries.
- Users with multiple tenant memberships must have each action validated against the specific target tenant context of that request.

## 3. Least Privilege & Role Boundaries
- Roles belong to strict scopes (`RoleScope::PLATFORM`, `RoleScope::NETWORK`, `RoleScope::POS`).
- Platform roles (`SUPER_ADMIN`, `SUPPORT_AGENT`) must not be accessible via mobile client endpoints.
- Accountant roles (`NETWORK_ACCOUNTANT`, `POS_ACCOUNTANT`) are strictly financial read/export roles and must not be granted administrative or entity-mutating permissions.
