#!/usr/bin/env python3
"""Validate a distributable Sky Fundi ZIP without extracting unsafe paths."""
from __future__ import annotations

import sys
from pathlib import PurePosixPath
from zipfile import ZipFile

REQUIRED = {"artisan", "composer.json", "Dockerfile", "compose.yaml", "README.md", ".env.example"}


def unsafe(name: str) -> bool:
    path = PurePosixPath(name)
    return "\\" in name or path.is_absolute() or ".." in path.parts


def main() -> int:
    if len(sys.argv) != 2:
        print("Usage: verify-archive.py PATH_TO_ZIP", file=sys.stderr)
        return 2
    with ZipFile(sys.argv[1]) as archive:
        names = archive.namelist()
    violations = [name for name in names if unsafe(name)]
    roots = {name.split("/", 1)[0] for name in names if name}
    missing = sorted(REQUIRED - roots)
    if violations or missing:
        for name in violations:
            print(f"Unsafe ZIP entry: {name}", file=sys.stderr)
        if missing:
            print("Missing root files: " + ", ".join(missing), file=sys.stderr)
        return 1
    print(f"Archive OK: {len(names)} entries; POSIX relative paths; required root files present.")
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
