#!/usr/bin/env python3
"""High-leverage contracts for the release-only WordPress.org publisher."""
from __future__ import annotations

import ast
import importlib.util
from pathlib import Path
import tempfile
import zipfile

ROOT = Path(__file__).resolve().parents[1]


def text(path: str) -> str:
    return (ROOT / path).read_text(encoding="utf-8")


def syntax_contract() -> None:
    for path in (
        ".github/scripts/release_lib.py",
        ".github/scripts/release_cli.py",
        "tests/release-contract-tests.py",
    ):
        ast.parse(text(path), filename=path)


def workflow_contract() -> None:
    prepare = text(".github/workflows/release-prepare.yml")
    publish = text(".github/workflows/publish-wordpress-org.yml")
    context = text(".github/actions/publisher-context/action.yml")

    for workflow in (prepare, publish):
        assert "workflow_dispatch:" in workflow
        assert "pull_request:" not in workflow
        assert "push:" not in workflow
        assert "GITHUB_REF_PROTECTED" in workflow or "publisher-context" in workflow

    for required in (
        "test \"$CANDIDATE_SHA\" = \"$GITHUB_SHA\"",
        "release_cli.py prepare",
        "Plugin Check exact prepared WordPress.org payload",
        "strict: false",
        "ysp-release/rc/*",
        "retention-days: 90",
    ):
        assert required in prepare, required

    for required in (
        "options: [publish, verify-only]",
        "default: true",
        "preparation_run_id:",
        "original_publish_run_id:",
        "wordpress-org-production",
        "Read-only WordPress.org publication preflight",
        "Dry-run final remote recheck",
        "Human-gated atomic WordPress.org publication",
        "Read-only WordPress.org recovery and propagation check",
        "GitHub Release only after verified WordPress.org publication",
        "release_cli.py recheck",
        "release_cli.py seal",
        "release_cli.py commit",
        "release_cli.py verify",
        "release_cli.py recover",
        "release_cli.py release",
    ):
        assert required in publish, required

    assert publish.count("secrets.WPORG_SVN_PASSWORD") == 1
    assert "secrets.WPORG_SVN_PASSWORD" not in prepare
    assert "secrets.WPORG_SVN_PASSWORD" not in context
    assert "WPORG_SVN_PASSWORD" not in context

    for required in (
        "test \"$GITHUB_REPOSITORY\" = yoohwz/yoohw-support-portal",
        "test \"$GITHUB_REF\" = refs/heads/main",
        "test \"$GITHUB_REF_PROTECTED\" = true",
        "publish-wordpress-org.yml@refs/heads/main",
        "actions/download-artifact@d3f86a106a0bac45b974a628896c90dbdf5c8093",
        "ysp-wporg-${{ env.VERSION }}-${{ env.CANDIDATE_SHA }}",
        "subversion rsync",
        "release_cli.py context",
    ):
        assert required in context, required


def implementation_contract() -> None:
    lib = text(".github/scripts/release_lib.py")
    cli = text(".github/scripts/release_cli.py")

    for required in (
        'REPOSITORY = "yoohwz/yoohw-support-portal"',
        'SLUG = "yoohw-support-portal"',
        'SVN_URL = f"https://plugins.svn.wordpress.org/{SLUG}"',
        "scripts/stage-distribution.sh",
        "zipfile.ZIP_STORED",
        "date_time=(1980, 1, 1, 0, 0, 0)",
        "--password-from-stdin",
        "--no-auth-cache",
        "WordPress.org assets are immutable in normal publication",
        "SVN commit outcome is unknown; do not retry publication, use verify-only recovery",
        "downloads.wordpress.org/plugin/",
        "WPORG_PROPAGATION_PENDING",
        "WPORG_PUBLIC_RELEASE_VERIFIED",
    ):
        assert required in lib, required

    assert '"--password",' not in lib
    assert "WPORG_SVN_PASSWORD" not in lib

    for command in ("prepare", "context", "preflight", "recheck", "seal", "commit", "verify", "recover", "release"):
        assert f'"{command}":' in cli, command
    assert "PUBLISH_ENVIRONMENT" in cli
    assert "wordpress-org-production" in cli
    assert "dry-run cannot mutate external state" in cli


def documentation_contract() -> None:
    docs = text("docs/releasing.md")
    for required in (
        "wordpress-org-production",
        "WPORG_SVN_USERNAME",
        "WPORG_SVN_PASSWORD",
        "SVN-specific password",
        "dry_run=true",
        "dry_run=false",
        "verify-only",
        "SVN_COMMIT_OUTCOME_UNKNOWN",
        "WPORG_PROPAGATION_PENDING",
        "WPORG_PUBLIC_RELEASE_VERIFIED",
        "GitHub Release",
        "does not publish version 1.0.0",
    ):
        assert required in docs, required


def load_release_lib():
    path = ROOT / ".github/scripts/release_lib.py"
    spec = importlib.util.spec_from_file_location("ysp_release_lib_contract", path)
    assert spec and spec.loader
    module = importlib.util.module_from_spec(spec)
    spec.loader.exec_module(module)
    return module


def deterministic_package_contract() -> None:
    rel = load_release_lib()
    with tempfile.TemporaryDirectory(prefix="ysp-release-contract-") as temporary:
        root = Path(temporary)
        payload = root / "payload"
        (payload / "inc").mkdir(parents=True)
        (payload / "readme.txt").write_text("release contract\n", encoding="utf-8")
        (payload / "inc/example.php").write_text("<?php echo 'ok';\n", encoding="utf-8")
        first = root / "first.zip"
        second = root / "second.zip"
        rel.deterministic_zip(payload, first)
        rel.deterministic_zip(payload, second)
        assert first.read_bytes() == second.read_bytes()
        extracted = rel.safe_extract_product(first, root / "extracted")
        assert rel.tree_digest(extracted) == rel.tree_digest(payload)

        malicious = root / "malicious.zip"
        with zipfile.ZipFile(malicious, "w") as archive:
            archive.writestr("yoohw-support-portal/../escape.txt", "no")
        try:
            rel.safe_extract_product(malicious, root / "malicious-out")
        except rel.ReleaseError:
            pass
        else:
            raise AssertionError("ZIP traversal fixture was not rejected")


def main() -> None:
    syntax_contract()
    workflow_contract()
    implementation_contract()
    documentation_contract()
    deterministic_package_contract()
    print("release-contracts-ok")


if __name__ == "__main__":
    main()
