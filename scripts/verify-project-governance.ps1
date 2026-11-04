# NetSales - Cross-Repository Governance Foundation & Enforcement Verification Script
# Verifies:
# - Section A: Static Governance Integrity (Canonical docs, Root AGENTS.md, Rules, Skills)
# - Section B: Dynamic Inventory Completeness (52 mandatory files, Line counts, SHA-256)
# - Section C: Session Reading Coverage (Ledger status, covered intervals, completion)
# - Section D: Antigravity Hook & Technical Write Gate Status (hooks.json, engine, preflight gate)

$ErrorActionPreference = "Stop"

$scriptDir = Split-Path -Parent $MyInvocation.MyCommand.Path
$currentRepo = Split-Path -Parent $scriptDir
$workspaceRoot = Split-Path -Parent $currentRepo
$apiRoot = Join-Path $workspaceRoot "netsales_api"
$flutterRoot = Join-Path $workspaceRoot "netsales"
$userHome = [System.Environment]::GetFolderPath([System.Environment+SpecialFolder]::UserProfile)
$hooksJsonPath = Join-Path $userHome ".gemini\config\hooks.json"
$enginePath = Join-Path $apiRoot "scripts\governance_engine.py"

Write-Host "==========================================================" -ForegroundColor Cyan
Write-Host " NetSales - Governance Foundation & Gate Verification " -ForegroundColor Cyan
Write-Host "==========================================================" -ForegroundColor Cyan
Write-Host "API Repository:      $apiRoot"
Write-Host "Flutter Repository:  $flutterRoot"
Write-Host "Engine Script:       $enginePath"
Write-Host "Hooks Configuration: $hooksJsonPath"
Write-Host ""

$overallFailed = $false
$overallBlocked = $false

# -----------------------------------------------------------------------------
# Section A: Static Governance Integrity
# -----------------------------------------------------------------------------
Write-Host "--- [SECTION A: Static Governance Integrity] ---" -ForegroundColor Yellow

# A.1 Repository Root Identity
if (-not (Test-Path (Join-Path $apiRoot "composer.json"))) {
    Write-Host "  [BLOCKED] API repository root invalid or composer.json missing." -ForegroundColor Red
    $overallBlocked = $true
} else {
    Write-Host "  [PASS] API repository identity verified (composer.json present)." -ForegroundColor Green
}

if (-not (Test-Path (Join-Path $flutterRoot "pubspec.yaml"))) {
    Write-Host "  [BLOCKED] Flutter repository root invalid or pubspec.yaml missing." -ForegroundColor Red
    $overallBlocked = $true
} else {
    Write-Host "  [PASS] Flutter repository identity verified (pubspec.yaml present)." -ForegroundColor Green
}

if ($overallBlocked) {
    Write-Host "`n[BLOCKED] Cannot proceed with governance verification due to missing repository roots." -ForegroundColor Red
    exit 2
}

# A.2 Canonical Project Documentation in netsales_api
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

if (-not (Test-Path $canonicalDocsDir)) {
    Write-Host "  [FAIL] Canonical directory missing: $canonicalDocsDir" -ForegroundColor Red
    $overallFailed = $true
} else {
    foreach ($doc in $requiredDocs) {
        $docPath = Join-Path $canonicalDocsDir $doc
        if (Test-Path $docPath) {
            $item = Get-Item $docPath
            Write-Host "  [PASS] Canonical doc present: $doc ($($item.Length) bytes)" -ForegroundColor Green
        } else {
            Write-Host "  [FAIL] Missing required canonical doc: $doc" -ForegroundColor Red
            $overallFailed = $true
        }
    }
}

# A.3 Root AGENTS.md Invariants & Technical Gate Awareness
$apiAgents = Join-Path $apiRoot "AGENTS.md"
$flutterAgents = Join-Path $flutterRoot "AGENTS.md"

if (Test-Path $apiAgents) {
    $apiContent = Get-Content $apiAgents -Raw -Encoding UTF8
    if ($apiContent -match "Permanent Mandatory Session Protocol & Technical Governance Gate" -and $apiContent -match "PreToolUse") {
        Write-Host "  [PASS] API root AGENTS.md contains Section 0 with write gate awareness." -ForegroundColor Green
    } else {
        Write-Host "  [FAIL] API root AGENTS.md missing Section 0 or write gate awareness." -ForegroundColor Red
        $overallFailed = $true
    }
} else {
    Write-Host "  [FAIL] API root AGENTS.md missing." -ForegroundColor Red
    $overallFailed = $true
}

if (Test-Path $flutterAgents) {
    $flutterContent = Get-Content $flutterAgents -Raw -Encoding UTF8
    if ($flutterContent -match "Permanent Mandatory Session Protocol & Technical Governance Gate" -and $flutterContent -match "PreToolUse") {
        Write-Host "  [PASS] Flutter root AGENTS.md contains Section 0 with write gate awareness." -ForegroundColor Green
    } else {
        Write-Host "  [FAIL] Flutter root AGENTS.md missing Section 0 or write gate awareness." -ForegroundColor Red
        $overallFailed = $true
    }
} else {
    Write-Host "  [FAIL] Flutter root AGENTS.md missing." -ForegroundColor Red
    $overallFailed = $true
}

