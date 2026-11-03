---
name: netsales-task-handoff
description: >-
  Standardized task completion, verification, and session handoff procedure for NetSales.
  Reviews acceptance criteria, inspects git diffs, executes verification commands, updates
  WORK_LOG.md, PROJECT_STATE.md, and DECISION_LOG.md, and halts at approval boundaries.
---

# Skill: NetSales Task Handoff & Session Completion Protocol

## Purpose
Ensures that all completed engineering tasks are rigorously verified, cleanly logged, and properly handed off. Guarantees that future sessions can seamlessly resume work from authoritative persistent records without confusion or lost work.

---

## 10-Step Mandatory Task Completion Procedure

1. **Review Acceptance Criteria:**
   - Audit the work performed against every acceptance criterion of the authorized task.
2. **Inspect Working Tree Diff:**
   - Run `git status --short` and `git diff --stat`.
   - Confirm only files within the authorized scope were modified.
3. **Run Real Verification Commands & Visual QA:**
   - Flutter: Run `flutter analyze` and relevant unit/widget tests.
   - UI Review: When UI code was changed, verify visual quality, absence of overflow exceptions, responsive layout (mobile & tablet), RTL flow, and dark/light palettes per `flutter-premium-ui-ux`.
   - Laravel: Run `vendor/bin/pint --format agent` and `vendor/bin/phpunit`.
   - Governance: Run `powershell -ExecutionPolicy Bypass -File scripts/verify-project-governance.ps1`.
4. **Report Actual Command Outcomes:**
   - Record exact command output, exit codes, and passing/failing checks.
   - Never report a check as passed unless it was actually executed and inspected.
5. **Classify All Created and Modified Files:**
   - Document every file created, edited, moved, or deleted.
6. **Update Work History Log:**
   - Append a new task entry to [`docs/project/WORK_LOG.md`](file:///d:/Smartix/NetSales/netsales_api/docs/project/WORK_LOG.md).
7. **Update Current Project State Snapshot:**
   - Update [`docs/project/PROJECT_STATE.md`](file:///d:/Smartix/NetSales/netsales_api/docs/project/PROJECT_STATE.md) if the milestone status, blocker status, or authorized next steps have changed.
8. **Record Material Decisions in Append-Only Log:**
   - If explicit user approval was received for a decision, update status to `APPROVED` in [`docs/project/DECISION_LOG.md`](file:///d:/Smartix/NetSales/netsales_api/docs/project/DECISION_LOG.md).
   - If a new architectural recommendation arose, record it as `PROPOSED`. Never mark an unapproved recommendation as `APPROVED`.
9. **Identify Unfinished Work & Known Limitations:**
   - Document any edge cases, deferred items, or branch divergences requiring attention in future milestones.
10. **Deliver Structured Handoff & Halt:**
    - Provide a complete final summary report with exact commit hashes, file inventories, and next steps.
    - Halt execution and wait for explicit user review and authorization.
