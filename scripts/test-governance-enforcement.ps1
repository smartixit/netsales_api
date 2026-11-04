# NetSales - Governance Enforcement Test Runner
# Executes the 16 automated verification test scenarios for the Governance Enforcement Engine

$ErrorActionPreference = "Stop"

$scriptDir = Split-Path -Parent $MyInvocation.MyCommand.Path
$enginePath = Join-Path $scriptDir "governance_engine.py"

if (-not (Test-Path $enginePath)) {
    Write-Host "[FAIL] Governance engine script not found at $enginePath" -ForegroundColor Red
    exit 1
}

Write-Host "Running NetSales Governance Enforcement Engine automated test suite..." -ForegroundColor Cyan
& python $enginePath test

if ($LASTEXITCODE -eq 0) {
    Write-Host "`n[PASS] Governance enforcement tests completed successfully." -ForegroundColor Green
    exit 0
} else {
    Write-Host "`n[FAIL] Governance enforcement tests failed." -ForegroundColor Red
    exit 1
}

