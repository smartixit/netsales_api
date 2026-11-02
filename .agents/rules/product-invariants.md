---
trigger: always_on
description: Non-negotiable NetSales B2B product scope, operating workspaces, and explicit anti-definitions.
---

# Rule: NetSales Product and Business Invariants

## 1. Product Identity
NetSales is an enterprise **B2B Network and Point-of-Sale (POS) Sales Management System**.
The platform governs wholesale procurement orders, package tariffs, contracted POS discounts, dual wallets, debt ceilings, multi-invoice aging, and repayment settlements between Internet Service Networks and authorized retail Points of Sale.

## 2. Explicit Anti-Definitions [STRICT PROHIBITION]
- **NOT a Voucher-Card Inventory System:** The system does NOT manage card batches, serial numbers, PIN codes, digital vaults, or physical card stock.
- **NOT a Consumer Retail POS:** Points of sale do NOT use NetSales to sell cards or services to end-user citizens; they use it to procure wholesale stock from upstream network providers.
- **NOT an ISP Gateway:** The system does NOT interact directly with MikroTik, Cisco, RADIUS, or hotspot authentication servers.

## 3. Workspace Scopes
- **Platform Scope:** Governs global billing, SaaS licensing, and platform support (`SUPER_ADMIN`, `SUPPORT_AGENT`, general `ACCOUNTANT`). Belongs exclusively to the future standalone Windows desktop platform.
- **Network Scope:** Governs merchant network wholesale operations, pricing, POS approvals, and procurement requests (`NETWORK_OWNER`, `NETWORK_USER`, `NETWORK_ACCOUNTANT`).
- **POS Scope:** Governs retail merchant procurement, balance replenishment, and debt settlement (`POS_AGENT`, `POS_USER`, `POS_ACCOUNTANT`).
- Mobile and tablet clients are **strictly limited to Network and POS workspaces**.
