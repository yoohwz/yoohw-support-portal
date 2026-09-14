#!/usr/bin/env python3
import re
import subprocess
import tempfile
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

    assert "dedicated **YoOhw Support Portal** project" in agents
    assert "configured source folder" in agents
    assert "yoohwz/yoohw-support-portal" in agents
    assert "Do not create a second clone" in agents
    assert "dedicated YoOhw Support Portal project" in workflow
    assert "configured source folder" in workflow
    assert "same dedicated project" in workflow
    assert "fresh Codex review context" in workflow
    assert "canonical source working tree read-only" in workflow
    assert "yoohwz/yoohw-support-portal" in workflow
    assert "/Users/" not in agents
    assert "/Users/" not in workflow

    for forbidden in (
        "DIRECT_YSP",
        "STANDARD_YSP",
        "CRITICAL_YSP",
        "task-state.json",
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


def run_stage(destination: Path) -> subprocess.CompletedProcess[str]:
    return subprocess.run(
        ["bash", str(ROOT / "scripts/stage-distribution.sh"), str(ROOT), str(destination)],
        cwd=ROOT,
        text=True,
        stdout=subprocess.PIPE,
        stderr=subprocess.PIPE,
        check=False,
    )


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
        "/.env",
        "/.env.*",
        "*.log",
        "*.zip",
    }
    assert required.issubset(distignore), sorted(required - distignore)

    stage = text("scripts/stage-distribution.sh")
    for fragment in (
        "git -C \"$SOURCE\" ls-files -z",
        "--files-from=",
        "--exclude-from=",
        "destination must not already exist",
        "forbidden development artifact",
        "symbolic links",
        "nested ZIP archives",
        "local environment or log artifacts",
        "yoohw-support-portal.php",
        "readme.txt",
    ):
        assert fragment in stage, fragment

    assert "rm -rf" not in stage
    assert "|| true" not in stage


def distribution_adversarial_contract() -> None:
    artifacts = {
        ROOT / ".env": "YSP_FOUNDATION_SECRET=must-not-ship\n",
        ROOT / "debug.log": "private debug output\n",
    }
    created: list[Path] = []

    try:
        for path, contents in artifacts.items():
            if not path.exists():
                path.write_text(contents, encoding="utf-8")
                created.append(path)

        with tempfile.TemporaryDirectory(prefix="ysp-distribution-") as temporary:
            temp = Path(temporary)
            fresh = temp / "fresh-stage"
            result = run_stage(fresh)
            assert result.returncode == 0, result.stderr or result.stdout
            assert (fresh / "yoohw-support-portal.php").is_file()
            assert (fresh / "readme.txt").is_file()
            assert not (fresh / ".env").exists()
            assert not (fresh / "debug.log").exists()

            existing = temp / "existing-destination"
            existing.mkdir()
            sentinel = existing / "sentinel.txt"
            sentinel.write_text("keep-me\n", encoding="utf-8")

            blocked = run_stage(existing)
            assert blocked.returncode != 0
            assert "destination must not already exist" in blocked.stderr
            assert sentinel.read_text(encoding="utf-8") == "keep-me\n"
            assert not (existing / "yoohw-support-portal.php").exists()
    finally:
        for path in created:
            path.unlink(missing_ok=True)


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
    distribution_adversarial_contract()
    foundation_scope_contract()
    print("repository-contracts-ok")


if __name__ == "__main__":
    main()
