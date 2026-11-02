---
name: api-contract-and-financial-safety
description: >-
  Verifies consistency, serialization contracts, and financial safety boundaries between
  the Laravel API backend and the Flutter mobile client in the NetSales platform.
---

# Skill: NetSales API Contract & Financial Safety Protocol

## Purpose
Prevents contract drift, enum serialization mismatches, and financial integrity violations between `netsales_api` and `netsales`.

---

## Verification Checklist

1. **Enum Parity & Wire Serialization:**
   - Verify that incoming string values match the exact canonical wire casing defined in `netsales_api/app/Enums/`.
   - Ensure the Flutter enum parser provides safe fallbacks for unknown future enum values to prevent runtime crashes.
2. **Resource Field Mapping:**
   - Confirm exact JSON field names match between Laravel API Resources and Flutter DTOs (e.g. `log_operation_type` for wallet logs, `payment_status` for invoices, `status` for requests).
   - Ensure localized `*_label` fields are used for display when available.
3. **Financial Authority Boundary:**
   - Confirm that zero financial calculations, invoice totals, discount deductions, or repayment allocations are computed in Flutter.
   - All financial amounts must originate directly from the server.
4. **Previews vs. Final Settlements:**
   - Previews (`POST /requests/{id}/preview-modification` or future repayment preview) are non-binding and informational.
   - Settlement allocations are calculated atomically by Laravel during the final collection transaction under row locks.
5. **Multi-Tenant Header Propagation:**
   - Ensure Flutter passes the active tenant ID header/parameter for scoped requests.
   - Ensure Laravel validates that the authenticated user holds active membership in that tenant.
