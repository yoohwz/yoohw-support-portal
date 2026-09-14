#!/usr/bin/env python3
import re
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]


def text(path: str) -> str:
    return (ROOT / path).read_text(encoding="utf-8")


def version_contract() -> None:
    plugin = text("yoohw-support-portal.php")
    readme = text("readme.txt")

    header = re.search(r"^Version:\s*([^\s]+)", plugin, re.MULTILINE)
    constant = re.search(r"YOOHW_SUPPORT_PORTAL_VERSION',\s*'([^']+)'", plugin)
    stable = re.search(r"^Stable tag:\s*([^\s]+)", readme, re.MULTILINE)
    requires_php = re.search(r"^Requires PHP:\s*([^\s]+)", readme, re.MULTILINE)

    assert header and constant and stable and requires_php
    assert header.group(1) == constant.group(1) == stable.group(1), (
        header.group(1),
        constant.group(1),
        stable.group(1),
    )
    assert requires_php.group(1) == "7.4"


def workflow_contract() -> None:
    agents = text("AGENTS.md")
    workflow = text("docs/workflow.md")
    ci = text(".github/workflows/ci.yml")

    for required in (
        "STANDARD",
        "CONTROLLED",
        "READY_TO_FINALIZE",
        "YSP Required CI",
        "Iteration Gate",
        "Git/GitHub",
    ):
        assert required in agents or required in workflow, required

    for forbidden in (
        "DIRECT_YSP",
        "STANDARD_YSP",
        "CRITICAL_YSP",
        "task-state.json",
        "evidence ledger",
    ):
        assert forbidden not in workflow, forbidden

    for required in (
        "pull_request:",
        "ready_for_review",
        "converted_to_draft",
        "cancel-in-progress",
        "YSP Required CI",
        "Iteration Gate",
        "always()",
        "scripts/stage-distribution.sh",
        "source-safety-contract-tests.py",
        "repository-contract-tests.py",
    ):
        assert required in ci, required

    # Workflow state is owned by GitHub. CI must not parse Issue/review prose.
    for forbidden in (
        "issue_comment",
        "pull_request_review:",
        "workflow_dispatch",
        "gh api",
        "issues/comments",
    ):
        assert forbidden not in ci, forbidden


def distribution_contract() -> None:
    distignore = {line.strip() for line in text(".distignore").splitlines() if line.strip()}
    required = {
        "/.git",
        "/.github",
        "/tests",
        "/docs",
        "/scripts",
        "/AGENTS.md",
        "/.distignore",
        "*.zip",
    }
    assert required.issubset(distignore), sorted(required - distignore)

    stage = text("scripts/stage-distribution.sh")
    for fragment in (
        "--exclude-from=",
        "forbidden development artifact",
        "symbolic links",
        "nested ZIP archives",
        "yoohw-support-portal.php",
        "readme.txt",
    ):
        assert fragment in stage, fragment


def foundation_scope_contract() -> None:
    # The Foundation branch must be governance/test/distribution only. This test
    # cannot inspect git history itself, but it makes the intended immutable
    # boundary explicit for human/reviewer diff verification.
    workflow = text("docs/workflow.md")
    safety = text("docs/support-data-safety.md")
    assert "Do not create another mutable task-state database" in workflow
    assert "do not claim a runtime combination was tested" in safety.lower()


def main() -> None:
    version_contract()
    workflow_contract()
    distribution_contract()
    foundation_scope_contract()
    print("repository-contracts-ok")


if __name__ == "__main__":
    main()
