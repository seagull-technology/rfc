#!/usr/bin/env python3
"""Build an offline IIS release from committed source and cached dependencies."""

import argparse
import gzip
import hashlib
import json
import os
from pathlib import Path, PurePosixPath
import re
import shutil
import subprocess
import sys
import tarfile
import tempfile
import time
import zipfile


RUNTIME_DIRS = {"app", "bootstrap", "config", "database", "lang", "public", "resources", "routes", "vendor", "scripts"}
RUNTIME_FILES = {"artisan", "composer.json", "composer.lock", "package.json", "package-lock.json", "vite.config.js"}
REQUIRED_FILES = {
    "artisan", "vendor/autoload.php", "public/build/manifest.json", "public/web.config",
    "public/js/lodash.min.js", "public/js/lodash.LICENSE.txt", "scripts/sync-vendor-assets.mjs",
}


def run(command, cwd, env=None, capture=False):
    print("+ " + " ".join(str(item) for item in command), flush=True)
    result = subprocess.run(command, cwd=cwd, env=env, check=True, text=True,
                            stdout=subprocess.PIPE if capture else None)
    return result.stdout.strip() if capture else None


def sha256(path):
    digest = hashlib.sha256()
    with path.open("rb") as stream:
        for block in iter(lambda: stream.read(1024 * 1024), b""):
            digest.update(block)
    return digest.hexdigest()


def relative_path(value):
    path = PurePosixPath(value)
    if path.is_absolute() or not path.parts or ".." in path.parts or "\\" in value:
        raise ValueError("Expected a repository-relative path without traversal: " + value)
    return path


def extract_source(archive, destination):
    with tarfile.open(archive, "r:") as source:
        for member in source.getmembers():
            relative_path(member.name)
            if not (member.isdir() or member.isfile()):
                raise ValueError("Source archive contains a link or special file: " + member.name)
        source.extractall(destination)


def include_runtime(path):
    parts = path.parts
    if not parts:
        return False
    if any(part in {".git", "node_modules", "tests", ".DS_Store", "__pycache__"} for part in parts):
        return False
    if path.name.startswith((".env", "._")):
        return str(path) == "deployment/windows/.env.production.example"
    if path.name in {"auth.json", ".npmrc", "hot"} or path.suffix in {".log", ".sqlite", ".sqlite3", ".pfx", ".p12", ".key"}:
        return False
    if parts[:2] in {("bootstrap", "cache"), ("public", "storage"), ("public", "uploads")}:
        return False
    if parts[0] == "deployment":
        return len(parts) == 3 and parts[1] == "windows" and path.suffix in {".ps1", ".txt", ".md"}
    return parts[0] in RUNTIME_DIRS or str(path) in RUNTIME_FILES


def runtime_files(source):
    selected = []
    for path in sorted(source.rglob("*")):
        relative = PurePosixPath(path.relative_to(source).as_posix())
        if not include_runtime(relative):
            continue
        if path.is_symlink():
            raise ValueError("Runtime symlink must be reviewed before packaging: " + str(relative))
        if path.is_file():
            selected.append(path)
    names = {path.relative_to(source).as_posix() for path in selected}
    missing = REQUIRED_FILES - names
    if missing:
        raise ValueError("Required runtime files are missing: " + ", ".join(sorted(missing)))
    return selected


def create_app_archive(source, target, timestamp):
    files = runtime_files(source)
    with target.open("wb") as raw:
        with gzip.GzipFile(filename="", mode="wb", fileobj=raw, mtime=0) as compressed:
            with tarfile.open(fileobj=compressed, mode="w", format=tarfile.PAX_FORMAT) as archive:
                for path in files:
                    info = archive.gettarinfo(str(path), arcname=path.relative_to(source).as_posix())
                    info.mtime, info.uid, info.gid = timestamp, 0, 0
                    info.uname, info.gname = "", ""
                    info.mode = 0o755 if path.stat().st_mode & 0o111 else 0o644
                    with path.open("rb") as content:
                        archive.addfile(info, content)
                # Deploy-RfcRelease.ps1 also creates these; retain a usable empty
                # skeleton for administrators extracting the archive manually.
                for name in ["bootstrap/cache", "storage/app/private", "storage/app/public",
                             "storage/framework/cache/data", "storage/framework/sessions",
                             "storage/framework/views", "storage/logs"]:
                    info = tarfile.TarInfo(name)
                    info.type, info.mode, info.mtime = tarfile.DIRTYPE, 0o755, timestamp
                    archive.addfile(info)
    return files


