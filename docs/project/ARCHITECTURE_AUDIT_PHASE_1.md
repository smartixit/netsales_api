# Phase 1 — Backend Architecture Inventory & Evidence

**Audit baseline:** `main` at `6ed2c9f1943382e6f554fcae8d901b8376dddf21`  
**Audit branch:** `audit/phase-1-backend-architecture-inventory` (created from that exact `main` commit)  
**Scope:** Laravel API source architecture, responsibility boundaries, validation, financial safety, and current CI enforcement.  
**Status:** Source audit completed; implementation is not part of this audit.

## 1. Audit method and limits

The review used the current repository tree, the governing `AGENTS.md`, Laravel architecture rules, applicable engineering skills, `TESTING.md`, and the canonical project architecture/domain/contract/financial/RBAC documents. The relevant controllers, services, repository interfaces/implementations, models, and CI workflow were then inspected at source level.

This is an evidence inventory, not a blanket line-count refactor. A long service is not automatically an architectural violation. The findings below distinguish direct boundary breaches from high-cohesion candidates that require a call-graph review before any extraction.

No local PHP runtime or working tree is available in this session. No PHPUnit, Pint, PHPStan, migration, route-list, or governance script command is claimed as executed in this audit branch. The previous Phase 0 CI run is recorded separately in the project state.

## 2. Current backend surface

| Area | Files at audit baseline |
|---|---:|
| API controllers | 42 |
| FormRequests | 139 |
| Services | 112 |
| Repositories | 38 |
| Eloquent models | 95 |
| API Resources | 71 |
| Jobs | 5 |
| Enums | 42 |
| PHP files under `tests/` | 50 |

The principal dependency direction declared by the repository is:

`Route → FormRequest → thin Controller → Domain Service → Repository / Model`, with API Resources owning JSON serialization and Jobs owning deferred execution.

This remains the correct target. Business rules, authorization that depends on tenant/domain state, and financial computations must not migrate into controllers merely to make files shorter.

## 3. Confirmed architecture findings

### API-01 — Operational report controller owns persistence and preset business rules

**Evidence:** `app/Http/Controllers/API/V1/OperationalReportController.php`  
**Reference:** main blob `af6a1525db93ca8cb95d4dd4efb7144bb705376c`.

- `storeExport()` creates `OperationalReportExport` directly in the controller (around line 129) after using an authorization service and provider validation. The controller is coordinating HTTP and also owns persistence.
- `presets()`, `storePreset()`, `updatePreset()`, and `destroyPreset()` perform direct Eloquent queries and mutations (around lines 158–314).
- `storePreset()` also owns the maximum-preset count and duplicate-name business checks before direct creation.
- Some preset actions validate a generic `Request` inline rather than using a dedicated FormRequest.

**Required direction:** move preset query/create/update/delete, uniqueness/limit enforcement, and export-record creation into reporting services (and repositories where the project uses them). Keep the controller responsible for extracting typed FormRequest data, invoking the service, dispatching the accepted job when appropriate, and shaping the HTTP response.

### API-02 — Trial eligibility controller contains a use-case implementation

**Evidence:** `app/Http/Controllers/API/V1/Billing/BillingTrialEligibilityController.php`  
**Reference:** main blob `9949bddcb85051fe29c118023e337b1f797c6cdf`.

`show()` directly loads billing accounts, prices, and related plan/policy graphs; checks active/public/published/currency conditions; resolves trial and dunning versions; invokes eligibility logic; writes a `BillingTrialEligibilityCheck`; queries for existing live subscriptions; and composes purchase blockers. This is a complete business use case in an HTTP controller, not just response coordination.

**Required direction:** move this operation into a dedicated Billing application/domain service that owns the account/price checks, policy resolution, eligibility audit record, duplicate-subscription check, and typed result. Keep the controller thin and preserve the explicitly non-binding nature of the eligibility preview.

### API-03 — Network/POS relationship controller performs tenant queries and authorization filters

