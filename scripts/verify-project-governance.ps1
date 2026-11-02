# NetSales - Cross-Repository Governance Verification Script
# Verifies that canonical documentation in netsales_api exists and is discoverable from netsales

$ErrorActionPreference = "Stop"

$scriptDir = Split-Path -Parent $MyInvocation.MyCommand.Path
$apiRoot = Split-Path -Parent $scriptDir
$workspaceRoot = Split-Path -Parent $apiRoot
$flutterRoot = Join-Path $workspaceRoot "netsales"

Write-Host "==========================================================" -ForegroundColor Cyan
Write-Host " NetSales - Governance Foundation Verification " -ForegroundColor Cyan
Write-Host "==========================================================" -ForegroundColor Cyan
Write-Host "API Repository:      $apiRoot"
Write-Host "Flutter Repository:  $flutterRoot"
Write-Host ""

$canonicalDocsDir = Join-Path $apiRoot "docs\project"
$requiredDocs = @(
    "PROJECT_OVERVIEW.md",
    "PRODUCT_SCOPE.md",
    "DOMAIN_MAP.md",
    "ARCHITECTURE.md",
    "API_CONTRACTS.md",
    "RBAC_AND_TENANT_CONTEXT.md",
    "FINANCIAL_INVARIANTS.md",
    "PROJECT_STATE.md",
    "DECISION_LOG.md",
    "WORK_LOG.md",
    "GOVERNANCE.md"
)

$hasError = $false

Write-Host "1. Verifying Canonical Project Documentation in netsales_api..." -ForegroundColor Yellow
if (-not (Test-Path $canonicalDocsDir)) {
    Write-Host "  [FAIL] Canonical directory missing: $canonicalDocsDir" -ForegroundColor Red
    $hasError = $true
} else {
    foreach ($doc in $requiredDocs) {
        $docPath = Join-Path $canonicalDocsDir $doc
        if (Test-Path $docPath) {
            $item = Get-Item $docPath
            $size = $item.Length
            Write-Host "  [PASS] $doc ($size bytes)" -ForegroundColor Green
        } else {
            Write-Host "  [FAIL] Missing required canonical doc: $doc" -ForegroundColor Red
            $hasError = $true
        }
    }
}

Write-Host ""
Write-Host "2. Verifying Root AGENTS.md Invariants..." -ForegroundColor Yellow
$flutterAgents = Join-Path $flutterRoot "AGENTS.md"
$apiAgents = Join-Path $apiRoot "AGENTS.md"

if (Test-Path $flutterAgents) {
    Write-Host "  [PASS] Flutter root AGENTS.md exists." -ForegroundColor Green
} else {
    Write-Host "  [FAIL] Flutter root AGENTS.md missing." -ForegroundColor Red
    $hasError = $true
}

if (Test-Path $apiAgents) {
    Write-Host "  [PASS] API root AGENTS.md exists." -ForegroundColor Green
} else {
    Write-Host "  [FAIL] API root AGENTS.md missing." -ForegroundColor Red
    $hasError = $true
}

Write-Host ""
Write-Host "3. Verifying Antigravity Rules Directories..." -ForegroundColor Yellow
$flutterRules = Join-Path $flutterRoot ".agents\rules"
$apiRules = Join-Path $apiRoot ".agents\rules"

if (Test-Path $flutterRules) {
    $count = (Get-ChildItem -Path $flutterRules -Filter "*.md").Count
    Write-Host "  [PASS] Flutter rules present: $count rule files." -ForegroundColor Green
} else {
    Write-Host "  [FAIL] Flutter .agents/rules missing." -ForegroundColor Red
    $hasError = $true
}

if (Test-Path $apiRules) {
    $count = (Get-ChildItem -Path $apiRules -Filter "*.md").Count
    Write-Host "  [PASS] API rules present: $count rule files." -ForegroundColor Green
} else {
    Write-Host "  [FAIL] API .agents/rules missing." -ForegroundColor Red
    $hasError = $true
}

Write-Host ""
Write-Host "==========================================================" -ForegroundColor Cyan
if ($hasError) {
    Write-Host " GOVERNANCE VERIFICATION FAILED - Inconsistencies detected." -ForegroundColor Red
    exit 1
} else {
    Write-Host " GOVERNANCE VERIFICATION PASSED - All contracts verified." -ForegroundColor Green
    exit 0
}
