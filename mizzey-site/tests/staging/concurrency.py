"""Parallel requests against staging: the capability workstream item B6 was waiting for.

    python mizzey-site/tests/staging/concurrency.py last-units --buyers 12 --stock 3 [--languages en,ar]
    python mizzey-site/tests/staging/concurrency.py read-load --clients 16 --requests 160

`last-units` puts N buyers at the checkout for the last K units of one item and releases every order request at
the same instant. One PHP process cannot create simultaneity, which is why this could not be measured before
staging existed. Each buyer has its own cart, as a real visitor does, through the store's own public cart and
checkout interface. With `--languages en,ar` half the buyers order the English record and half the Arabic record
of the same physical item, which is the case B6 names.

`read-load` reads a set of storefront pages from many clients at once and reports the response times.

This file measures. It prints what happened and decides nothing: an oversell it finds is a finding for the
checkout PBI (#255) and for BR-003, not something corrected here. It refuses any address but the staging one,
and uses the standard library only.
"""

from __future__ import annotations

import argparse
import http.client
import json
import statistics
import subprocess
import sys
import threading
import time
from pathlib import Path

sys.path.insert(0, str(Path(__file__).resolve().parent))
import staging  # noqa: E402  the guards, the paths and the wp runner

HOST, PORT = "127.0.0.1", staging.PORT
ITEM_SKU = "DEMO-LOAD-001"


def call(method: str, path: str, body: dict | None = None, headers: dict | None = None) -> tuple[int, dict, object, float]:
    conn = http.client.HTTPConnection(HOST, PORT, timeout=180)
    payload = json.dumps(body).encode("utf-8") if body is not None else None
    send = {"Accept": "application/json", **(headers or {})}
    if payload:
        send["Content-Type"] = "application/json"
    started = time.perf_counter()
    try:
        conn.request(method, path, body=payload, headers=send)
        r = conn.getresponse()
        raw = r.read()
        took = time.perf_counter() - started
        try:
            data = json.loads(raw.decode("utf-8"))
        except ValueError:
            data = raw[:200].decode("utf-8", "replace")
        return r.status, {k.lower(): v for k, v in r.getheaders()}, data, took
    finally:
        conn.close()


def item_ids() -> dict:
    # One line: the WP-CLI launcher on Windows is a batch file, and a line break ends its argument.
    out = staging.wp("eval", f"$en = (int) apply_filters('wpml_object_id', (int) wc_get_product_id_by_sku('{ITEM_SKU}'), "
                             "'product', true, 'en'); echo wp_json_encode(array('en' => $en, 'ar' => (int) "
                             "apply_filters('wpml_object_id', $en, 'product', false, 'ar')));")
    return json.loads(out.splitlines()[-1])


def set_stock(product_id: int, quantity: int) -> None:
    staging.wp("eval", f"wc_update_product_stock({product_id}, {quantity}, 'set'); wc_delete_product_transients({product_id});")


def stock_of(ids: dict) -> dict:
    out = staging.wp("eval", "echo wp_json_encode(array_map(fn($id) => $id ? wc_get_product($id)->get_stock_quantity() : null, "
                             f"array('en' => {ids['en']}, 'ar' => {ids['ar'] or 0})));")
    return json.loads(out.splitlines()[-1])


def address(n: int) -> dict:
    return {"first_name": "Load", "last_name": f"Buyer {n:02d}", "address_1": f"{n} Sample Street", "city": "Cairo",
            "state": "C", "postcode": "11511", "country": "EG", "email": f"load-buyer-{n:02d}@example.invalid",
            "phone": f"0100000{n:04d}"}