**Evidence:** `app/Http/Controllers/API/V1/NetworkPosController.php`  
**Reference:** main blob `ef8ba34c875ce846cd2495c447ab93d56bbe995c`.

- `index()` obtains network and POS IDs using Eloquent membership/ownership relationships and injects those values into query filters (around lines 64–78).
- `batchDelete()` and `batchStatus()` execute direct `NetworkPos` Eloquent queries with `whereDoesntHave()` to determine whether any selected link is outside the current user's network scope (around lines 330–400).

This couples HTTP endpoints to the data model and duplicates tenant-scope authorization logic in controller methods.

**Required direction:** centralize the scoped selection/authorization in the existing tenant authorization/domain service or a focused NetworkPos service/repository operation, with feature tests proving owner, active member, inactive member, and cross-network behavior. A refactor must preserve atomic all-or-nothing bulk semantics.

### API-04 — Voucher-discount bulk actions read and authorize records in the controller

**Evidence:** `app/Http/Controllers/API/V1/VoucherPackageDiscountController.php`  
**Reference:** main blob `7e716cc8652b28d8938928970b7d28893e37630b`.

The three bulk endpoints query live, soft-deleted, and with-trashed `VoucherPackageDiscount` records directly (around lines 461–590), perform existence-count checks, iterate policies through Gate, and only then call the service for the mutation. The controller is therefore doing data access and batch authorization orchestration.

**Required direction:** move batch resolution and authorization into a cohesive service/policy operation. Preserve the distinction between live, trashed, and permanently deleted records; preserve missing-ID behavior and atomic rejection when any record is unauthorized.

### API-05 — Billing policy controller mixes Eloquent query construction with inline HTTP validation

**Evidence:** `app/Http/Controllers/API/V1/Billing/BillingPolicyController.php`  
**Reference:** main blob `311351e3cf0b6c9c975f7e3098e3a838db52db5c`.

The policy list/version/pending-redemption methods build Eloquent queries and cursor paginators in the controller. Mutation methods call `$request->validate()` inline. The controller injects `BillingPolicyManagementService`, but some of the read path and request-validation boundary remains in the controller.

**Required direction:** introduce dedicated FormRequests for list filters and mutation inputs, and have a Billing policy read/management service own query and pagination behavior. Return Resources or explicit response DTOs so serialization remains a separate concern.

### API-06 — Generic Request input extraction is widespread and bypasses the declared FormRequest boundary

The architecture rule states that FormRequests are the sole authority for HTTP validation, type coercion, and initial authorization. Source scans found recurring `Request::only()` and `Request::validate()` usage in endpoint controllers, including:

- `PosController::index()` (generic `Request::only` plus direct Eloquent tenant scoping).
- `ExpenseController` list/trashed filters.
- `BankAccountController` list and active-account filters.
- `BillingFinanceController`, `BillingSelfServiceController`, and `BillingCatalogController`.
- `NetworkMembershipController` and `PosMembershipController`.
- `BillingPolicyController`.

Not every read-only filter requires a large request class, but every client-supplied input must have an explicit validation/allowlist boundary before it reaches services or repositories. The follow-up inventory must create focused FormRequests for reusable filter contracts rather than simply wrapping raw maps in new classes.

## 4. Financial precision finding requiring correction

### FIN-01 — Debt repayment aggregation converts decimal values to floating point

**Evidence:** `app/Services/DebtRepaymentService.php`  
**Reference:** main blob `82456fe547f5cf8fc0cd112f6e21b7ba9886ebd8`.

- Outstanding debt and aging buckets are aggregated through `round((float) ...->sum(...), 2)` around lines 74–81.
- Settlement eligibility uses `round((float) $invoices->sum('remaining_amount'), 2)` around line 282 and a floating tolerance of `0.001` around lines 284–292.

The repository already contains `App\\Support\\DecimalAmount`, and the financial invariants require integer-minor-unit precision for authoritative financial calculations. The repayment path should use exact minor-unit conversion, checked integer addition, and exact comparisons. The feature tests must cover one-minor-unit boundaries, exact full settlement, overpayment by one minor unit, and large aggregated debt values.

