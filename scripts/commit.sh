#!/usr/bin/env bash
# Initial import: one back-dated commit per announcement, in publication order.
# Reads scripts/.commit-plan (fields separated by 0x1f):
#   post_date_gmt <US> id <US> rel_md <US> rel_json <US> message
set -euo pipefail
cd "$(dirname "$0")/.."

US=$'\x1f'
total=$(wc -l < scripts/.commit-plan)
i=0

while IFS="$US" read -r gmt id relmd reljs msg; do
  [ -z "${id:-}" ] && continue
  i=$((i+1))
  git add -- "$relmd" "$reljs"
  GIT_AUTHOR_DATE="$gmt +0000" \
  GIT_COMMITTER_DATE="$gmt +0000" \
  git -c user.name="CFI.co Awards Archive" \
      -c user.email="awards-archive@cfi.co" \
      commit -q --no-verify -m "$msg" -- "$relmd" "$reljs"
  if [ $((i % 250)) -eq 0 ] || [ "$i" -eq "$total" ]; then
    echo "  committed $i / $total"
  fi
done < scripts/.commit-plan

echo "Done: $i individual announcement commits created."