def cmd_last_units(a) -> int:
    staging.guard_destructive()
    languages = a.languages.split(",")
    ids = item_ids()
    if not ids["en"] or ("ar" in languages and not ids["ar"]):
        raise SystemExit(f"the staging seed has no {ITEM_SKU} in the languages asked for: {ids}")
    set_stock(ids["en"], a.stock)
    before = stock_of(ids)

    # Phase 1, not simultaneous: each buyer opens a cart and puts one unit in it. Every one of these succeeds
    # while stock remains, because a cart reserves nothing.
    buyers, prepare_errors = [], []
    for n in range(1, a.buyers + 1):
        lang = languages[(n - 1) % len(languages)]
        prefix = "" if lang == "en" else f"/{lang}"
        status, headers, _, _ = call("GET", f"{prefix}/wp-json/wc/store/v1/cart")
        token, nonce = headers.get("cart-token"), headers.get("nonce")
        auth = {"Cart-Token": token or "", "Nonce": nonce or ""}
        status, _, data, _ = call("POST", f"{prefix}/wp-json/wc/store/v1/cart/add-item",
                                  {"id": ids[lang], "quantity": 1}, auth)
        if status not in (200, 201):
            prepare_errors.append({"buyer": n, "lang": lang, "status": status,
                                   "message": data.get("message") if isinstance(data, dict) else data})
            continue
        buyers.append({"n": n, "lang": lang, "prefix": prefix, "auth": auth})

    # Phase 2, simultaneous: every order request is released by one barrier.
    barrier = threading.Barrier(len(buyers)) if buyers else None
    results: list[dict] = []
    lock = threading.Lock()

    def order(buyer: dict) -> None:
        body = {"billing_address": address(buyer["n"]), "shipping_address": address(buyer["n"]),
                "payment_method": a.payment}
        barrier.wait()
        fired = time.perf_counter()
        try:
            status, _, data, took = call("POST", f"{buyer['prefix']}/wp-json/wc/store/v1/checkout", body, buyer["auth"])
            row = {"buyer": buyer["n"], "lang": buyer["lang"], "status": status, "seconds": round(took, 2),
                   "fired": fired,
                   "order_id": data.get("order_id") if isinstance(data, dict) else None,
                   "order_status": data.get("status") if isinstance(data, dict) else None,
                   "message": (data.get("message") if isinstance(data, dict) else str(data))}
        except OSError as e:
            row = {"buyer": buyer["n"], "lang": buyer["lang"], "status": "no answer", "message": str(e), "fired": fired}
        with lock:
            results.append(row)

    threads = [threading.Thread(target=order, args=(b,)) for b in buyers]
    for t in threads:
        t.start()
    for t in threads:
        t.join()

    after = stock_of(ids)
    confirmed = [r for r in results if r.get("status") == 200 and r.get("order_id")]
    fired = [r["fired"] for r in results]
    report = {
        "measured": staging.now(), "what": "N buyers ordering the last K units at the same instant",
        "item": ITEM_SKU, "records": ids, "languages": languages, "payment_method": a.payment,
        "buyers": a.buyers, "buyers_with_a_cart": len(buyers), "stock_before": before, "stock_after": after,
        "release_spread_ms": round((max(fired) - min(fired)) * 1000, 2) if fired else None,
        "orders_confirmed": len(confirmed), "orders_refused": len(results) - len(confirmed),
        "units_oversold": max(0, len(confirmed) - a.stock),
        "confirmed_by_language": {l: sum(1 for r in confirmed if r["lang"] == l) for l in languages},
        "language_records_agree_after": len({v for v in after.values() if v is not None}) <= 1,
        "refusal_messages": sorted({str(r.get("message"))[:160] for r in results if r not in confirmed}),
        "cart_errors": prepare_errors,
        "rows": sorted(({k: v for k, v in r.items() if k != "fired"} for r in results), key=lambda r: r["buyer"]),
    }
    return finish(report, a, f"last-units-{'-'.join(languages)}")


def cmd_read_load(a) -> int:
    staging.guard_tree()
    paths = ["/", "/ar/", "/shop/", "/ar/shop/", "/product/sample-product-01/", "/ar/product/sample-product-01/",
             "/cart/", "/my-account/"]
    jobs = [paths[i % len(paths)] for i in range(a.requests)]
    times: dict[str, list[float]] = {p: [] for p in paths}
    statuses: dict[str, int] = {}
    lock, cursor = threading.Lock(), iter(jobs)

    def client() -> None:
        while True:
            with lock:
                path = next(cursor, None)
            if path is None:
                return
            try:
                status, _, _, took = call("GET", path, headers={"Accept": "text/html"})
            except OSError:
                status, took = "no answer", 0.0
            with lock:
                times[path].append(took)
                statuses[str(status)] = statuses.get(str(status), 0) + 1

    started = time.perf_counter()
    threads = [threading.Thread(target=client) for _ in range(a.clients)]
    for t in threads:
        t.start()
    for t in threads:
        t.join()
    wall = time.perf_counter() - started
    everything = sorted(t for ts in times.values() for t in ts)

    def pct(values: list[float], q: float) -> float:
        return round(values[min(len(values) - 1, int(len(values) * q))], 3) if values else 0.0

    report = {"measured": staging.now(), "what": "storefront pages read by many clients at once",
              "clients": a.clients, "requests": a.requests, "wall_seconds": round(wall, 2),
              "requests_per_second": round(a.requests / wall, 2), "statuses": statuses,
              "seconds": {"median": round(statistics.median(everything), 3), "p95": pct(everything, 0.95),
                          "max": round(everything[-1], 3)},
              "by_page_median": {p: round(statistics.median(ts), 3) for p, ts in times.items() if ts},
              "note": "Measured on the developer's machine, with debugging on and no page cache. It shows that the "
                      "harness produces load and that staging holds it. It is not a production performance figure."}
    return finish(report, a, "read-load")


def finish(report: dict, a, name: str) -> int:
    text = json.dumps(report, indent=1, ensure_ascii=False)
    print(text)
    out = staging.STAGING / "evidence" / f"concurrency-{name}-{time.strftime('%Y%m%d-%H%M%S')}.json"
    out.write_text(text + "\n", encoding="utf-8")
    print(f"evidence: {out}", file=sys.stderr)
    return 0


def main(argv: list[str] | None = None) -> int:
    ap = argparse.ArgumentParser(description=__doc__.splitlines()[0])
    sub = ap.add_subparsers(dest="command", required=True)
    lu = sub.add_parser("last-units")
    lu.add_argument("--buyers", type=int, default=12)
    lu.add_argument("--stock", type=int, default=3)
    lu.add_argument("--languages", default="en")
    lu.add_argument("--payment", default="cod")
    rl = sub.add_parser("read-load")
    rl.add_argument("--clients", type=int, default=16)
    rl.add_argument("--requests", type=int, default=160)
    a = ap.parse_args(argv)
    if hasattr(sys.stdout, "reconfigure"):
        sys.stdout.reconfigure(encoding="utf-8", errors="replace")
    return {"last-units": cmd_last_units, "read-load": cmd_read_load}[a.command](a)


if __name__ == "__main__":
    sys.exit(main())