def create_zip(bundle, target, timestamp):
    date = time.gmtime(max(timestamp, 315532800))[:6]
    with zipfile.ZipFile(target, "w", compression=zipfile.ZIP_DEFLATED, compresslevel=6) as archive:
        for path in sorted(bundle.iterdir()):
            info = zipfile.ZipInfo(bundle.name + "/" + path.name, date_time=date)
            info.compress_type = zipfile.ZIP_DEFLATED
            info.external_attr = 0o100644 << 16
            archive.writestr(info, path.read_bytes())


def build_environment(work, composer_cache, npm_cache):
    # Preserve system paths, but do not import application/VITE settings or
    # registry credentials from the caller into the release build.
    env = {key: value for key, value in os.environ.items() if key in {
        "PATH", "HOME", "USER", "USERPROFILE", "LANG", "LC_ALL", "TMPDIR", "TMP", "TEMP", "SYSTEMROOT", "SystemRoot", "PATHEXT",
    }}
    composer_home = work / "composer-home"
    composer_home.mkdir()
    empty_npm_config = work / "empty.npmrc"
    empty_npm_config.write_text("", encoding="utf-8")
    empty_npm_global = work / "empty-global.npmrc"
    empty_npm_global.write_text("", encoding="utf-8")
    env.update({
        "COMPOSER_HOME": str(composer_home), "COMPOSER_CACHE_DIR": str(composer_cache),
        "COMPOSER_DISABLE_NETWORK": "1", "COMPOSER_NO_AUDIT": "1", "COMPOSER_NO_INTERACTION": "1",
        "COMPOSER_FUND": "0", "npm_config_cache": str(npm_cache), "npm_config_audit": "false",
        "npm_config_fund": "false", "npm_config_offline": "true",
        "npm_config_userconfig": str(empty_npm_config), "npm_config_globalconfig": str(empty_npm_global),
        "APP_ENV": "production", "APP_DEBUG": "false", "APP_URL": "https://filmjordan.jo",
        "GSB_ENABLED": "false", "CACHE_STORE": "array", "SESSION_DRIVER": "array",
        "DB_CONNECTION": "sqlite", "DB_DATABASE": ":memory:", "LOG_CHANNEL": "stderr", "MAIL_MAILER": "array",
    })
    return env


def verify_composer(source):
    lock = json.loads((source / "composer.lock").read_text())
    inventory = json.loads((source / "vendor/composer/installed.json").read_text())
    def identity(package):
        return (package["version"], package.get("dist", {}).get("reference"), package.get("source", {}).get("reference"))
    expected = {package["name"]: identity(package) for package in lock["packages"]}
    actual = {package["name"]: identity(package) for package in inventory["packages"]}
    if inventory.get("dev") is not False or expected != actual:
        raise ValueError("Installed Composer inventory is not exactly the production lockfile packages.")
    return {name: identity[0] for name, identity in actual.items()}


def seed_vendor(archive, expected_sha256, source):
    digest = sha256(archive)
    if digest != expected_sha256.lower():
        raise ValueError("Prior release archive checksum does not match the approved vendor seed.")
    with tarfile.open(archive) as previous:
        members = {PurePosixPath(member.name).as_posix(): member for member in previous.getmembers()}
        for name in ["composer.json", "composer.lock"]:
            member = members.get(name)
            if member is None or not member.isfile():
                raise ValueError("Prior release is missing " + name)
            with previous.extractfile(member) as stream:
                if stream.read() != (source / name).read_bytes():
                    raise ValueError("Prior release " + name + " differs from selected committed source.")
        for name, member in members.items():
            path = PurePosixPath(name)
            if path.parts[:1] != ("vendor",):
                continue
            relative_path(name)
            if not (member.isfile() or member.isdir()):
                raise ValueError("Prior vendor archive contains a link or special file: " + name)
            target = source / name
            if member.isdir():
                target.mkdir(parents=True, exist_ok=True)
            else:
                target.parent.mkdir(parents=True, exist_ok=True)
                with previous.extractfile(member) as content, target.open("wb") as output:
                    shutil.copyfileobj(content, output)
                target.chmod(0o755 if member.mode & 0o111 else 0o644)
    verify_composer(source)
    return digest