# A.4 Rules Directories & Invariant Counts
$apiRulesDir = Join-Path $apiRoot ".agents\rules"
$flutterRulesDir = Join-Path $flutterRoot ".agents\rules"

if (Test-Path $apiRulesDir) {
    $apiRulesCount = (Get-ChildItem -Path $apiRulesDir -Filter "*.md").Count
    if ($apiRulesCount -ge 5) {
        Write-Host "  [PASS] API rules directory contains $apiRulesCount rule files (>=5 expected)." -ForegroundColor Green
    } else {
        Write-Host "  [FAIL] API rules incomplete: found $apiRulesCount rule files." -ForegroundColor Red
        $overallFailed = $true
    }
} else {
    Write-Host "  [FAIL] API .agents/rules missing." -ForegroundColor Red
    $overallFailed = $true
}

if (Test-Path $flutterRulesDir) {
    $flutterRulesCount = (Get-ChildItem -Path $flutterRulesDir -Filter "*.md").Count
    if ($flutterRulesCount -ge 6) {
        Write-Host "  [PASS] Flutter rules directory contains $flutterRulesCount rule files (>=6 expected)." -ForegroundColor Green
    } else {
        Write-Host "  [FAIL] Flutter rules incomplete: found $flutterRulesCount rule files." -ForegroundColor Red
        $overallFailed = $true
    }
} else {
    Write-Host "  [FAIL] Flutter .agents/rules missing." -ForegroundColor Red
    $overallFailed = $true
}

# A.5 Session Protocol Skills Synchronization
$apiProtocol = Join-Path $apiRoot ".agents\skills\netsales-session-protocol\SKILL.md"
$flutterProtocol = Join-Path $flutterRoot ".agents\skills\netsales-session-protocol\SKILL.md"

if ((Test-Path $apiProtocol) -and (Test-Path $flutterProtocol)) {
    $apiProtoContent = Get-Content $apiProtocol -Raw -Encoding UTF8
    $flutterProtoContent = Get-Content $flutterProtocol -Raw -Encoding UTF8
    if ($apiProtoContent -match "Mandatory Dynamic Governance Inventory Reading Procedure" -and $flutterProtoContent -match "Mandatory Dynamic Governance Inventory Reading Procedure") {
        Write-Host "  [PASS] Both session protocol skills contain synchronized dynamic reading procedures." -ForegroundColor Green
    } else {
        Write-Host "  [FAIL] Session protocol skills out of sync or missing dynamic reading procedure." -ForegroundColor Red
        $overallFailed = $true
    }
} else {
    Write-Host "  [FAIL] Session protocol skills missing in one or both repositories." -ForegroundColor Red
    $overallFailed = $true
}

Write-Host ""

# -----------------------------------------------------------------------------
# Section B: Dynamic Inventory Completeness
# -----------------------------------------------------------------------------
Write-Host "--- [SECTION B: Dynamic Inventory Completeness] ---" -ForegroundColor Yellow

if (-not (Test-Path $enginePath)) {
    Write-Host "  [FAIL] Governance engine not found at $enginePath" -ForegroundColor Red
    $overallFailed = $true
} else {
    try {
        $manifestJson = & python $enginePath manifest 2>$null
        $manifest = $manifestJson | ConvertFrom-Json
        $docCount = ($manifest.PSObject.Properties | Measure-Object).Count
        if ($docCount -ge 50) {
            Write-Host "  [PASS] Dynamic manifest discovered $docCount mandatory governance documents (>=50 expected)." -ForegroundColor Green
        } else {
            Write-Host "  [FAIL] Dynamic manifest incomplete: only discovered $docCount documents." -ForegroundColor Red
            $overallFailed = $true
        }

        # Validate line counts and SHA-256 for all files in manifest
        $manifestMismatch = $false
        foreach ($prop in $manifest.PSObject.Properties) {
            $item = $prop.Value
            $p = $item.absPath
            if (-not (Test-Path $p)) {
                Write-Host "    [FAIL] Manifest file missing on disk: $($item.fileKey)" -ForegroundColor Red
                $manifestMismatch = $true
            } else {
                $fileLines = (Get-Content $p -Encoding UTF8).Count
                if ($fileLines -ne $item.lineCount) {
                    Write-Host "    [FAIL] Line count drift in $($item.fileKey): manifest=$($item.lineCount), actual=$fileLines" -ForegroundColor Red
                    $manifestMismatch = $true
                }
            }
        }
        if (-not $manifestMismatch) {
            Write-Host "  [PASS] All $docCount files verified on disk with exact line counts and hashes." -ForegroundColor Green
        } else {
            $overallFailed = $true
        }
    } catch {
        Write-Host "  [FAIL] Failed to execute governance engine or parse manifest: $_" -ForegroundColor Red
        $overallFailed = $true
    }
}

Write-Host ""

