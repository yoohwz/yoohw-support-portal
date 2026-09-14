#!/usr/bin/env python3
import re
import shutil
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


def branding_contract() -> None:
    plugin = text("yoohw-support-portal.php")
    readme = text("readme.txt")
    license_text = text("license.txt")
    release_lib = text(".github/scripts/release_lib.py")

    assert re.search(r"^Plugin Name:\s*Support Portal\s*$", plugin, re.MULTILINE)
    assert re.search(r"^=== Support Portal ===\s*$", readme, re.MULTILINE)
    assert license_text.startswith("Support Portal\n")

    # Brand rename must not change compatibility identifiers.
    assert re.search(r"^Text Domain:\s*yoohw-support-portal\s*$", plugin, re.MULTILINE)
    assert 'REPOSITORY = "yoohwz/yoohw-support-portal"' in release_lib
    assert 'SLUG = "yoohw-support-portal"' in release_lib
    assert 'SVN_URL = f"https://plugins.svn.wordpress.org/{SLUG}"' in release_lib

    # Keep the retired public product name out of tracked text without embedding
    # that stale literal contiguously in the contract itself.
    legacy_name = "YoOhw" + " Support Portal"
    tracked = subprocess.run(
        ["git", "ls-files", "-z"],
        cwd=ROOT,
        check=True,
        stdout=subprocess.PIPE,
    ).stdout.split(b"\0")
    stale = []
    for raw in tracked:
        if not raw:
            continue
        path = ROOT / raw.decode("utf-8")
        if not path.is_file():
            continue
        try:
            contents = path.read_text(encoding="utf-8")
        except UnicodeDecodeError:
            continue
        if legacy_name in contents:
            stale.append(path.relative_to(ROOT).as_posix())
    assert not stale, stale


def license_contract() -> None:
    plugin = text("yoohw-support-portal.php")
    readme = text("readme.txt")
    license_path = ROOT / "license.txt"
    license_text = license_path.read_text(encoding="utf-8")
    license_bytes = license_path.read_bytes()

    assert re.search(r"^License:\s*GPL-2\.0-or-later\s*$", plugin, re.MULTILINE)
    assert re.search(r"^License:\s*GPLv2 or later\s*$", readme, re.MULTILINE)
    for fragment in (
        "Support Portal",
        "Copyright (C) 2026 YoOhw",
        "either version 2 of the License, or (at your option) any later version",
        "GNU GENERAL PUBLIC LICENSE",
        "Version 2, June 1991",
        "    b) You must cause any work that you distribute or publish, that in",
    ):
        assert fragment in license_text, fragment

    # The rename is allowed to change only the product-name line. Lock the exact
    # post-rename license bytes so the GPLv2 body and final newline cannot drift.
    assert license_bytes.endswith(b"\n")
    license_blob = subprocess.run(
        ["git", "hash-object", "license.txt"],
        cwd=ROOT,
        text=True,
        check=True,
        stdout=subprocess.PIPE,
    ).stdout.strip()
    assert license_blob == "6a6b64a734602bf8865d7b348a6ff94dcc7b6251", license_blob


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


def run_stage(destination: Path, source: Path = ROOT) -> subprocess.CompletedProcess[str]:
    return subprocess.run(
        ["bash", str(ROOT / "scripts/stage-distribution.sh"), str(source), str(destination)],
        cwd=source,
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
        ".DS_Store",
        ".env",
        ".env.*",
        "*.[Ll][Oo][Gg]",
        "*.[Zz][Ii][Pp]",
        "*.[Rr][Aa][Rr]",
        "*.7[Zz]",
        "*.[Tt][Aa][Rr]",
        "*.[Tt][Gg][Zz]",
        "*.[Gg][Zz]",
        "*.[Bb][Zz]2",
        "*.[Xx][Zz]",
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
        "local artifacts or nested archives",
        ".DS_Store|*/.DS_Store",
        "*.[Zz][Ii][Pp]",
        "-iname '*.zip'",
        "-iname '*.tar'",
        "yoohw-support-portal.php",
        "readme.txt",
        "license.txt",
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
            assert (fresh / "license.txt").is_file()
            assert "GNU GENERAL PUBLIC LICENSE" in (fresh / "license.txt").read_text(encoding="utf-8")
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

            # Build an isolated local Git worktree whose adversarial artifacts are
            # genuinely tracked. This proves the manifest filter is the boundary,
            # rather than relying on the cleanliness of the CI checkout.
            fixture = temp / "tracked-fixture"
            shutil.copytree(ROOT, fixture, ignore=shutil.ignore_patterns(".git"))
            tracked_artifacts = {
                fixture / "assets/.DS_Store": "finder metadata\n",
                fixture / "assets/archive.ZIP": "nested zip placeholder\n",
                fixture / "assets/cache.LOG": "local log placeholder\n",
                fixture / "assets/package.tar.gz": "nested archive placeholder\n",
            }
            for path, contents in tracked_artifacts.items():
                path.parent.mkdir(parents=True, exist_ok=True)
                path.write_text(contents, encoding="utf-8")

            subprocess.run(["git", "init", "-q"], cwd=fixture, check=True)
            subprocess.run(["git", "add", "-f", "-A"], cwd=fixture, check=True)
            for path in tracked_artifacts:
                relative = path.relative_to(fixture).as_posix()
                subprocess.run(
                    ["git", "ls-files", "--error-unmatch", relative],
                    cwd=fixture,
                    check=True,
                    stdout=subprocess.DEVNULL,
                )

            tracked_stage = temp / "tracked-stage"
            tracked_result = run_stage(tracked_stage, fixture)
            assert tracked_result.returncode == 0, tracked_result.stderr or tracked_result.stdout
            assert (tracked_stage / "license.txt").is_file()
            for path in tracked_artifacts:
                relative = path.relative_to(fixture)
                assert not (tracked_stage / relative).exists(), relative
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
    branding_contract()
    license_contract()
    workflow_contract()
    distribution_contract()
    distribution_adversarial_contract()
    foundation_scope_contract()
    print("repository-contracts-ok")


if __name__ == "__main__":
    main()
