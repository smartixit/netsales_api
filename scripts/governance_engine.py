#!/usr/bin/env python3
"""
NetSales Unified Governance Enforcement Engine
Handles:
- Dynamic discovery of governance inventory across netsales and netsales_api
- Line-by-line reading coverage tracking with interval merging
- Hash validation and tampering invalidation
- Antigravity lifecycle hooks: SessionStart, PreToolUse, PostToolUse, Stop
- Technical preflight write gate
"""

import sys
import os
import json
import glob
import hashlib
import time
from typing import Dict, List, Tuple, Optional, Any

# Canonical workspace identities
CANONICAL_API_NAME = "smartixit/netsales_api"
CANONICAL_FLUTTER_NAME = "netsales"

# Ledger storage directory (outside tracked repository code)
USER_HOME = os.path.expanduser("~")
LEDGER_DIR = os.path.join(USER_HOME, ".gemini", "antigravity", "governance_ledgers")


def get_canonical_path(p: str) -> str:
    """Normalize path for cross-platform and case-insensitive Windows comparison."""
    if not p:
        return ""
    norm = os.path.abspath(os.path.expanduser(p))
    return norm.lower() if sys.platform.startswith("win") else norm


def find_repo_roots(hint_paths: Optional[List[str]] = None) -> Tuple[Optional[str], Optional[str], Optional[str]]:
    """
    Dynamically locate and verify both netsales and netsales_api repositories.
    Returns (api_root, flutter_root, error_message).
    """
    candidates = []
    if hint_paths:
        for hp in hint_paths:
            candidates.append(hp)
            candidates.append(os.path.dirname(hp))
            candidates.append(os.path.join(hp, ".."))

    # Add standard default locations
    default_base = r"d:\Smartix\NetSales"
    candidates.extend([
        default_base,
        os.path.join(default_base, "netsales_api"),
        os.path.join(default_base, "netsales"),
        os.getcwd(),
        os.path.dirname(os.getcwd())
    ])

    resolved_api = None
    resolved_flutter = None

    for cand in candidates:
        cand = os.path.abspath(cand)
        if not os.path.isdir(cand):
            continue

        # Check if cand itself is netsales_api
        if not resolved_api and is_valid_api_repo(cand):
            resolved_api = cand
        # Check if cand has a child netsales_api
        child_api = os.path.join(cand, "netsales_api")
        if not resolved_api and os.path.isdir(child_api) and is_valid_api_repo(child_api):
            resolved_api = child_api

        # Check if cand itself is netsales
        if not resolved_flutter and is_valid_flutter_repo(cand):
            resolved_flutter = cand
        # Check if cand has a child netsales
        child_flutter = os.path.join(cand, "netsales")
        if not resolved_flutter and os.path.isdir(child_flutter) and is_valid_flutter_repo(child_flutter):
            resolved_flutter = child_flutter

    if not resolved_api and not resolved_flutter:
        return None, None, "Neither netsales_api nor netsales repository was located."
    if not resolved_api:
        return None, resolved_flutter, "netsales_api repository could not be located or verified."
    if not resolved_flutter:
        return resolved_api, None, "netsales repository could not be located or verified."

    return resolved_api, resolved_flutter, None


def is_valid_api_repo(path: str) -> bool:
    """Validate Laravel backend repo identity by artisan and composer.json."""
    composer_path = os.path.join(path, "composer.json")
    artisan_path = os.path.join(path, "artisan")
    if os.path.isfile(composer_path) and os.path.isfile(artisan_path):
        try:
            with open(composer_path, "r", encoding="utf-8", errors="ignore") as f:
                content = f.read()
                return "smartixit/netsales_api" in content or "laravel/framework" in content
        except Exception:
            return False
    return False


def is_valid_flutter_repo(path: str) -> bool:
    """Validate Flutter client repo identity by pubspec.yaml."""
    pubspec_path = os.path.join(path, "pubspec.yaml")
    if os.path.isfile(pubspec_path):
        try:
            with open(pubspec_path, "r", encoding="utf-8", errors="ignore") as f:
                content = f.read()
                return "name: netsales" in content
        except Exception:
            return False
    return False


def compute_sha256(path: str) -> str:
    """Compute file SHA-256 hash."""
    h = hashlib.sha256()
    with open(path, "rb") as f:
        for chunk in iter(lambda: f.read(65536), b""):
            h.update(chunk)
    return h.hexdigest()


def count_lines(path: str) -> int:
    """Count lines in file."""
    with open(path, "r", encoding="utf-8", errors="ignore") as f:
        return len(f.readlines())


