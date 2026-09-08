import importlib.util
import io
from pathlib import Path, PurePosixPath
import tarfile
import tempfile
import unittest
import zipfile


SPEC = importlib.util.spec_from_file_location(
    "release_builder", Path(__file__).resolve().parents[2] / "scripts/build-offline-release.py"
)
builder = importlib.util.module_from_spec(SPEC)
SPEC.loader.exec_module(builder)


class OfflineReleaseTest(unittest.TestCase):
    def test_runtime_selection_excludes_sensitive_and_development_files(self):
        for name in [".env", "app/.env", "storage/logs/laravel.log", "storage/app/private/upload.pdf",
                     "public/storage/upload.pdf", "public/uploads/upload.pdf", "database/database.sqlite",
                     "bootstrap/cache/config.php", "tests/Feature/Test.php", "node_modules/lodash/index.js",
                     "vendor/package/.git/config", "app/private.key", "deployment/releases/old/rfc-app.tar.gz"]:
            with self.subTest(name=name):
                self.assertFalse(builder.include_runtime(PurePosixPath(name)))
        for name in ["artisan", "app/Models/User.php", "public/build/manifest.json", "vendor/autoload.php",
                     "deployment/windows/.env.production.example", "scripts/sync-vendor-assets.mjs"]:
            with self.subTest(name=name):
                self.assertTrue(builder.include_runtime(PurePosixPath(name)))

    def test_source_extraction_rejects_traversal_and_links(self):
        for name, kind in [("../escape", tarfile.REGTYPE), ("/absolute", tarfile.REGTYPE), ("link", tarfile.SYMTYPE)]:
            with self.subTest(name=name), tempfile.TemporaryDirectory() as temp:
                root = Path(temp)
                archive = root / "source.tar"
                with tarfile.open(archive, "w") as handle:
                    member = tarfile.TarInfo(name)
                    member.type = kind
                    member.linkname = "/etc/passwd" if kind == tarfile.SYMTYPE else ""
                    handle.addfile(member, io.BytesIO())
                with self.assertRaises(ValueError):
                    builder.extract_source(archive, root / "source")

    def test_archive_and_zip_contain_only_required_runtime_and_safe_skeleton(self):
        with tempfile.TemporaryDirectory() as temp:
            root = Path(temp)
            source, bundle = root / "source", root / "rfc-offline-release-test"
            bundle.mkdir()
            for name in builder.REQUIRED_FILES | {"storage/app/private/secret.pdf", ".env", "bootstrap/cache/config.php"}:
                path = source / name
                path.parent.mkdir(parents=True, exist_ok=True)
                path.write_text("fixture")
            archive = bundle / "rfc-app.tar.gz"
            files = builder.create_app_archive(source, archive, 1700000000)
            self.assertEqual({path.relative_to(source).as_posix() for path in files}, builder.REQUIRED_FILES)
            with tarfile.open(archive) as handle:
                self.assertTrue(builder.REQUIRED_FILES.issubset(set(handle.getnames())))
                self.assertNotIn(".env", handle.getnames())
                self.assertNotIn("storage/app/private/secret.pdf", handle.getnames())
                self.assertTrue(handle.getmember("bootstrap/cache").isdir())
            first_hash = builder.sha256(archive)
            builder.create_app_archive(source, archive, 1700000000)
            self.assertEqual(builder.sha256(archive), first_hash)
            target = root / "release.zip"
            builder.create_zip(bundle, target, 1700000000)
            with zipfile.ZipFile(target) as handle:
                self.assertEqual(handle.namelist(), [bundle.name + "/rfc-app.tar.gz"])
                self.assertIsNone(handle.testzip())

    def test_vendor_seed_requires_the_approved_archive_and_matching_composer_inputs(self):
        with tempfile.TemporaryDirectory() as temp:
            root = Path(temp)
            source = root / "source"
            source.mkdir()
            payloads = {
                "composer.json": b'{"require":{}}',
                "composer.lock": b'{"packages":[]}',
                "vendor/composer/installed.json": b'{"dev":false,"packages":[]}',
                "vendor/autoload.php": b'<?php // vendor fixture',
                "app/stale.php": b'<?php // never copy old application files',
            }
            for name in ["composer.json", "composer.lock"]:
                (source / name).write_bytes(payloads[name])
            archive = root / "previous.tar.gz"
            with tarfile.open(archive, "w:gz") as handle:
                for name, content in payloads.items():
                    member = tarfile.TarInfo("./" + name)
                    member.size = len(content)
                    handle.addfile(member, io.BytesIO(content))
            with self.assertRaisesRegex(ValueError, "checksum"):
                builder.seed_vendor(archive, "0" * 64, source)
            digest = builder.sha256(archive)
            (source / "composer.lock").write_text('{"packages":[],"changed":true}')
            with self.assertRaisesRegex(ValueError, "differs"):
                builder.seed_vendor(archive, digest, source)
            (source / "composer.lock").write_bytes(payloads["composer.lock"])
            self.assertEqual(builder.seed_vendor(archive, digest, source), digest)
            self.assertTrue((source / "vendor/autoload.php").is_file())
            self.assertFalse((source / "app/stale.php").exists())


if __name__ == "__main__":
    unittest.main()
