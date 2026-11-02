# NetSales — Project Overview

## 1. Project Purpose & Executive Summary
NetSales is an enterprise B2B sales management and financial reconciliation platform built to govern the commercial relationships between Internet Service Network Providers (*Networks*) and their authorized retail distributors (*Points of Sale / POS*).

The platform solves the complex commercial, operational, and financial challenges of distributing prepaid network packages:
- Contractual wholesale discount agreements per merchant.
- Multi-invoice credit tracking and credit facility enforcement.
- Automated debt aging analysis across chronological tiers.
- Atomic FIFO (First-In, First-Out) debt settlement and dual-wallet liquidity tracking.
- Multi-device security and fine-grained role-based access control.

---

## 2. Multi-Repository Architecture
The NetSales platform comprises two interconnected codebases:

1. **`smartixit/netsales_api` (Laravel 12 / PHP 8.3):**
   - **Role:** Sole authoritative backend for business rules, accounting policies, financial ledgers, and API endpoints.
   - **Architecture:** Controller → FormRequest → Domain Service → Eloquent Model / Repository → API Resource.
   - **Database:** MySQL / PostgreSQL with pessimistic row locking and transactional boundaries.

2. **`smartixit/netsales` (Flutter 3.x / Dart 3.x):**
   - **Role:** Cross-platform client for mobile and tablet form factors.
   - **Workspaces:** Strictly restricted to Network Workspace and POS Workspace.
   - **Architecture:** Feature-first structure powered exclusively by the GetX ecosystem (`GetxController`, `Bindings`, `AppPages`).

---

## 3. Verified System Actors
The system supports distinct business actors operating across isolated scopes:

| Actor | Workspace Scope | Primary Capabilities |
|---|---|---|
| **Network Owner** | Network Scope | Configures package discounts, approves/modifies procurement requests, sets POS credit limits, monitors receivables. |
| **Network Accountant** | Network Scope | Audits sales invoices, reconciles POS debt collections, inspects liquidity statements. |
| **Network User / Staff**| Network Scope | Assists in operational review of procurement requests within assigned permissions. |
| **POS Agent / Merchant**| POS Scope | Submits wholesale procurement requests, tracks available wallet balance, monitors debt aging, submits repayments. |
| **POS Accountant** | POS Scope | Monitors store procurement expenses, reconciles bank payments against wholesale invoices. |
| **POS Staff / User** | POS Scope | Submits procurement requests on behalf of the POS agent. |
| **Platform Super Admin**| Platform Scope | **Excluded from Mobile:** Manages SaaS subscriptions, platform licenses, and global system configurations via desktop platform. |

---

## 4. Technology Stack Verification

| Dimension | Laravel API (`netsales_api`) | Flutter Client (`netsales`) |
|---|---|---|
| **Runtime / SDK** | PHP 8.3 (Laravel 12) | Dart 3.x / Flutter 3.x |
| **Architecture** | Service-Oriented Layered Architecture | Feature-First Clean Architecture |
| **State Management** | N/A (Stateless REST API with Sanctum) | GetX (`GetxController`, `Bindings`) |
| **API Documentation**| `dedoc/scramble` (OpenAPI) | Scramble-compatible DTOs and DataSources |
| **Design System** | N/A | Custom Material 3 tokens (`AppColors`, `AppTypography`, `AppBreakpoints`) |
| **Code Formatter** | Laravel Pint | `dart format` |
| **Testing** | PHPUnit | Flutter Test / Mockito |
