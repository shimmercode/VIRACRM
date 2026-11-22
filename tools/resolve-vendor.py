#!/usr/bin/env python3
"""
VIRA CRM — GitHub-only vendor resolver.

WHY THIS EXISTS
---------------
In this development environment `composer install` is impossible:
  * no native PHP / no composer binary
  * `repo.packagist.org` is blocked (only github.com / npm / pypi are reachable)

So we resolve the dependency graph ourselves straight from GitHub releases,
and generate a real PSR-4 autoloader. The result is a genuine vendor/ tree
that Laravel can actually boot against.

WHAT THIS IS NOT
----------------
This is a DEVELOPMENT-ONLY workaround. On the client host the project is
installed with real Composer against Packagist. `composer.json`/`composer.lock`
remain the source of truth for production.

Usage:
    python3 tools/resolve-vendor.py            # full resolve + download
    python3 tools/resolve-vendor.py --verify   # re-verify existing vendor tree
"""
from __future__ import annotations

import argparse
import io
import json
import os
import re
import sys
import tarfile
import urllib.error
import urllib.request

ROOT = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
CACHE = os.path.join(ROOT, ".vendor-cache")
VENDOR = os.path.join(ROOT, "vendor")

# Packages whose GitHub repo name differs from their Composer name.
KNOWN_REPOS = {
    "egulias/email-validator": "egulias/EmailValidator",
    "guzzlehttp/guzzle": "guzzle/guzzle",
    "guzzlehttp/uri-template": "guzzle/uri-template",
    "guzzlehttp/promises": "guzzle/promises",
    "guzzlehttp/psr7": "guzzle/psr7",
    "league/commonmark": "thephpleague/commonmark",
    "league/flysystem": "thephpleague/flysystem",
    "league/flysystem-local": "thephpleague/flysystem-local",
    "league/uri": "thephpleague/uri",
    "league/uri-interfaces": "thephpleague/uri-interfaces",
    "league/mime-type-detection": "thephpleague/mime-type-detection",
    "league/config": "thephpleague/config",
    "monolog/monolog": "Seldaek/monolog",
    "nesbot/carbon": "briannesbitt/Carbon",
    "psr/container": "php-fig/container",
    "psr/log": "php-fig/log",
    "psr/simple-cache": "php-fig/simple-cache",
    "psr/http-message": "php-fig/http-message",
    "psr/http-client": "php-fig/http-client",
    "psr/http-factory": "php-fig/http-factory",
    "psr/clock": "php-fig/clock",
    "psr/event-dispatcher": "php-fig/event-dispatcher",
    "tijsverkoyen/css-to-inline-styles": "tijsverkoyen/CssToInlineStyles",
    "graham-campbell/result-type": "GrahamCampbell/Result-Type",
    "phpoption/phpoption": "schmittjoh/php-option",
    "dflydev/dot-access-data": "dflydev/dflydev-dot-access-data",
    "ralouphie/getallheaders": "ralouphie/getallheaders",
}

# Extra packages the application needs that aren't transitive in Laravel core.
EXTRA_REQUIREMENTS = [
    # ("vendor/package", "constraint")
]


def log(msg: str) -> None:
    print(msg, flush=True)


def gh_json(url: str):
    req = urllib.request.Request(
        url, headers={"User-Agent": "vira-crm-resolver", "Accept": "application/vnd.github+json"}
    )
    with urllib.request.urlopen(req, timeout=40) as r:
        return json.load(r)


def gh_bytes(url: str) -> bytes:
    req = urllib.request.Request(url, headers={"User-Agent": "vira-crm-resolver"})
    with urllib.request.urlopen(req, timeout=120) as r:
        return r.read()


def parse_version(tag: str):
    m = re.match(r"v?(\d+)(?:\.(\d+))?(?:\.(\d+))?", tag)
    if not m:
        return None
    return tuple(int(x) if x is not None else 0 for x in m.groups())