def scan_dynamic_inventory(api_root: str, flutter_root: str) -> Dict[str, Dict[str, Any]]:
    """
    Dynamically scan the complete governance inventory for both repositories.
    Returns a manifest dictionary mapping file key to metadata.
    """
    manifest = {}

    def scan_repo(root: str, repo_id: str):
        # A. Agent instructions
        for name in ["AGENTS.md", "GEMINI.md"]:
            p = os.path.join(root, name)
            if os.path.isfile(p):
                add_file(p, repo_id, "Agent Instructions", root)

        # B. Rules under .agents/rules/
        rules_dir = os.path.join(root, ".agents", "rules")
        if os.path.isdir(rules_dir):
            for p in sorted(glob.glob(os.path.join(rules_dir, "*.md"))):
                add_file(p, repo_id, "Rules", root)

        # C. Skills under .agents/skills/
        skills_dir = os.path.join(root, ".agents", "skills")
        if os.path.isdir(skills_dir):
            for p in sorted(glob.glob(os.path.join(skills_dir, "**", "SKILL.md"), recursive=True)):
                add_file(p, repo_id, "Skills", root)

        # D. Project Documentation under docs/project/
        docs_dir = os.path.join(root, "docs", "project")
        if os.path.isdir(docs_dir):
            for p in sorted(glob.glob(os.path.join(docs_dir, "**", "*.md"), recursive=True)):
                add_file(p, repo_id, "Project Docs", root)

    def add_file(abs_p: str, repo_id: str, category: str, root_dir: str):
        rel_p = os.path.relpath(abs_p, root_dir).replace("\\", "/")
        file_key = f"{repo_id}/{rel_p}"
        lines = count_lines(abs_p)
        file_hash = compute_sha256(abs_p)
        manifest[file_key] = {
            "fileKey": file_key,
            "repoId": repo_id,
            "category": category,
            "relPath": rel_p,
            "absPath": abs_p,
            "canonicalPath": get_canonical_path(abs_p),
            "lineCount": lines,
            "sha256": file_hash,
        }

    scan_repo(api_root, "netsales_api")
    scan_repo(flutter_root, "netsales")
    return manifest


def merge_intervals(intervals: List[List[int]], total_lines: int) -> Tuple[List[List[int]], bool]:
    """
    Merge overlapping and adjacent 1-based line intervals.
    Returns (merged_intervals, is_complete).
    """
    if not intervals:
        return [], False

    # Normalize: bounds within [1, total_lines]
    cleaned = []
    for s, e in intervals:
        start = max(1, min(s, total_lines))
        end = max(1, min(e, total_lines))
        if start <= end:
            cleaned.append([start, end])

    if not cleaned:
        return [], False

    cleaned.sort(key=lambda x: x[0])
    merged = [cleaned[0]]

    for current in cleaned[1:]:
        prev = merged[-1]
        # Overlapping or adjacent: prev[1] + 1 >= current[0]
        if prev[1] + 1 >= current[0]:
            prev[1] = max(prev[1], current[1])
        else:
            merged.append(current)

    is_complete = (len(merged) == 1 and merged[0][0] <= 1 and merged[0][1] >= total_lines)
    return merged, is_complete