This is recorded as a financial correctness finding, not merely a code-style preference.

## 5. Large services — candidates, not automatic violations

The following files are unusually large or span multiple operations. Their size is a reason to map their responsibilities, not permission to split them without proof:

| Service | Approx. lines | Source-level observation |
|---|---:|---|
| `AuthService` | 1,259 | Login, dashboard login, 2FA, registration, sessions/devices, recovery and password flows coexist. Likely split by independently testable use-case boundaries after dependency mapping. |
| `SaleInvoiceService` | 888 | Request-derived and direct invoice creation, sale-type changes, deletion lifecycle, trend reads. Core invoice lifecycle is cohesive; query/report extraction needs evidence. |
| `PosWalletService` | 830 | Wallet lifecycle, credit, deposits, withdrawals, transfers, reversals, logs, SMS. Preserve lock order and financial transaction invariants if partitioned. |
| `RequestService` | 746 | Procurement request lifecycle, quote/preview, approval/invoicing, rejection/cancel and deletion. Preserve atomic approval and pricing boundaries. |
| `DebtRepaymentService` | 594 | Receivable summaries, aging, collection and reversal. The float precision finding above is independently confirmed. |

`BillingInvoiceService` (476 lines) is a good example of a cohesive service owning invoice lifecycle transactions and row locks; it should not be split just because it has multiple methods. The same evidence standard applies to every candidate.

## 6. Automated verification assessment

The API currently has one Laravel CI workflow and 50 PHP test files under `tests/`. No dedicated architecture-boundary test file was found in the test tree.

Two CI issues are confirmed in `.github/workflows/ci.yml` (main blob `c19f8eed7c8b60868a05316aa35445e5e1b5a476`):

1. The full PHPStan step has `continue-on-error: true` and ID `full-phpstan` (around line 160), but the final enforcement condition (around line 171) does **not** inspect `steps.full-phpstan.outcome`. A failing full analysis can therefore fail to fail the job.
2. The workflow runs auto-fixing `vendor/bin/pint` before `vendor/bin/pint --test`. The formatted working-tree files are packaged as an artifact, but the fix is not committed to the PR branch by that step. Consequently the later `--test` can validate the formatter-mutated working tree rather than the exact source committed by the PR.

**Required direction for Phase 2:** make every required quality result part of the final enforcement condition; run non-mutating style verification against the checked-in tree; keep formatter-generated patch artifacts advisory or explicitly apply them and require the resulting patch to be committed. Add automated architecture checks for direct Eloquent/DB work in controllers, inline request validation, and forbidden layer dependencies, with a narrow documented allowlist for justified exceptions.

## 7. Backend refactoring order

The evidence supports this order:

1. Close the CI enforcement holes and establish architecture tests before broad refactoring.
2. Extract the confirmed controller responsibilities in reporting, billing eligibility/policies, network-POS scoping, and discount bulk operations, each with its own typed FormRequests and focused feature tests.
3. Move generic Request filter contracts onto validated FormRequests, prioritizing tenant-scoped and finance-facing endpoints.
4. Correct DebtRepaymentService precision with the existing DecimalAmount helper and boundary tests.
5. Only then consider splitting large services, guided by call graphs, transaction boundaries, cohesive use cases, and single-responsibility tests. Keep all accounting logic in the backend Service/Domain layer.

## 8. Audit conclusion

Phase 1 confirms real controller/data/validation boundary breaches and a repayment precision concern. It also confirms that several large financial services are already the appropriate owners of transactions and business rules, so an arbitrary line-count split would make the architecture worse, not better.

**No application source was changed by this audit.** The next engineering step is Phase 2: preventive architecture/CI enforcement, followed by the planned feature-by-feature refactors. All changes must continue from a fresh branch created from the then-current `main`, with a dedicated PR and exact-HEAD CI verification.