def main():
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("--ref", default="HEAD", help="Committed Git ref; never includes working-tree changes")
    parser.add_argument("--release-name", required=True, help="For example rfc-offline-release-20260908-v1")
    parser.add_argument("--notes", required=True, help="Release notes path present in the selected commit")
    parser.add_argument("--output-dir", default="deployment/releases")
    parser.add_argument("--composer-cache", help="Existing Composer cache directory (copied, never modified)")
    parser.add_argument("--npm-cache", help="Existing npm cache directory (copied, never modified)")
    parser.add_argument("--vendor-archive", help="Previously verified release tar.gz used as an offline Composer seed")
    parser.add_argument("--vendor-sha256", help="Approved SHA-256 of --vendor-archive; both options are required together")
    args = parser.parse_args()
    if not re.fullmatch(r"rfc-offline-release-[A-Za-z0-9][A-Za-z0-9._-]*", args.release_name):
        parser.error("Use a release name beginning rfc-offline-release- with no path separators.")
    if bool(args.vendor_archive) != bool(args.vendor_sha256) or (args.vendor_sha256 and not re.fullmatch(r"[0-9a-fA-F]{64}", args.vendor_sha256)):
        parser.error("--vendor-archive requires its approved 64-character --vendor-sha256.")
    notes = relative_path(args.notes)
    repo = Path(run(["git", "rev-parse", "--show-toplevel"], Path.cwd(), capture=True))
    commit = run(["git", "rev-parse", "--verify", args.ref + "^{commit}"], repo, capture=True)
    timestamp = int(run(["git", "show", "-s", "--format=%ct", commit], repo, capture=True))
    output = Path(args.output_dir).resolve()
    output.mkdir(parents=True, exist_ok=True)
    targets = [output / args.release_name, output / (args.release_name + ".zip"), output / (args.release_name + ".zip.sha256.txt")]
    if any(path.exists() for path in targets):
        raise ValueError("An output with this release name already exists; choose a new release name.")

    with tempfile.TemporaryDirectory(prefix="rfc-offline-build-") as temp:
        work = Path(temp)
        source = work / "source"
        source.mkdir()
        run(["git", "archive", "--format=tar", "--output=" + str(work / "source.tar"), commit], repo)
        extract_source(work / "source.tar", source)
        for name in [str(notes), "composer.lock", "package-lock.json", "scripts/sync-vendor-assets.mjs", "scripts/build-offline-release.py",
                     "deployment/windows/Deploy-RfcRelease.ps1", "deployment/windows/PRE-DEPLOY-CHECKLIST.txt",
                     "deployment/windows/.env.production.example", "deployment/windows/SECURITY-RETEST.md"]:
            if not (source / name).is_file():
                raise ValueError("Selected commit does not contain required release input: " + name)
        if (source / ".env").exists() or (source / "auth.json").exists() or (source / ".npmrc").exists():
            raise ValueError("Committed local environment/credential configuration must not enter release staging.")
        if sha256(Path(__file__).resolve()) != sha256(source / "scripts/build-offline-release.py"):
            raise ValueError("The running release builder differs from the selected commit; commit/review it first.")

        tracked_integrity = {name: sha256(source / name) for name in [
            "composer.json", "composer.lock", "package.json", "package-lock.json",
            "public/js/lodash.min.js", "public/js/lodash.LICENSE.txt", "scripts/sync-vendor-assets.mjs", "scripts/build-offline-release.py",
        ]}
        npm_existing = Path(args.npm_cache or run(["npm", "config", "get", "cache"], repo, capture=True)).expanduser()
        print("Copying dependency caches for an isolated offline build...", flush=True)
        vendor_seed = None
        if args.vendor_archive:
            vendor_seed = seed_vendor(Path(args.vendor_archive).resolve(), args.vendor_sha256, source)
            (work / "composer-cache/files").mkdir(parents=True)
        else:
            composer_existing = Path(args.composer_cache or run(["composer", "config", "cache-dir"], repo, capture=True)).expanduser()
            shutil.copytree(composer_existing / "files", work / "composer-cache/files")
        shutil.copytree(npm_existing / "_cacache", work / "npm-cache/_cacache")
        env = build_environment(work, work / "composer-cache", work / "npm-cache")

        run(["composer", "install", "--no-dev", "--classmap-authoritative", "--prefer-dist",
             "--no-interaction", "--no-progress", "--no-plugins", "--no-scripts"], source, env)
        packages = verify_composer(source)
        run(["composer", "check-platform-reqs", "--no-dev"], source, env)
        run(["npm", "ci", "--offline", "--no-audit", "--no-fund", "--include=dev", "--ignore-scripts"], source, env)
        run(["npm", "run", "vendor:check"], source, env)
        run(["npm", "run", "build"], source, env)
        run(["npm", "run", "vendor:check"], source, env)
        for name, original_hash in tracked_integrity.items():
            if sha256(source / name) != original_hash:
                raise ValueError("Build changed committed dependency/asset input: " + name)

        # Only these known Laravel checks run: arbitrary Composer lifecycle
        # scripts and npm install scripts remain disabled.
        run(["php", "artisan", "package:discover", "--ansi"], source, env)
        run(["php", "artisan", "view:cache"], source, env)
        bundle = work / args.release_name
        bundle.mkdir()
        files = create_app_archive(source, bundle / "rfc-app.tar.gz", timestamp)
        for name in ["Deploy-RfcRelease.ps1", "Install-RfcQueueWorker.ps1", "PRE-DEPLOY-CHECKLIST.txt", ".env.production.example", "SECURITY-RETEST.md"]:
            shutil.copyfile(source / "deployment/windows" / name, bundle / name)
        staging_launcher = source / "deployment/windows/Deploy-RfcStagingVerification.ps1"
        if staging_launcher.is_file():
            shutil.copyfile(staging_launcher, bundle / staging_launcher.name)
        shutil.copyfile(source / str(notes), bundle / "RELEASE-NOTES.txt")
        windows_bundle = "C:\\Deploy\\" + args.release_name
        (bundle / "DEPLOY-COMMAND.txt").write_text(
            "After extracting the ZIP under C:\\Deploy, verify SHA256SUMS.txt and complete the checklist.\n"
            "Run in an elevated PowerShell window:\n\nSet-Location C:\\inetpub\n"
            + "& '" + windows_bundle + "\\Deploy-RfcRelease.ps1' `\n"
            + "  -ArchivePath '" + windows_bundle + "\\rfc-app.tar.gz' `\n"
            + "  -ExpectedSha256 '" + sha256(bundle / "rfc-app.tar.gz") + "'\n",
            encoding="utf-8")
        (bundle / "SOURCE-COMMIT.txt").write_text(
            "RFC e-Permit source commit\n==========================\n\n"
            + "Release: " + args.release_name + "\nRequested ref: " + args.ref + "\nCommit: " + commit
            + "\n\nBuilt from git archive of this commit; working-tree changes were excluded.\n", encoding="utf-8")
        manifest = {
            "schema": "rfc-offline-release-v1", "release": args.release_name, "source_commit": commit,
            "source_commit_timestamp": timestamp, "builder_sha256": sha256(Path(__file__).resolve()),
            "network_disabled": True, "advisory_audit_performed": False,
            "vendor_seed_archive_sha256": vendor_seed,
            "source_integrity": tracked_integrity, "composer_production_packages": packages,
            "tools": {name: run(command, source, env, capture=True) for name, command in {
                "php": ["php", "-r", "echo PHP_VERSION;"], "composer": ["composer", "--version", "--no-ansi"],
                "node": ["node", "--version"], "npm": ["npm", "--version"],
            }.items()},
            "runtime_files": {path.relative_to(source).as_posix(): sha256(path) for path in files},
        }
        (bundle / "BUILD-MANIFEST.json").write_text(json.dumps(manifest, indent=2, sort_keys=True) + "\n", encoding="utf-8")
        (bundle / "SHA256SUMS.txt").write_text("".join(
            sha256(path) + "  " + path.name + "\n" for path in sorted(bundle.iterdir()) if path.is_file()
        ), encoding="utf-8")
        zip_path = work / targets[1].name
        create_zip(bundle, zip_path, timestamp)
        checksum = sha256(zip_path) + "  " + zip_path.name + "\n"
        shutil.move(str(bundle), targets[0])
        shutil.move(str(zip_path), targets[1])
        targets[2].write_text(checksum, encoding="utf-8")
        print("Release created from " + commit + ":\n" + str(targets[1]) + "\n" + checksum, flush=True)


if __name__ == "__main__":
    try:
        main()
    except (ValueError, OSError, subprocess.CalledProcessError) as error:
        print("Release build failed: " + str(error), file=sys.stderr)
        sys.exit(1)
