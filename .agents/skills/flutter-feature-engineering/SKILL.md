---
name: flutter-feature-engineering
description: >-
  Standardized procedure for implementing new Flutter features in NetSales.
  Enforces 3-layer Clean Architecture (data, domain, presentation), GetX state management,
  mandatory reuse of lib/widgets/ and design tokens, and zero client-side financial calculations.
---

# Skill: NetSales Flutter Feature Engineering

## Purpose
Guides the implementation of Flutter features in NetSales according to established project conventions, Clean Architecture layers, and GetX state management.

---

## 1. Feature Clean Architecture Structure

Every feature under `lib/features/<feature_name>/` must follow this exact 3-tier structure:

```text
lib/features/<feature_name>/
├── data/
│   ├── datasources/     # Remote and local API datasources (HTTP/Dio requests)
│   ├── models/          # Data transfer models (fromJson/toJson, API mappings)
│   └── repositories/    # Repository implementations (implements domain repository contracts)
│
├── domain/
│   ├── entities/        # Pure business entities (immutable domain models)
│   ├── repositories/    # Abstract repository interfaces/contracts
│   └── usecases/        # Reusable domain use cases encapsulating business actions
│
└── presentation/
    ├── screens/         # Full-screen responsive views and layouts
    ├── widgets/         # Feature-specific atomic UI components
    ├── controllers/     # GetxControllers managing view state and invoking use cases
    └── bindings/        # GetX Bindings instantiating controllers and dependencies
```

---

## 2. Layer Responsibilities & Data Flow

1. **Presentation Layer:**
   - **Screens:** Render responsive views using `AppBreakpoints`.
   - **Widgets:** Feature-scoped UI components. Reuses core components from `lib/widgets/`.
   - **Controllers:** Subclasses of `GetxController`. Exposes reactive state (`Rx`, `.obs`) to UI and delegates actions to domain Use Cases.
   - **Bindings:** Extends `Bindings` to inject controllers, use cases, and repositories lazily via `Get.lazyPut(...)` and binds them to `AppPages` routes.
2. **Domain Layer:**
   - **Entities:** Pure Dart classes representing core business data structures. Free of JSON serialization dependencies.
   - **Repositories (Interfaces):** Abstract contracts defining data operations required by use cases.
   - **Use Cases:** Granular, single-responsibility business operations (e.g. `GetProcurementRequestsUseCase`).
3. **Data Layer:**
   - **Models:** Extends or maps to domain entities, containing `fromJson` and `toJson` serialization matching Laravel API resources.
   - **Data Sources:** Handle raw network calls (`ApiClient`), headers, and query parameters.
   - **Repositories (Implementations):** Implements domain repository interfaces, coordinating data sources and converting models to domain entities.

---

## 3. Engineering Rules & Invariants

- **State Management Ecosystem:** **GetX** is the exclusive ecosystem.
  - Bind UI with `Obx(() => ...)` or `GetBuilder<FeatureController>`.
  - **Prohibited:** Provider, Riverpod, Bloc, and custom state machines via `StatefulWidget`. `StatefulWidget` is permitted only for low-level animation/ticker controllers.
- **Infrastructure & Core Reuse (Mandatory):**
  - **Dialogs & Sheets:** Reuse `CustomDialog`, `CustomAnimationOverlay`, `DialogActionBar`, `CustomBottomSheet`, `InvoicePrintFormatSheet`.
  - **Inputs & Keyboards:** Reuse `CustomTextField`, `CustomPasswordField`, `CustomPhoneField`, `CustomPinField`, `CustomDropdown`, `CustomNumericKeyboard`.
  - **Buttons:** Reuse `CustomButton`, `CustomThemeToggleButton`.
  - **Feedback & Skeletons:** Reuse `CustomEmptyState`, `CustomErrorState`, `CustomLoadingState`, `CustomLoadingIndicatorBar`, `CustomNotFound`, `CustomOfflineBanner`, `CustomUnauthorized`, and skeleton shimmers (`CustomSkeletonCard`, `CustomSkeletonFeed`, `CustomSkeletonGrid`, `CustomSkeletonListTile`, `CustomSkeletonShimmer`, `CustomSkeletonText`).
  - **Toasts:** Reuse `CustomSnackbar`, `CustomToast`.
  - **Pickers:** Reuse `CustomDatePickerField`, `CustomDatePickerModal`, `CustomFilePicker`, `CustomImagePicker`, `CustomContactPickerIconButton`, `CustomColorPicker`, `CustomPosSelectorField`.
  - **Foundation, Nav & Media:** Reuse `CustomAppScaffold`, `CustomFloatingBottomNavBar`, `DoubleBackExitScope`, `MediaGalleryViewer`, `CustomCachedImage`, `CustomQrCode`.
  - **Dividers, Menus & Indicators:** Reuse `CustomLabeledDivider`, `CustomPopupMenu`, `CustomPageIndicator`, `CustomSegmentedBar`, `CustomInteractiveBounce`.
  - **Utils:** Reuse `DateFormatter`, `AmountFormatter`, `TextFormatter`, `FileSizeFormatter`, `FormValidators`, `IdempotencyHelper`, `SystemUiStyleResolver`.
  - **Core Services:** Leverage established services (`ApiService`, `WorkspaceService`, `SessionService`, `ThemeService`, `StorageService`, `CredentialService`, `AppCryptoService`, `AppCacheService`, `InternetConnectionService`, `PermissionService`, `DeviceService`, `PushNotificationService`, `FilePickerService`, `FileExportService`, `DatePickerService`, `ContactPickerService`, `QrCodeService`).
- **Design System Tokens:**
  - Colors: Reuse `AppColors.primary` (`0xFF0D7A5F`), `AppColors.secondary` (`0xFF1ABC9C`), `AppColors.tertiary` (`0xFF2ECC71`), and Material 3 surface tokens.
  - Typography: Reuse `AppTypography` styles (`badgeText`, `bodyLarge`, `titleMedium`, etc.).
  - Radii & Spacing: Reuse `AppRadius.full`, `AppRadius.chip`, `AppRadius.card`, `AppSpacing`.
  - Elevation & Motion: Reuse `AppElevation`, `AppGradients`, `AppMotion`, `AppThemes`.
  - Breakpoints: Strictly adapt using `AppBreakpoints` (`mobileSmallMax: 360`, `mobileMax: 600`, `tabletMax: 900`, `desktopMax: 1240`).
- **Permanent Premium UI/UX Standard (Mandatory):**
  - Follow the dedicated `flutter-premium-ui-ux` skill for information architecture, visual hierarchy, and distinct screen composition.
  - Strict prohibition against generic screen templates (e.g., repeating identical card stacks across different business features).
  - Explicit multi-form factor design for mobile (< 600dp) and tablet (≥ 600dp) using `AppBreakpoints`.
- **Zero Financial Calculations:**
  - Never compute invoice line sums, discount subtotals, or repayment allocations on the client.
  - Display financial amounts originating directly from authoritative Laravel API responses.
- **Required View State Coverage:**
  - Explicit Loading, Empty, Error (with retry), and Success states on all data screens.
