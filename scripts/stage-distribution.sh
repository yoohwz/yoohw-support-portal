#!/usr/bin/env bash
set -euo pipefail

SOURCE="${1:-}"
DESTINATION="${2:-}"

if [[ -z "$SOURCE" || -z "$DESTINATION" ]]; then
  echo "usage: bash scripts/stage-distribution.sh <source> <destination>" >&2
  exit 2
fi

SOURCE="$(cd "$SOURCE" && pwd -P)"
mkdir -p "$DESTINATION"
DESTINATION="$(cd "$DESTINATION" && pwd -P)"

if [[ ! -f "$SOURCE/yoohw-support-portal.php" || ! -f "$SOURCE/.distignore" ]]; then
  echo "source is not a YoOhw Support Portal repository root" >&2
  exit 1
fi

case "$DESTINATION/" in
  "$SOURCE/"|"$SOURCE/"*)
    echo "destination must be outside the source tree" >&2
    exit 1
    ;;
esac

rm -rf "$DESTINATION"/* "$DESTINATION"/.[!.]* "$DESTINATION"/..?* 2>/dev/null || true

rsync -a \
  --exclude-from="$SOURCE/.distignore" \
  "$SOURCE/" "$DESTINATION/"

for forbidden in .git .github tests docs scripts AGENTS.md .distignore composer.json composer.lock phpunit.xml phpunit.xml.dist; do
  if [[ -e "$DESTINATION/$forbidden" ]]; then
    echo "forbidden development artifact in distribution: $forbidden" >&2
    exit 1
  fi
done

if find "$DESTINATION" -type l -print -quit | grep -q .; then
  echo "distribution must not contain symbolic links" >&2
  exit 1
fi

if find "$DESTINATION" -type f -name '*.zip' -print -quit | grep -q .; then
  echo "distribution must not contain nested ZIP archives" >&2
  exit 1
fi

for required in yoohw-support-portal.php readme.txt inc templates assets; do
  if [[ ! -e "$DESTINATION/$required" ]]; then
    echo "distribution is missing required path: $required" >&2
    exit 1
  fi
done

printf 'distribution-ok files=%s\n' "$(find "$DESTINATION" -type f | wc -l | tr -d ' ')"
