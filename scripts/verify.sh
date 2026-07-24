#!/usr/bin/env bash
# Independent re-verification of the CFI.co Awards transparency archive.
# Recomputes content_sha256 + record_sha256 for every announcement and checks
# them against MANIFEST.sha256. Exit 0 = all good; non-zero = mismatch found.
set -euo pipefail
cd "$(dirname "$0")/.."

fail=0 n=0

# 1. Per-record hash check (uses php for exact JSON canonicalisation).
while IFS= read -r -d '' j; do
  n=$((n+1))
  php -r '
    $f=$argv[1]; $r=json_decode(file_get_contents($f),true);
    $want_c=$r["content_sha256"]; $want_r=$r["record_sha256"];
    if (hash("sha256",$r["content_html"])!==$want_c){fwrite(STDERR,"content_sha256 MISMATCH: $f\n");exit(1);}
    unset($r["record_sha256"]);
    $j=json_encode($r,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE);
    if (hash("sha256",$j)!==$want_r){fwrite(STDERR,"record_sha256 MISMATCH: $f\n");exit(1);}
  ' "$j" || fail=1
done < <(find announcements -name '*.json' -print0)

# 2. Whole-tree manifest check: coverage AND content. Coverage first so a
#    truncated/stale manifest fails loudly instead of silently "passing".
if [ ! -f MANIFEST.sha256 ]; then
  echo "MANIFEST.sha256 missing" >&2; fail=1
else
  expected="$(git ls-files | grep -vxF -e 'MANIFEST.sha256' -e 'MANIFEST.sha256.asc' | sort)"
  listed="$(cut -c67- MANIFEST.sha256 | sort)"
  if [ "$expected" != "$listed" ]; then
    echo "MANIFEST coverage mismatch — manifest does not list exactly the tracked files (truncated/stale?)" >&2
    diff <(printf '%s\n' "$expected") <(printf '%s\n' "$listed") | head >&2
    fail=1
  fi
  sha256sum -c --quiet MANIFEST.sha256 || fail=1
fi

# 3. Manifest signature (detached; the signer key ships in-tree as SIGNING-KEY.asc).
#    Skipped only where gpg is unavailable; a present-but-invalid signature fails.
if [ -f MANIFEST.sha256.asc ] && command -v gpg >/dev/null 2>&1; then
  gpg -q --import SIGNING-KEY.asc 2>/dev/null || true
  if gpg --verify MANIFEST.sha256.asc MANIFEST.sha256 2>/dev/null; then
    echo "manifest signature OK"
  else
    echo "manifest signature INVALID" >&2; fail=1
  fi
fi

if [ "$fail" -eq 0 ]; then
  echo "OK — $n announcement records verified, manifest intact."
else
  echo "VERIFICATION FAILED — see messages above." >&2
fi
exit $fail