def satisfies(version, constraint: str) -> bool:
    """Deliberately small Composer constraint evaluator (>=, <, ^, ~, =, ||)."""
    if not constraint or constraint.strip() in ("*", "@dev"):
        return True
    for alternative in re.split(r"\s*\|\|?\s*", constraint.strip()):
        alternative = alternative.strip()
        if not alternative or alternative in ("*", "@dev"):
            return True
        m = re.match(r"^(>=|<=|!=|>|<|\^|~|=)?\s*v?([\d.]+)", alternative)
        if not m:
            continue
        op, base = m.group(1) or "=", m.group(2)
        bt = parse_version(base)
        if not bt:
            continue
        if op == "^":
            if version >= bt and version[0] == bt[0]:
                return True
        elif op == "~":
            if version >= bt and version[:2] == bt[:2]:
                return True
        elif op == ">=" and version >= bt:
            return True
        elif op == "<=" and version <= bt:
            return True
        elif op == ">" and version > bt:
            return True
        elif op == "<" and version < bt:
            return True
        elif op == "=" and version[: len(bt)] == bt:
            return True
    return False


def find_repo(name: str):
    candidates = []
    if name in KNOWN_REPOS:
        candidates.append(KNOWN_REPOS[name])
    candidates.append(name)
    candidates.append("thephpleague/" + name.split("/")[-1])
    candidates.append("guzzle/" + name.split("/")[-1])
    for cand in candidates:
        try:
            data = gh_json(f"https://api.github.com/repos/{cand}")
            if data.get("full_name"):
                return data["full_name"]
        except Exception:
            continue
    return None


def fetch_tags(repo: str, pages: int = 4):
    tags = []
    for page in range(1, pages + 1):
        try:
            batch = gh_json(f"https://api.github.com/repos/{repo}/tags?per_page=100&page={page}")
        except Exception:
            break
        if not batch:
            break
        tags.extend(batch)
    return tags


def safe_name(name: str) -> str:
    return name.replace("/", "--")


def ensure_package(name: str, constraint: str) -> dict:
    """Download one package into vendor/. Returns metadata dict."""
    key = safe_name(name)
    dest = os.path.join(VENDOR, key)
    meta_path = os.path.join(CACHE, key + ".json")

    if os.path.isdir(dest) and os.path.exists(meta_path):
        return json.load(open(meta_path))

    repo = find_repo(name)
    if not repo:
        return {"name": name, "error": "repository not found"}

    tags = fetch_tags(repo)
    candidates = []
    for t in tags:
        tag = t["name"]
        if any(x in tag.upper() for x in ("BETA", "RC", "ALPHA", "DEV")):
            continue
        v = parse_version(tag)
        if v and satisfies(v, constraint):
            candidates.append((v, tag))
    if not candidates:
        return {"name": name, "repo": repo, "error": f"no tag satisfies {constraint}"}

    candidates.sort(reverse=True)
    version, tag = candidates[0]

    try:
        raw = gh_bytes(f"https://codeload.github.com/{repo}/tar.gz/refs/tags/{tag}")
    except Exception as exc:
        return {"name": name, "repo": repo, "error": f"download failed: {exc}"}

    os.makedirs(dest, exist_ok=True)
    with tarfile.open(fileobj=io.BytesIO(raw), mode="r:gz") as tf:
        for member in tf.getmembers():
            if "/" not in member.name:
                continue
            member.name = member.name.split("/", 1)[1]
            if member.name:
                tf.extract(member, dest)

    composer_json = os.path.join(dest, "composer.json")
    deps, replaces = {}, {}
    if os.path.exists(composer_json):
        try:
            cj = json.load(open(composer_json))
        except Exception:
            cj = {}
        deps = cj.get("require", {}) or {}
        replaces = {k: "*" for k in (cj.get("replace") or {})}

    meta = {
        "name": name,
        "repo": repo,
        "tag": tag,
        "version": ".".join(map(str, version)),
        "deps": deps,
        "replaces": replaces,
    }
    os.makedirs(CACHE, exist_ok=True)
    json.dump(meta, open(meta_path, "w"))
    return meta


