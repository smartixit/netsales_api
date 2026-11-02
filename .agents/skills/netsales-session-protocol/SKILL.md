---
name: netsales-session-protocol
description: >-
  Mandatory context restoration procedure at the beginning of every new Antigravity session
  working on the NetSales platform. Reads root instructions, current project state, active
  milestones, pending decisions, and verifies git cleanliness before any work begins.
---

# Skill: NetSales Session Initialization Protocol

## Purpose
Establishes an accurate, authoritative understanding of the current NetSales project state at the start of every session. Eliminates context loss, prevents repeating settled discussions, and blocks unauthorized feature implementation.

---

## 10-Step Mandatory Initialization Procedure

1. **Read Root Governance:**
   - In Laravel API: Read [`netsales_api/AGENTS.md`](file:///d:/Smartix/NetSales/netsales_api/AGENTS.md).
   - In Flutter: Read [`netsales/AGENTS.md`](file:///d:/Smartix/NetSales/netsales/AGENTS.md).
2. **Review Applicable Invariant Rules:**
   - Read `.agents/rules/product-invariants.md`.
   - Read `.agents/rules/financial-and-accounting-invariants.md`.
   - Read `.agents/rules/security-and-tenant-isolation.md`.
   - Read `.agents/rules/milestone-and-approval-governance.md`.
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
