---
trigger: always_on
description: Mandatory user approval boundaries, decision statuses, scope control, and session handoffs.
---

# Rule: Milestone and Approval Governance

## 1. Explicit Approval Prerequisite
- No milestone implementation may begin without explicit user authorization for that specific milestone.
- An agent recommendation, generated architectural report, inferred requirement, or discussion **never** constitutes user approval.
- Keep proposed changes in `PROPOSED` status until explicitly approved by the user.

## 2. Decision Log Discipline
- All architectural and business decisions are tracked in `docs/project/DECISION_LOG.md`.
- Valid decision statuses are strictly:
  - `PROPOSED`: Under evaluation, no implementation permitted.
  - `APPROVED`: Explicitly approved by user, implementation authorized.
  - `REJECTED`: Explicitly rejected by user, must not be implemented.
  - `SUPERSEDED`: Replaced by a newer approved decision.
- Never silently change a decision status or assume approval on the user's behalf.

## 3. Working Baseline and Known Limitations
- When milestone reports are accepted as the project discovery baseline, preserve known limitations and backlog items in `docs/project/PROJECT_STATE.md`.
- Do not reopen settled baseline milestones for repetitive audits unless direct source discrepancies require targeted verification.

## 4. Session Handoff Protocol
- Every task must end with an updated `docs/project/WORK_LOG.md` entry recording exact git commits, files changed, and commands executed.
- If the authorized task or milestone status changes, update `docs/project/PROJECT_STATE.md`.