def build_autoload() -> str:
    namespaces: dict[str, list[str]] = {}
    file_includes: list[str] = []

    for entry in sorted(os.listdir(VENDOR)):
        pkg_dir = os.path.join(VENDOR, entry)
        if not os.path.isdir(pkg_dir):
            continue
        composer_json = os.path.join(pkg_dir, "composer.json")
        if not os.path.exists(composer_json):
            continue
        try:
            meta = json.load(open(composer_json))
        except Exception:
            continue
        autoload = meta.get("autoload") or {}

        psr4 = autoload.get("psr-4") or {}
        if isinstance(psr4, str):
            psr4 = {"": psr4}
        for ns, paths in psr4.items():
            if isinstance(paths, str):
                paths = [paths]
            for sub in paths:
                namespaces.setdefault(ns, []).append(os.path.normpath(os.path.join(pkg_dir, sub)))

        for f in autoload.get("files") or []:
            file_includes.append(os.path.normpath(os.path.join(pkg_dir, f)))

    lines = [
        "<?php",
        "// VIRA CRM — generated PSR-4 autoloader (development fallback when Composer is unavailable).",
        "// Regenerate with: python3 tools/resolve-vendor.py",
        "spl_autoload_register(function ($class) {",
        "    static $map = array(",
    ]
    for ns, dirs in namespaces.items():
        joined = ", ".join(json.dumps(d) for d in dirs)
        lines.append(f"        {json.dumps(ns)} => array({joined}),")
    lines += [
        "    );",
        "    $prefixes = array_keys($map);",
        "    usort($prefixes, function ($a, $b) { return strlen($b) - strlen($a); });",
        "    $pos = strrpos($class, '\\\\');",
        "    $short = ($pos === false) ? $class : substr($class, $pos + 1);",
        "    foreach ($prefixes as $prefix) {",
        "        if ($prefix !== '' && strncmp($class, $prefix, strlen($prefix)) !== 0) continue;",
        "        $rel = str_replace('\\\\', DIRECTORY_SEPARATOR, substr($class, strlen($prefix)));",
        "        foreach ($map[$prefix] as $dir) {",
        "            $f = $dir . DIRECTORY_SEPARATOR . $rel . '.php';",
        "            if (is_file($f)) { require $f; return true; }",
        "            // Laravel splits some traits into sibling dirs; fall back by basename.",
        "            $g = $dir . DIRECTORY_SEPARATOR . $short . '.php';",
        "            if (is_file($g)) { require $g; return true; }",
        "        }",
        "    }",
        "    foreach ($map as $dirs) {",
        "        foreach ($dirs as $dir) {",
        "            $g = $dir . DIRECTORY_SEPARATOR . $short . '.php';",
        "            if (is_file($g)) { require $g; return true; }",
        "        }",
        "    }",
        "    return false;",
        "});",
        "",
    ]
    for f in file_includes:
        lines.append(f"if (is_file({json.dumps(f)})) require_once {json.dumps(f)};")

    lines += [
        "",
        "// ── Application namespaces ──────────────────────────────────────────────",
        "spl_autoload_register(function ($class) {",
        "    $root = dirname(__DIR__);",
        "    $map = array(",
        '        "App\\\\" => $root . "/app",',
        '        "Database\\\\Seeders\\\\" => $root . "/database/seeders",',
        '        "Database\\\\Factories\\\\" => $root . "/database/factories",',
        "    );",
        "    $prefixes = array_keys($map);",
        "    usort($prefixes, function ($a, $b) { return strlen($b) - strlen($a); });",
        "    foreach ($prefixes as $prefix) {",
        "        if (strncmp($class, $prefix, strlen($prefix)) !== 0) continue;",
        "        $rel = str_replace('\\\\', DIRECTORY_SEPARATOR, substr($class, strlen($prefix)));",
        "        $f = $map[$prefix] . DIRECTORY_SEPARATOR . $rel . '.php';",
        "        if (is_file($f)) { require $f; return true; }",
        "        return false;",
        "    }",
        "    return false;",
        "});",
        "",
    ]

    out_path = os.path.join(VENDOR, "autoload.php")
    with open(out_path, "w", encoding="utf-8", newline="\n") as fh:
        fh.write("\n".join(lines))
    return out_path