class GovernanceLedger:
    def __init__(self, conversation_id: str, workspace_paths: Optional[List[str]] = None):
        self.conversation_id = conversation_id or "default_session"
        self.workspace_paths = workspace_paths or []
        os.makedirs(LEDGER_DIR, exist_ok=True)
        self.ledger_path = os.path.join(LEDGER_DIR, f"{self.conversation_id}.json")
        self.data = self._load_or_initialize()

    def _load_or_initialize(self) -> Dict[str, Any]:
        if os.path.isfile(self.ledger_path):
            try:
                with open(self.ledger_path, "r", encoding="utf-8") as f:
                    data = json.load(f)
                    # Verify repo roots still exist
                    api_r = data.get("repoRoots", {}).get("netsales_api")
                    flutter_r = data.get("repoRoots", {}).get("netsales")
                    if api_r and os.path.isdir(api_r) and flutter_r and os.path.isdir(flutter_r):
                        return data
            except Exception:
                pass

        # Discover afresh
        api_root, flutter_root, err = find_repo_roots(self.workspace_paths)
        if err or not api_root or not flutter_root:
            return {
                "conversationId": self.conversation_id,
                "status": "BLOCKED",
                "blockedReason": err or "Failed to resolve repository roots.",
                "repoRoots": {"netsales_api": api_root, "netsales": flutter_root},
                "manifest": {},
                "coverage": {},
                "createdAt": time.time(),
                "updatedAt": time.time(),
            }

        manifest = scan_dynamic_inventory(api_root, flutter_root)
        manifest_str = json.dumps(
            {k: manifest[k]["sha256"] for k in sorted(manifest.keys())},
            sort_keys=True
        )
        manifest_hash = hashlib.sha256(manifest_str.encode("utf-8")).hexdigest()

        coverage = {}
        for k, item in manifest.items():
            coverage[k] = {
                "intervals": [],
                "isComplete": False,
                "lastReadHash": None
            }

        data = {
            "conversationId": self.conversation_id,
            "status": "INCOMPLETE",
            "blockedReason": None,
            "manifestHash": manifest_hash,
            "repoRoots": {"netsales_api": api_root, "netsales": flutter_root},
            "manifest": manifest,
            "coverage": coverage,
            "createdAt": time.time(),
            "updatedAt": time.time(),
        }
        self._save(data)
        return data

    def _save(self, data: Optional[Dict[str, Any]] = None):
        if data is not None:
            self.data = data
        self.data["updatedAt"] = time.time()
        with open(self.ledger_path, "w", encoding="utf-8") as f:
            json.dump(self.data, f, indent=2)

    def record_file_read(self, file_path: str, start_line: Optional[int] = None, end_line: Optional[int] = None) -> bool:
        """
        Record a read range for a file. Returns True if recorded, False if file not in manifest.
        """
        if self.data.get("status") == "BLOCKED":
            return False

        canon_path = get_canonical_path(file_path)
        matched_key = None
        manifest_item = None

        for k, item in self.data.get("manifest", {}).items():
            if item.get("canonicalPath") == canon_path or get_canonical_path(item.get("absPath")) == canon_path:
                matched_key = k
                manifest_item = item
                break

        if not matched_key or not manifest_item:
            return False

        abs_p = manifest_item["absPath"]
        if not os.path.isfile(abs_p):
            return False

        # Current hash check
        current_hash = compute_sha256(abs_p)
        total_lines = manifest_item["lineCount"]

        file_cov = self.data["coverage"].setdefault(matched_key, {"intervals": [], "isComplete": False, "lastReadHash": None})

        # If file changed on disk, invalidate old intervals and update manifest!
        if manifest_item.get("sha256") != current_hash or (file_cov["lastReadHash"] and file_cov["lastReadHash"] != current_hash):
            file_cov["intervals"] = []
            file_cov["isComplete"] = False
            manifest_item["sha256"] = current_hash
            manifest_item["lineCount"] = count_lines(abs_p)
            total_lines = manifest_item["lineCount"]

        file_cov["lastReadHash"] = current_hash

        # Determine interval: full read if start_line/end_line not provided or full range
        s = 1 if start_line is None or start_line <= 0 else start_line
        e = total_lines if end_line is None or end_line >= total_lines else end_line

        file_cov["intervals"].append([s, e])
        merged, is_comp = merge_intervals(file_cov["intervals"], total_lines)
        file_cov["intervals"] = merged
        file_cov["isComplete"] = is_comp

        self._recompute_status()
        self._save()
        return True

    def _recompute_status(self):
        if self.data.get("status") == "BLOCKED":
            return

        manifest = self.data.get("manifest", {})
        coverage = self.data.get("coverage", {})

        if not manifest:
            self.data["status"] = "BLOCKED"
            self.data["blockedReason"] = "Manifest is empty."
            return

        all_complete = True
        for k, item in manifest.items():
            cov = coverage.get(k, {})
            # Also verify file hash on disk has not mutated!
            abs_p = item["absPath"]
            if not cov.get("isComplete", False):
                all_complete = False
                break
            if os.path.isfile(abs_p):
                curr_h = compute_sha256(abs_p)
                if curr_h != cov.get("lastReadHash") or curr_h != item.get("sha256"):
                    cov["isComplete"] = False
                    cov["intervals"] = []
                    all_complete = False
                    break

        if all_complete:
            self.data["status"] = "COMPLETE"
            self.data["blockedReason"] = None
        else:
            self.data["status"] = "INCOMPLETE"

    def get_unread_summary(self) -> List[str]:
        unread = []
        manifest = self.data.get("manifest", {})
        coverage = self.data.get("coverage", {})
        for k, item in manifest.items():
            cov = coverage.get(k, {})
            if not cov.get("isComplete", False):
                intervals = cov.get("intervals", [])
                tot = item["lineCount"]
                unread.append(f"{k} (lines: {tot}, covered intervals: {intervals})")
        return unread

    def is_modification_allowed(self, tool_name: str) -> Tuple[bool, str]:
        """Check if mutation is allowed by technical write gate."""
        self._recompute_status()
        status = self.data.get("status")

        if status == "COMPLETE":
            return True, "Governance preflight reading complete. Technical write gate open."
        elif status == "BLOCKED":
            return False, f"NetSales Governance Technical Gate BLOCKED: {self.data.get('blockedReason')}"
        else:
            unread = self.get_unread_summary()
            count = len(unread)
            sample = "\n  - ".join(unread[:5])
            if count > 5:
                sample += f"\n  ... and {count - 5} more files."
            return False, (
                f"NetSales Technical Write Gate: Tool execution '{tool_name}' DENIED.\n"
                f"Governance reading preflight is INCOMPLETE ({count} mandatory files remain unread).\n"
                f"You must completely read all mandatory governance documents before running mutating commands or modifying code.\n"
                f"Remaining unread files:\n  - {sample}"
            )