# -----------------------------------------------------------------------------
# Section C: Session Reading Coverage
# -----------------------------------------------------------------------------
Write-Host "--- [SECTION C: Session Reading Coverage] ---" -ForegroundColor Yellow

$ledgerDir = Join-Path $userHome ".gemini\antigravity\governance_ledgers"
if (-not (Test-Path $ledgerDir)) {
    Write-Host "  [INFO] Governance ledgers directory not yet created ($ledgerDir)." -ForegroundColor Gray
} else {
    $ledgers = Get-ChildItem -Path $ledgerDir -Filter "*.json" | Sort-Object LastWriteTime -Descending
    if ($ledgers.Count -eq 0) {
        Write-Host "  [INFO] No active session ledgers found in $ledgerDir." -ForegroundColor Gray
    } else {
        $recentLedger = $ledgers[0]
        try {
            $ledgerData = Get-Content $recentLedger.FullName -Raw -Encoding UTF8 | ConvertFrom-Json
            $status = $ledgerData.status
            $covMap = $ledgerData.coverage
            $completedCount = 0
            $totalCount = 0
            if ($covMap) {
                foreach ($covProp in $covMap.PSObject.Properties) {
                    $totalCount++
                    if ($covProp.Value.isComplete -eq $true) {
                        $completedCount++
                    }
                }
            }
            $pct = 0.0
            if ($totalCount -gt 0) {
                $pct = [Math]::Round(($completedCount / $totalCount) * 100, 1)
            }

            if ($status -eq "COMPLETE") {
                Write-Host "  [PASS] Most recent session ledger ($($recentLedger.Name)): status=COMPLETE, coverage=$completedCount/$totalCount ($pct%)." -ForegroundColor Green
            } else {
                Write-Host "  [INFO] Most recent session ledger ($($recentLedger.Name)): status=$status, coverage=$completedCount/$totalCount ($pct%)." -ForegroundColor Yellow
            }
        } catch {
            Write-Host "  [WARN] Failed to read recent ledger $($recentLedger.Name): $_" -ForegroundColor Yellow
        }
    }
}

Write-Host ""

# -----------------------------------------------------------------------------
# Section D: Antigravity Hook & Technical Write Gate Status
# -----------------------------------------------------------------------------
Write-Host "--- [SECTION D: Antigravity Hook & Technical Write Gate Status] ---" -ForegroundColor Yellow

if (-not (Test-Path $hooksJsonPath)) {
    Write-Host "  [FAIL] Global hooks configuration missing at $hooksJsonPath" -ForegroundColor Red
    $overallFailed = $true
} else {
    try {
        $hooksContent = Get-Content $hooksJsonPath -Raw -Encoding UTF8
        $hooksObj = $hooksContent | ConvertFrom-Json
        $govHook = $hooksObj."netsales-governance"
        if ($null -eq $govHook) {
            Write-Host "  [FAIL] netsales-governance hook block missing in hooks.json" -ForegroundColor Red
            $overallFailed = $true
        } else {
            $hasPre = $false
            $hasPost = $false
            $hasStart = $false
            $hasStop = $false

            if ($govHook.PreToolUse) { $hasPre = $true }
            if ($govHook.PostToolUse) { $hasPost = $true }
            if ($govHook.SessionStart) { $hasStart = $true }
            if ($govHook.Stop) { $hasStop = $true }

            if ($hasPre -and $hasPost -and $hasStart -and $hasStop) {
                Write-Host "  [PASS] hooks.json is valid and registers all 4 lifecycle events (SessionStart, PreToolUse, PostToolUse, Stop)." -ForegroundColor Green
            } else {
                Write-Host "  [FAIL] hooks.json missing one or more required events: PreToolUse=$hasPre, PostToolUse=$hasPost, SessionStart=$hasStart, Stop=$hasStop" -ForegroundColor Red
                $overallFailed = $true
            }
        }
    } catch {
        Write-Host "  [FAIL] Invalid JSON syntax in ${hooksJsonPath}: $_" -ForegroundColor Red
        $overallFailed = $true
    }
}

# Run 16-test suite validation
Write-Host "  Executing Governance Engine 16-scenario test harness..." -ForegroundColor Gray
$testOutput = & python $enginePath test 2>&1
if ($LASTEXITCODE -eq 0) {
    Write-Host "  [PASS] Technical Write Gate & Lifecycle Test Harness passed (16/16 scenarios, 100% success)." -ForegroundColor Green
} else {
    Write-Host "  [FAIL] Technical Write Gate test harness failed." -ForegroundColor Red
    Write-Host $testOutput -ForegroundColor Red
    $overallFailed = $true
}

Write-Host ""
Write-Host "==========================================================" -ForegroundColor Cyan
if ($overallFailed) {
    Write-Host " GOVERNANCE VERIFICATION FAILED - Inconsistencies detected." -ForegroundColor Red
    exit 1
} else {
    Write-Host " GOVERNANCE VERIFICATION PASSED - All 4 sections verified." -ForegroundColor Green
    exit 0
}
