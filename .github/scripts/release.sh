#!/usr/bin/env bash
# Release helper for mod_buchbinder.
#
#   release.sh bump <x.y.z> [MATURITY_...]  update version.php and CHANGES.md
#   release.sh check <tag>                  verify that tag, version.php and CHANGES.md match
#   release.sh field <version|release|maturity|requires>
#   release.sh notes [release]              print the changelog section of a release
#   release.sh build                        create dist/mod_buchbinder_<release>.zip
set -euo pipefail
cd "$(dirname "$0")/../.."

field() {
    php -r '
        define("MOODLE_INTERNAL", 1);
        foreach (["MATURITY_ALPHA" => 50, "MATURITY_BETA" => 100, "MATURITY_RC" => 150, "MATURITY_STABLE" => 200] as $k => $v) {
            define($k, $v);
        }
        $plugin = new stdClass();
        include "version.php";
        $f = $argv[1];
        if ($f === "maturity") {
            echo array_search($plugin->maturity, ["MATURITY_ALPHA" => 50, "MATURITY_BETA" => 100,
                "MATURITY_RC" => 150, "MATURITY_STABLE" => 200]);
        } else {
            echo $plugin->$f;
        }' "$1"
}

notes() {
    local release="${1:-$(field release)}"
    awk -v r="$release" '
        /^## / { if (found) exit; if (index($0, "[" r "]")) { found = 1; next } }
        found { print }
    ' CHANGES.md | sed -e '/./,$!d'
}

case "${1:-}" in
    field)
        field "$2"
        ;;

    notes)
        notes "${2:-}"
        ;;

    bump)
        release="${2:?release number required, e.g. 0.4.0}"
        maturity="${3:-$(field maturity)}"
        [[ "$release" =~ ^[0-9]+\.[0-9]+\.[0-9]+$ ]] || { echo "Invalid release number: $release" >&2; exit 1; }
        [[ "$maturity" =~ ^MATURITY_(ALPHA|BETA|RC|STABLE)$ ]] || { echo "Invalid maturity: $maturity" >&2; exit 1; }
        if git rev-parse -q --verify "refs/tags/v$release" >/dev/null; then
            echo "Tag v$release exists already" >&2
            exit 1
        fi
        # Moodle version numbers are YYYYMMDDXX and must always increase.
        current=$(field version)
        today="$(date -u +%Y%m%d)00"
        new=$(( current >= today ? current + 1 : today ))
        sed -i -E "s/^(\\\$plugin->version\s*=\s*)[0-9]+;/\1$new;/" version.php
        sed -i -E "s/^(\\\$plugin->release\s*=\s*)'[^']*';/\1'$release';/" version.php
        sed -i -E "s/^(\\\$plugin->maturity\s*=\s*)MATURITY_[A-Z]+;/\1$maturity;/" version.php
        if ! grep -q '^## \[Unreleased\]' CHANGES.md; then
            echo "CHANGES.md has no '## [Unreleased]' section" >&2
            exit 1
        fi
        if [ -z "$(notes Unreleased | tr -d '[:space:]')" ]; then
            echo "The '## [Unreleased]' section of CHANGES.md is empty" >&2
            exit 1
        fi
        sed -i "s/^## \[Unreleased\]/## [Unreleased]\n\n## [$release] - $(date -u +%Y-%m-%d)/" CHANGES.md
        echo "version.php: version $new, release $release, $maturity"
        ;;

    check)
        tag="${2:?tag required}"
        php -l version.php >/dev/null
        release=$(field release)
        if [ "$tag" != "v$release" ]; then
            echo "::error::Tag $tag does not match release '$release' in version.php (expected v$release)" >&2
            exit 1
        fi
        if [ -z "$(notes "$release" | tr -d '[:space:]')" ]; then
            echo "::error::CHANGES.md has no entries for [$release]" >&2
            exit 1
        fi
        previous=$(git describe --tags --abbrev=0 "$tag^" 2>/dev/null || true)
        if [ -n "$previous" ]; then
            oldversion=$(git show "$previous:version.php" | sed -nE 's/^\$plugin->version\s*=\s*([0-9]+);.*/\1/p')
            if [ "$(field version)" -le "${oldversion:-0}" ]; then
                echo "::error::\$plugin->version must be higher than in $previous ($oldversion)" >&2
                exit 1
            fi
        fi
        echo "OK: $tag, version $(field version), $(field maturity)"
        ;;

    build)
        release=$(field release)
        mkdir -p dist
        zip="dist/mod_buchbinder_${release}.zip"
        rm -f "$zip"
        # Files marked export-ignore in .gitattributes are left out.
        git archive --format=zip --prefix=buchbinder/ -o "$zip" HEAD
        unzip -l "$zip" | grep -q ' buchbinder/version.php$' || { echo "version.php missing in $zip" >&2; exit 1; }
        if unzip -l "$zip" | grep -qE ' buchbinder/\.git'; then
            echo "Development files in $zip" >&2
            exit 1
        fi
        echo "$zip"
        ;;

    *)
        sed -n '2,9p' "$0" | sed 's/^# \{0,1\}//'
        exit 1
        ;;
esac
