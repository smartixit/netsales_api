---
name: netsales-session-protocol
description: >-
  Mandatory context restoration procedure at the beginning of every new Antigravity session
  working on the NetSales platform. Reads root instructions, current project state, active
  milestones, pending decisions, dynamic governance inventory, and verifies technical write gates before any work begins.
---

# Skill: NetSales Session Initialization Protocol

## Purpose
Establishes an accurate, authoritative understanding of the current NetSales project state at the start of every session. Eliminates context loss, prevents repeating settled discussions, and blocks unauthorized feature implementation. Enforces complete line-by-line reading coverage of all governance documents across both repositories before technical write gates allow code modifications.

---

## 1. Mandatory Dynamic Governance Inventory Reading Procedure

Before modifying any project files, running mutating commands, or performing implementation tasks, every new Antigravity task and conversation must complete this mandatory reading procedure across BOTH repositories (`netsales` and `netsales_api`):

1. **Discover Dynamic Inventory:**
   - Scan both repositories dynamically to discover all files across:
     - **Category A (Agent Instructions):** Root and nested `AGENTS.md`, `GEMINI.md`.
     - **Category B (Rules):** All `.agents/rules/*.md` files (and any `.ai/rules/` if present).
     - **Category C (Skills):** All `.agents/skills/**/SKILL.md` files and referenced supporting docs.
     - **Category D (Project Documentation):** All canonical documents under `docs/project/` (`PROJECT_OVERVIEW.md`, `PRODUCT_SCOPE.md`, `DOMAIN_MAP.md`, `ARCHITECTURE.md`, `API_CONTRACTS.md`, `RBAC_AND_TENANT_CONTEXT.md`, `FINANCIAL_INVARIANTS.md`, `PROJECT_STATE.md`, `DECISION_LOG.md`, `WORK_LOG.md`, `GOVERNANCE.md`, and client references).
2. **Read Every Document Completely:**
   - Read each inventoried document from its first line (`StartLine: 1`) through its final line without selective omission.
3. **Sequential Reading for Long Files:**
   - For long files exceeding the per-read limit (e.g. `WORK_LOG.md`), read in contiguous sequential chunks (e.g. lines 1–800, 801–1600, etc.) without skipping intermediate sections.
4. **Record Successful File-Read Ranges:**
   - Each successful read call records its exact start and end line bounds.
5. **Merge Overlapping & Adjacent Ranges:**
   - Combine overlapping and adjoining line intervals into continuous covered segments.
6. **Mark File Complete Upon 100% Coverage:**
   - A file is marked `COMPLETE` in the reading ledger only when its entire line range `[1, totalLines]` is continuously covered.
7. **Recheck File Hash After Reading:**
   - Compute SHA-256 after reading to verify content stability during preflight.
8. **Restart Reading on File Modification:**
   - If any mandatory document's content changes during the session, previous coverage is invalidated and the file must be re-read.
9. **Recursively Resolve Mandatory References:**
   - Resolve and read all documents and skills cross-referenced by inventoried files.
10. **Produce Verification Summary:**
    - Explicitly report the completed inventory and ledger status before proceeding with task execution.

> [!IMPORTANT]
> This reading procedure applies to every task and every new conversation regardless of whether the task changes Flutter, Laravel, documentation, UI, APIs, tests, or architecture. Task relevance never excuses skipping mandatory governance documents. The Antigravity technical write gate (`PreToolUse`) hard-denies all file mutations and mutating commands until 100% reading coverage is verified.

---

## 2. 10-Step Mandatory Initialization Procedure

1. **Read Root Governance:**
   - In Laravel API: Read [`netsales_api/AGENTS.md`](file:///d:/Smartix/NetSales/netsales_api/AGENTS.md).
   - In Flutter: Read [`netsales/AGENTS.md`](file:///d:/Smartix/NetSales/netsales/AGENTS.md).
2. **Review Applicable Invariant Rules:**
   - Read `.agents/rules/product-invariants.md`.
   - Read `.agents/rules/financial-and-accounting-invariants.md`.
   - Read `.agents/rules/security-and-tenant-isolation.md`.
   - Read `.agents/rules/milestone-and-approval-governance.md`.
   - Read `.agents/rules/laravel-architecture-and-services.md` (Backend) / `flutter-architecture-and-getx.md` & `flutter-ui-ux-engineering-standard.md` (Frontend).
3. **Read Current Project Snapshot:**
   - Read [`docs/project/PROJECT_STATE.md`](file:///d:/Smartix/NetSales/netsales_api/docs/project/PROJECT_STATE.md).
   - Identify the current authorized milestone and active limitations.
4. **Inspect Decision Register:**
   - Review recent entries in [`docs/project/DECISION_LOG.md`](file:///d:/Smartix/NetSales/netsales_api/docs/project/DECISION_LOG.md).
   - Note which decisions are `APPROVED` vs `PROPOSED`.
5. **Inspect Recent Execution History:**
   - Read the latest entries in [`docs/project/WORK_LOG.md`](file:///d:/Smartix/NetSales/netsales_api/docs/project/WORK_LOG.md).
6. **Verify Working Tree Status:**
   - Run `git status --short` in both repositories.
   - Confirm HEAD commits match the documented baseline.
7. **Read Domain & Architecture Documentation:**
   - For backend tasks: Read [`ARCHITECTURE.md`](file:///d:/Smartix/NetSales/netsales_api/docs/project/ARCHITECTURE.md) and [`API_CONTRACTS.md`](file:///d:/Smartix/NetSales/netsales_api/docs/project/API_CONTRACTS.md).
   - For frontend tasks: Read [`shared-contract-reference.md`](file:///d:/Smartix/NetSales/netsales/docs/project/shared-contract-reference.md).
8. **Load Domain-Specific Engineering Skill:**
   - Backend: Activate `laravel-api-engineering`.
   - Frontend: Activate `flutter-feature-engineering`.
9. **Identify Task Scope & Prohibitions:**
   - Confirm the user's prompt matches the authorized milestone.
   - Explicitly verify that no unapproved milestone implementation is attempted.
10. **Reconcile Documentation with Actual Source:**
    - Verify class names, endpoints, and Enums in actual code before making factual statements to the user.
