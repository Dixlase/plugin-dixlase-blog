#!/usr/bin/env bash
#
# Recompute the SHA-256 of every file the signature manifest covers and compare
# it with the hash recorded there.
#
# No secret is involved. This is a hash comparison, not a signature
# verification: the manifest states what the author signed, and this answers
# whether the tree still matches it. A signature made over a tree that has since
# moved is exactly what an installed site reports as `signature_invalid`, which
# costs the extension 50 health points and blocks it under the Strict preset.
#
# Why it runs here, before the merge: signing happens in one place, from a
# working copy. A pull request merged on GitHub produces no signature, so `main`
# silently drifts and the next deploy carries the drift to production. See
# Dixlase/dixlase-core#520.
#
# Usage: check-manifest.sh [manifest]   (default: plugin.json; a theme passes theme.json)
#
set -euo pipefail

MANIFEST="${1:-plugin.json}"

if [ ! -f "$MANIFEST" ]; then
    echo "::error::$MANIFEST not found"
    exit 1
fi

entries=$(jq -r '(.files // {}) | length' "$MANIFEST")

if [ "$entries" -eq 0 ]; then
    echo "::error::$MANIFEST carries no file list, so the extension is unsigned."
    echo "Sign it from the environment that holds the signing key, then commit the manifest."
    exit 1
fi

changed=0
missing=0

while IFS=$'\t' read -r path want; do
    if [ ! -f "$path" ]; then
        echo "::error file=$MANIFEST::missing  $path"
        missing=$((missing + 1))
        continue
    fi

    got="sha256:$(sha256sum "$path" | cut -d' ' -f1)"

    if [ "$got" != "$want" ]; then
        echo "::error file=$path::changed since signing  $path"
        changed=$((changed + 1))
    fi
done < <(jq -r '.files | to_entries[] | "\(.key)\t\(.value)"' "$MANIFEST")

bad=$((changed + missing))

if [ "$bad" -eq 0 ]; then
    echo "$entries files, all matching the manifest."
    exit 0
fi

echo
echo "$bad of $entries manifest entries no longer match ($changed changed, $missing missing)."
echo
echo "The signature covers these files, so the extension now reports as tampered"
echo "with wherever it is installed. Re-sign from the environment that holds the"
echo "signing key and commit the refreshed manifest on this branch:"
echo
echo "    php artisan dls:signer:sign --force <PluginDirName>"
echo
echo "Signing is never done in CI. A key reachable from a workflow can be used by"
echo "anything with workflow-write access, and the key_id is recorded in every"
echo "manifest ever signed, so a leak invalidates the whole history."
exit 1