def verify() -> int:
    problems = []
    if not os.path.isdir(VENDOR):
        print("FAIL: vendor/ does not exist")
        return 1
    autoload = os.path.join(VENDOR, "autoload.php")
    if not os.path.exists(autoload):
        problems.append("vendor/autoload.php missing")
    needed = [
        "laravel--framework",
        "symfony--http-kernel",
        "symfony--routing",
        "nesbot--carbon",
        "brick--math",
        "psr--log",
    ]
    for pkg in needed:
        if not os.path.isdir(os.path.join(VENDOR, pkg)):
            problems.append(f"missing package: {pkg}")

    if problems:
        for p in problems:
            print("FAIL:", p)
        return 1
    pkgs = [d for d in os.listdir(VENDOR) if os.path.isdir(os.path.join(VENDOR, d))]
    print(f"OK: vendor/ has {len(pkgs)} packages and an autoloader")
    return 0


def main() -> int:
    parser = argparse.ArgumentParser()
    parser.add_argument("--verify", action="store_true")
    args = parser.parse_args()

    if args.verify:
        return verify()

    composer = json.load(open(os.path.join(ROOT, "composer.json")))
    requires = dict(composer.get("require") or {})
    for extra_name, extra_constraint in EXTRA_REQUIREMENTS:
        requires.setdefault(extra_name, extra_constraint)

    os.makedirs(VENDOR, exist_ok=True)
    os.makedirs(CACHE, exist_ok=True)

    resolved: dict[str, dict] = {}
    replaced_by: dict[str, str] = {}
    queue = [(n, c) for n, c in requires.items()]
    failures = []

    while queue:
        name, constraint = queue.pop(0)
        if name in resolved:
            continue
        if name in replaced_by:
            log(f"  skip  {name:45s} (replaced by {replaced_by[name]})")
            continue
        if name == "php" or name.startswith("ext-") or name in ("composer-runtime-api", "composer-plugin-api"):
            continue
        try:
            meta = ensure_package(name, constraint)
        except Exception as exc:  # pragma: no cover - defensive
            meta = {"name": name, "error": str(exc)}
        resolved[name] = meta
        if "error" in meta:
            failures.append(f"{name}: {meta['error']}")
            log(f"  FAIL  {name:45s} {meta['error']}")
            continue
        log(f"  ok    {name:45s} {meta['tag']}")
        # laravel/framework "replaces" every illuminate/* package; pulling the split
        # packages as well produces two copies of the same classes at different versions.
        for replaced in (meta.get("replaces") or {}):
            replaced_by.setdefault(replaced, name)
        for dep, dep_constraint in (meta.get("deps") or {}).items():
            if dep == "php" or dep.startswith("ext-") or dep in resolved:
                continue
            queue.append((dep, dep_constraint))

    autoload = build_autoload()
    ok_count = sum(1 for m in resolved.values() if "error" not in m)
    log("")
    log(f"resolved {ok_count}/{len(resolved)} packages")
    log(f"autoload written: {os.path.relpath(autoload, ROOT)}")

    if failures:
        log("")
        log("unresolved packages (investigate before trusting the vendor tree):")
        for f in failures:
            log("  - " + f)
        return 2
    return 0


if __name__ == "__main__":
    sys.exit(main())