# -----------------------------------------------------------------------------
# Hook Entry Points
# -----------------------------------------------------------------------------

def is_netsales_context(payload: Dict[str, Any]) -> bool:
    """Determine if execution context pertains to NetSales."""
    ws_paths = payload.get("workspacePaths", [])
    for p in ws_paths:
        p_canon = get_canonical_path(p)
        if "netsales" in p_canon:
            return True
    # Check cwd
    cwd_canon = get_canonical_path(os.getcwd())
    if "netsales" in cwd_canon:
        return True
    return False


def hook_session_start(payload: Dict[str, Any]) -> Dict[str, Any]:
    if not is_netsales_context(payload):
        return {}

    conv_id = payload.get("conversationId", "default_session")
    ws_paths = payload.get("workspacePaths", [])
    ledger = GovernanceLedger(conv_id, ws_paths)

    total_files = len(ledger.data.get("manifest", {}))
    api_root = ledger.data.get("repoRoots", {}).get("netsales_api", "Not found")
    flutter_root = ledger.data.get("repoRoots", {}).get("netsales", "Not found")

    msg = (
        f"===========================================================\n"
        f" NetSales Mandatory Governance Preflight Active\n"
        f"===========================================================\n"
        f"API Repository:     {api_root}\n"
        f"Flutter Repository: {flutter_root}\n"
        f"Total Mandatory Governance Documents: {total_files}\n"
        f"Technical Write Gate Status: BLOCKED until 100% reading coverage.\n\n"
        f"PROCEDURE: You must execute 'netsales-session-protocol' and read all\n"
        f"{total_files} mandatory files line-by-line (AGENTS.md, .agents/rules/,\n"
        f".agents/skills/, docs/project/) before calling any code modification\n"
        f"tools or commands.\n"
        f"==========================================================="
    )

    return {
        "injectSteps": [
            {
                "systemMessage": {
                    "systemMessage": msg
                }
            }
        ]
    }


MUTATING_TOOL_NAMES = {
    "write_file", "write_to_file",
    "edit_file", "replace_file_content", "multi_replace_file_content",
    "run_command"
}

READ_TOOL_NAMES = {
    "read_file", "view_file", "list_dir", "find_by_name", "grep_search"
}


def hook_pre_tool_use(payload: Dict[str, Any]) -> Dict[str, Any]:
    if not is_netsales_context(payload):
        return {"decision": "allow"}

    tool_call = payload.get("toolCall", {})
    tool_name = tool_call.get("name", "")

    # Always allow non-mutating inspection tools
    if tool_name in READ_TOOL_NAMES or "search" in tool_name or "list" in tool_name:
        return {"decision": "allow"}

    conv_id = payload.get("conversationId", "default_session")
    ws_paths = payload.get("workspacePaths", [])
    ledger = GovernanceLedger(conv_id, ws_paths)

    allowed, reason = ledger.is_modification_allowed(tool_name)
    if allowed:
        return {"decision": "allow"}
    else:
        return {
            "decision": "deny",
            "reason": reason
        }


def hook_post_tool_use(payload: Dict[str, Any]) -> Dict[str, Any]:
    if not is_netsales_context(payload):
        return {}

    tool_call = payload.get("toolCall", {})
    tool_name = tool_call.get("name", "")
    error = payload.get("error")

    # If tool failed with error, do not record reading
    if error:
        return {}

    if tool_name in ("read_file", "view_file"):
        args = tool_call.get("args", {})
        file_path = args.get("Path") or args.get("path") or args.get("file_path") or args.get("FilePath")
        start_line = args.get("StartLine") or args.get("start_line") or args.get("startLine")
        end_line = args.get("EndLine") or args.get("end_line") or args.get("endLine")

        if file_path:
            conv_id = payload.get("conversationId", "default_session")
            ws_paths = payload.get("workspacePaths", [])
            ledger = GovernanceLedger(conv_id, ws_paths)
            ledger.record_file_read(file_path, start_line, end_line)

    return {}


