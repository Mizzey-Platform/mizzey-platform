"""Run the product-cost integration scenarios against the disposable runtime.

Usage: python mizzey-site/tests/integration/run.py --wp ../app/wp [--only t05]

Each t*.php scenario runs in its own `wp eval-file` process and prints one JSON verdict line. The runner prints a
table and exits 1 if any contract scenario fails (pass is false). A scenario with pass null is fact-finding only.
Never point --wp at production.
"""

from __future__ import annotations

import argparse
import json
import shutil
import subprocess
import sys
from pathlib import Path

HERE = Path(__file__).resolve().parent


def run_scenario(wp: str, path: Path, script: Path) -> dict:
    proc = subprocess.run([wp, f"--path={path}", "--skip-themes", "eval-file", str(script)], capture_output=True,
                          text=True, encoding="utf-8")
    for line in reversed(proc.stdout.splitlines()):
        line = line.strip()
        if line.startswith("{") and '"test"' in line:
            return json.loads(line)
    return {"test": script.stem, "criterion": "?", "pass": False,
            "observed": [f"no verdict (exit {proc.returncode})", proc.stderr.strip()[-600:], proc.stdout.strip()[-600:]]}


def main(argv: list[str] | None = None) -> int:
    ap = argparse.ArgumentParser(description=__doc__.splitlines()[0])
    ap.add_argument("--wp", required=True, type=Path, help="WordPress path of the disposable runtime")
    ap.add_argument("--only", help="run only scenarios whose file name starts with this")
    ap.add_argument("--json", type=Path, help="also write all verdicts to this file")
    a = ap.parse_args(argv)
    if hasattr(sys.stdout, "reconfigure"):
        sys.stdout.reconfigure(encoding="utf-8", errors="replace")
    wp = shutil.which("wp") or "wp"
    scripts = sorted(p for p in HERE.glob("t*.php") if not a.only or p.name.startswith(a.only))
    results = [run_scenario(wp, a.wp.resolve(), s) for s in scripts]
    failed = 0
    for r in results:
        state = {True: "PASS", False: "FAIL", None: "FACT"}[r["pass"]]
        failed += r["pass"] is False
        print(f"{state}  {r['test']:<28} {r['criterion']}")
        for line in r.get("observed", []):
            print(f"      {line}")
    if results and results[0].get("versions"):
        print("versions:", json.dumps(results[0]["versions"]))
    if a.json:
        a.json.write_text(json.dumps(results, indent=1, ensure_ascii=False) + "\n", encoding="utf-8")
    print(f"{len(results)} scenarios, {failed} failed")
    return 1 if failed else 0


if __name__ == "__main__":
    sys.exit(main())