def hook_stop(payload: Dict[str, Any]) -> Dict[str, Any]:
    if not is_netsales_context(payload):
        return {}

    conv_id = payload.get("conversationId", "default_session")
    ws_paths = payload.get("workspacePaths", [])
    ledger = GovernanceLedger(conv_id, ws_paths)

    ledger._recompute_status()
    if ledger.data.get("status") == "INCOMPLETE":
        # Check if any mandatory files are missing from disk
        manifest = ledger.data.get("manifest", {})
        missing_files = []
        for k, item in manifest.items():
            if not os.path.isfile(item.get("absPath", "")):
                missing_files.append(k)

        if missing_files:
            ledger.data["status"] = "BLOCKED"
            ledger.data["blockedReason"] = f"Missing mandatory files: {', '.join(missing_files)}"
            ledger._save()
            return {
                "decision": "allow",
                "reason": f"NetSales Governance Gate: BLOCKED due to missing files: {missing_files}"
            }

        cov = ledger.data.get("coverage", {})
        completed_count = sum(1 for c in cov.values() if c.get("isComplete", False))
        prev_completed = ledger.data.get("lastStopCompletedCount", -1)
        stop_attempts = ledger.data.get("stopAttempts", 0)

        unread = ledger.get_unread_summary()
        count = len(unread)

        # Progress tracking: if reading progress occurred since last stop attempt
        if completed_count > prev_completed:
            ledger.data["lastStopCompletedCount"] = completed_count
            ledger.data["stopAttempts"] = 1
            ledger._save()
            return {
                "decision": "continue",
                "reason": (
                    f"NetSales Governance Gate: Reading preflight is INCOMPLETE ({count} mandatory files remain unread).\n"
                    f"Progress detected ({completed_count}/{len(manifest)} files complete). Please continue reading."
                )
            }
        else:
            stop_attempts += 1
            ledger.data["stopAttempts"] = stop_attempts
            ledger._save()
            if stop_attempts >= 2:
                # Bounded recovery: allow termination with BLOCKED notification, but DO NOT mark ledger complete!
                return {
                    "decision": "allow",
                    "reason": (
                        f"NetSales Governance Gate: Reading preflight remains INCOMPLETE ({count} files unread) without progress.\n"
                        f"Termination permitted with outcome BLOCKED to prevent infinite loop. Technical write gate remains closed."
                    )
                }
            return {
                "decision": "continue",
                "reason": (
                    f"NetSales Governance Gate: Reading preflight is INCOMPLETE ({count} mandatory files remain unread).\n"
                    f"Please read the remaining mandatory governance files to satisfy the session initialization protocol."
                )
            }

    return {}


def run_tests() -> bool:
    """
    Execute 16 comprehensive automated verification tests for the NetSales Governance Enforcement Engine.
    Uses an isolated test ledger directory and sandbox files.
    """
    import tempfile
    import shutil

    print("===========================================================")
    print(" NetSales Governance Enforcement Engine — Test Suite (16 Scenarios)")
    print("===========================================================")

    api_root, flutter_root, err = find_repo_roots()
    if err:
        print(f"[FATAL] Repository roots not found: {err}")
        return False

    all_passed = True
    temp_dir = tempfile.mkdtemp(prefix="netsales_gov_test_")
    orig_ledger_dir = globals()["LEDGER_DIR"]
    globals()["LEDGER_DIR"] = os.path.join(temp_dir, "ledgers")
    os.makedirs(globals()["LEDGER_DIR"], exist_ok=True)

    try:
        # Scenario 1: Fresh session initialization
        print("Scenario 1: Fresh session initialization...")
        l1 = GovernanceLedger("test_session_1", [api_root, flutter_root])
        s1_status = l1.data.get("status")
        s1_count = len(l1.data.get("manifest", {}))
        if s1_status == "INCOMPLETE" and s1_count >= 50:
            print(f"  [PASS] Status INCOMPLETE, manifest discovered {s1_count} items.")
        else:
            print(f"  [FAIL] Expected INCOMPLETE with >=50 items, got status={s1_status}, count={s1_count}")
            all_passed = False

        # Scenario 2: Single missing repository blocks preflight
        print("Scenario 2: Single missing repository blocks preflight...")
        orig_find = globals()["find_repo_roots"]
        try:
            globals()["find_repo_roots"] = lambda paths=None: (None, flutter_root, "netsales_api repository could not be located or verified.")
            l2 = GovernanceLedger("test_session_2")
            s2_status = l2.data.get("status")
            if s2_status == "BLOCKED" and "netsales_api" in str(l2.data.get("blockedReason", "")):
                print(f"  [PASS] Status BLOCKED as expected: {l2.data.get('blockedReason')}")
            else:
                print(f"  [FAIL] Expected BLOCKED, got {s2_status}")
                all_passed = False
        finally:
            globals()["find_repo_roots"] = orig_find

        # Scenario 3: Unread file denies modification
        print("Scenario 3: Unread file denies modification...")
        l3 = GovernanceLedger("test_session_3", [api_root, flutter_root])
        allowed3, reason3 = l3.is_modification_allowed("write_file")
        if not allowed3 and "DENIED" in reason3:
            print("  [PASS] write_file modification denied on unread inventory.")
        else:
            print(f"  [FAIL] Expected denial, got allowed={allowed3}")
            all_passed = False

        # Scenario 4: Partial read denies modification
        print("Scenario 4: Partial read denies modification...")
        l4 = GovernanceLedger("test_session_4", [api_root, flutter_root])
        first_key = sorted(l4.data["manifest"].keys())[0]
        first_file = l4.data["manifest"][first_key]["absPath"]
        tot_lines = l4.data["manifest"][first_key]["lineCount"]
        half = max(1, tot_lines // 2)
        l4.record_file_read(first_file, 1, half)
        cov4 = l4.data["coverage"][first_key]
        if not cov4["isComplete"] and not l4.is_modification_allowed("edit_file")[0]:
            print(f"  [PASS] Partial read [1, {half}] of {tot_lines} lines is marked incomplete, modification denied.")
        else:
            print("  [FAIL] Partial read was marked complete or allowed modification.")
            all_passed = False

        # Scenario 5: Middle omitted range denies modification
        print("Scenario 5: Middle omitted range denies modification...")
        l5 = GovernanceLedger("test_session_5", [api_root, flutter_root])
        if tot_lines >= 10:
            l5.record_file_read(first_file, 1, 3)
            l5.record_file_read(first_file, 7, tot_lines)
            cov5 = l5.data["coverage"][first_key]
            if not cov5["isComplete"] and len(cov5["intervals"]) == 2:
                print(f"  [PASS] Gap between ranges preserved, intervals: {cov5['intervals']}, incomplete.")
            else:
                print(f"  [FAIL] Gap was incorrectly merged: {cov5}")
                all_passed = False
        else:
            print("  [SKIP] File has fewer than 10 lines, testing interval merge logic directly.")
            merged, comp = merge_intervals([[1, 3], [7, 10]], 10)
            if not comp and len(merged) == 2:
                print("  [PASS] merge_intervals correctly handles gap.")
            else:
                all_passed = False

        # Scenario 6: Overlapping / contiguous reads mark complete
        print("Scenario 6: Overlapping / contiguous reads mark complete...")
        merged6, comp6 = merge_intervals([[1, 60], [50, 100]], 100)
        if comp6 and merged6 == [[1, 100]]:
            print("  [PASS] Intervals [1, 60] and [50, 100] merged to [1, 100], complete=True.")
        else:
            print(f"  [FAIL] Interval merge failed: {merged6}, complete={comp6}")
            all_passed = False

        # Scenario 7: Full repository read marks complete
        print("Scenario 7: Full repository read marks complete...")
        l7 = GovernanceLedger("test_session_7", [api_root, flutter_root])
        for k, item in l7.data["manifest"].items():
            l7.record_file_read(item["absPath"], 1, item["lineCount"])
        l7._recompute_status()
        if l7.data["status"] == "COMPLETE" and l7.is_modification_allowed("run_command")[0]:
            print("  [PASS] 100% read coverage achieved, status COMPLETE, run_command allowed.")
        else:
            print(f"  [FAIL] Full read failed to mark complete. Status={l7.data['status']}")
            all_passed = False

        # Scenario 8: File change on disk invalidates reading
        print("Scenario 8: File change on disk invalidates reading...")
        first_item = l7.data["manifest"][first_key]
        first_item["sha256"] = "0000000000000000000000000000000000000000000000000000000000000000"
        l7._recompute_status()
        if l7.data["status"] == "INCOMPLETE":
            print("  [PASS] Hash change on disk invalidated file coverage and reverted status to INCOMPLETE.")
        else:
            print(f"  [FAIL] Hash invalidation failed. Status={l7.data['status']}")
            all_passed = False

        # Scenario 9: Direct file modification tool blocked before preflight complete
        print("Scenario 9: Direct file modification tool blocked before preflight complete...")
        hook_payload_write = {
            "conversationId": "test_session_1",
            "workspacePaths": [api_root, flutter_root],
            "toolCall": {"name": "write_file", "args": {"Path": "test.dart"}}
        }
        res_write = hook_pre_tool_use(hook_payload_write)
        if res_write.get("decision") == "deny":
            print("  [PASS] hook_pre_tool_use denied 'write_file' on incomplete ledger.")
        else:
            print(f"  [FAIL] write_file was allowed: {res_write}")
            all_passed = False

        # Scenario 10: Shell modification command blocked before preflight complete
        print("Scenario 10: Shell modification command blocked before preflight complete...")
        hook_payload_cmd = {
            "conversationId": "test_session_1",
            "workspacePaths": [api_root, flutter_root],
            "toolCall": {"name": "run_command", "args": {"CommandLine": "rm -rf foo"}}
        }
        res_cmd = hook_pre_tool_use(hook_payload_cmd)
        if res_cmd.get("decision") == "deny":
            print("  [PASS] hook_pre_tool_use denied 'run_command' on incomplete ledger.")
        else:
            print(f"  [FAIL] run_command was allowed: {res_cmd}")
            all_passed = False

        # Scenario 11: Non-modifying tool allowed at all times
        print("Scenario 11: Non-modifying tool allowed at all times...")
        hook_payload_read = {
            "conversationId": "test_session_1",
            "workspacePaths": [api_root, flutter_root],
            "toolCall": {"name": "read_file", "args": {"Path": "test.md"}}
        }
        res_read = hook_pre_tool_use(hook_payload_read)
        if res_read.get("decision") == "allow":
            print("  [PASS] hook_pre_tool_use allowed 'read_file' on incomplete ledger.")
        else:
            print(f"  [FAIL] read_file was denied: {res_read}")
            all_passed = False

        # Scenario 12: Independent session ledgers
        print("Scenario 12: Independent session ledgers...")
        l_sessA = GovernanceLedger("session_alpha", [api_root, flutter_root])
        l_sessB = GovernanceLedger("session_beta", [api_root, flutter_root])
        l_sessA.record_file_read(first_file, 1, tot_lines)
        covA = l_sessA.data["coverage"][first_key]["isComplete"]
        covB = l_sessB.data["coverage"][first_key]["isComplete"]
        if covA is True and covB is False:
            print("  [PASS] Session Alpha and Session Beta maintain independent coverage states.")
        else:
            print(f"  [FAIL] Cross-session pollution detected: covA={covA}, covB={covB}")
            all_passed = False

        # Scenario 13: Stale ledger rejection
        print("Scenario 13: Stale ledger rejection...")
        stale_file = os.path.join(globals()["LEDGER_DIR"], "stale_session.json")
        with open(stale_file, "w", encoding="utf-8") as sf:
            json.dump({
                "conversationId": "stale_session",
                "repoRoots": {"netsales_api": r"C:\DeletedDir\Api", "netsales": r"C:\DeletedDir\Flutter"}
            }, sf)
        l_stale = GovernanceLedger("stale_session", [api_root, flutter_root])
        if l_stale.data["repoRoots"]["netsales_api"] == api_root:
            print("  [PASS] Stale paths in ledger rejected and re-discovered afresh from disk.")
        else:
            print(f"  [FAIL] Stale paths retained.")
            all_passed = False

        # Scenario 14: Dynamic addition of new rule/doc included in inventory
        print("Scenario 14: Dynamic addition of new rule/doc included in inventory...")
        dummy_rule_path = os.path.join(api_root, ".agents", "rules", "test-temp-governance-rule.md")
        try:
            with open(dummy_rule_path, "w", encoding="utf-8") as drf:
                drf.write("# Temp Rule\nTest content line 2\n")
            m_updated = scan_dynamic_inventory(api_root, flutter_root)
            found_temp = any("test-temp-governance-rule.md" in k for k in m_updated.keys())
            if found_temp and len(m_updated) == s1_count + 1:
                print(f"  [PASS] Dynamically discovered new rule file. Count grew from {s1_count} to {len(m_updated)}.")
            else:
                print(f"  [FAIL] Failed to dynamically pick up new rule: found={found_temp}")
                all_passed = False
        finally:
            if os.path.isfile(dummy_rule_path):
                os.remove(dummy_rule_path)

        # Scenario 15: PostToolUse accurately records read intervals
        print("Scenario 15: PostToolUse accurately records read intervals...")
        post_payload = {
            "conversationId": "test_session_15",
            "workspacePaths": [api_root, flutter_root],
            "toolCall": {
                "name": "read_file",
                "args": {
                    "Path": first_file,
                    "StartLine": 1,
                    "EndLine": 10
                }
            }
        }
        hook_post_tool_use(post_payload)
        l15 = GovernanceLedger("test_session_15", [api_root, flutter_root])
        cov15 = l15.data["coverage"].get(first_key, {})
        if cov15.get("intervals") == [[1, min(10, tot_lines)]]:
            print(f"  [PASS] PostToolUse accurately recorded interval {cov15.get('intervals')}.")
        else:
            print(f"  [FAIL] PostToolUse interval mismatch: {cov15}")
            all_passed = False

        # Scenario 16: Stop hook warns/continues if incomplete and bounds infinite loops
        print("Scenario 16: Stop hook warns/continues if incomplete and bounds loops...")
        stop_payload = {
            "conversationId": "test_session_1",
            "workspacePaths": [api_root, flutter_root]
        }
        res_stop1 = hook_stop(stop_payload)
        res_stop2 = hook_stop(stop_payload)
        if res_stop1.get("decision") == "continue" and res_stop2.get("decision") == "allow" and "BLOCKED" in res_stop2.get("reason", ""):
            print("  [PASS] Stop hook returned decision: continue on first attempt, and allow (BLOCKED) on repeated attempts without progress.")
        else:
            print(f"  [FAIL] Stop hook unexpected response: stop1={res_stop1}, stop2={res_stop2}")
            all_passed = False

    finally:
        globals()["LEDGER_DIR"] = orig_ledger_dir
        shutil.rmtree(temp_dir, ignore_errors=True)

    print("===========================================================")
    if all_passed:
        print(" ALL 16 GOVERNANCE ENGINE TESTS PASSED (100% SUCCESS)")
    else:
        print(" SOME GOVERNANCE ENGINE TESTS FAILED")
    print("===========================================================")
    return all_passed


def main():
    if len(sys.argv) < 2:
        print("Usage: governance_engine.py <verify|manifest|status|reset|simulate-read|test|session_start|pre_tool_use|post_tool_use|stop>")
        sys.exit(1)

    cmd = sys.argv[1].lower()

    if cmd == "test":
        success = run_tests()
        sys.exit(0 if success else 1)

    if cmd == "verify":
        api_root, flutter_root, err = find_repo_roots()
        if err:
            print(f"VERIFICATION ERROR: {err}")
            sys.exit(1)
        manifest = scan_dynamic_inventory(api_root, flutter_root)
        print(f"Verified API Root:     {api_root}")
        print(f"Verified Flutter Root: {flutter_root}")
        print(f"Dynamic Governance Inventory: {len(manifest)} mandatory documents discovered.")
        for k in sorted(manifest.keys()):
            item = manifest[k]
            print(f"  [{item['category']}] {k} ({item['lineCount']} lines)")
        sys.exit(0)

    if cmd == "manifest":
        api_root, flutter_root, err = find_repo_roots()
        if err:
            print(json.dumps({"error": err}))
            sys.exit(1)
        manifest = scan_dynamic_inventory(api_root, flutter_root)
        print(json.dumps(manifest, indent=2))
        sys.exit(0)

    if cmd == "status":
        conv_id = sys.argv[2] if len(sys.argv) > 2 else "default_session"
        ledger = GovernanceLedger(conv_id)
        ledger._recompute_status()
        tot = len(ledger.data.get("manifest", {}))
        cov = ledger.data.get("coverage", {})
        completed_count = sum(1 for c in cov.values() if c.get("isComplete", False))
        pct = (completed_count / tot * 100) if tot > 0 else 0.0
        print(f"Conversation: {conv_id}")
        print(f"Status:       {ledger.data.get('status')}")
        print(f"Coverage:     {completed_count}/{tot} files ({pct:.1f}%)")
        if ledger.data.get("status") != "COMPLETE":
            unread = ledger.get_unread_summary()
            print(f"Unread Files ({len(unread)}):")
            for u in unread[:10]:
                print(f"  - {u}")
            if len(unread) > 10:
                print(f"  ... and {len(unread) - 10} more.")
        sys.exit(0)

    if cmd == "reset":
        conv_id = sys.argv[2] if len(sys.argv) > 2 else "default_session"
        ledger_file = os.path.join(LEDGER_DIR, f"{conv_id}.json")
        if os.path.isfile(ledger_file):
            os.remove(ledger_file)
            print(f"Reset ledger for conversation: {conv_id}")
        else:
            print(f"No existing ledger for conversation: {conv_id}")
        sys.exit(0)

    if cmd == "simulate-read":
        if len(sys.argv) < 5:
            print("Usage: governance_engine.py simulate-read <file_path> <start_line> <end_line> [conv_id]")
            sys.exit(1)
        file_path = sys.argv[2]
        s_line = int(sys.argv[3])
        e_line = int(sys.argv[4])
        conv_id = sys.argv[5] if len(sys.argv) > 5 else "default_session"
        ledger = GovernanceLedger(conv_id)
        res = ledger.record_file_read(file_path, s_line, e_line)
        print(f"Recorded read: {file_path} [{s_line}-{e_line}] -> {res}")
        sys.exit(0)

    # Hook mode: expects JSON payload on stdin
    try:
        raw_input = sys.stdin.read()
        payload = json.loads(raw_input) if raw_input.strip() else {}
    except Exception as e:
        payload = {}

    response = {}
    if cmd == "session_start":
        response = hook_session_start(payload)
    elif cmd == "pre_tool_use":
        response = hook_pre_tool_use(payload)
    elif cmd == "post_tool_use":
        response = hook_post_tool_use(payload)
    elif cmd == "stop":
        response = hook_stop(payload)

    sys.stdout.write(json.dumps(response))
    sys.stdout.flush()


if __name__ == "__main__":
    main()

