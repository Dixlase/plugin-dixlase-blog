#!/usr/bin/env php
<?php

/**
 * This file is part of Dixlase Blog.
 *
 * Copyright (C) 2026 exc-D inc.
 * https://exc-d.com
 *
 * Dixlase Blog is dual-licensed. You may use this file under either:
 *
 *   (a) the GNU General Public License version 3 or later, as published
 *       by the Free Software Foundation; or
 *
 *   (b) a commercial license agreement obtained from exc-D inc.
 *
 * Unless you have entered into a commercial license agreement, this
 * file is governed by the GPL terms below.
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program. If not, see <https://www.gnu.org/licenses/>.
 */

declare(strict_types=1);

/**
 * Bump this extension's version in every file that records it.
 *
 * The manifest (plugin.json, or theme.json for a theme) is authoritative:
 * Dixlase core reads it first and only falls back to composer.json. The
 * composer.json copy (top-level "version" for a plugin, extra.dixlase.version
 * for a theme) is easy to forget and then drifts, so this script updates both
 * from one command — and package.json too, when the extension has one.
 * VersionManifestConsistencyTest fails CI if the manifest and composer.json
 * ever disagree.
 *
 * Each file is edited by replacing a single "version" value in place, so the
 * formatting and unescaped unicode are preserved. The edited text is decoded
 * and compared with the expected structure before anything is written, and
 * nothing is written unless every file can be updated.
 *
 * Usage:
 *   php scripts/bump-version.php <x.y.z>
 *   php scripts/bump-version.php 0.2.0
 */
$root = dirname(__DIR__);

$new = $argv[1] ?? '';

// Accept semver plus pre-release / build suffixes (e.g. 0.2.0-beta.1).
if (! preg_match('/^\d+\.\d+\.\d+(?:[-+][0-9A-Za-z.-]+)?$/', $new)) {
    fwrite(STDERR, "Usage: php scripts/bump-version.php <x.y.z>\n");
    fwrite(STDERR, "  version must be semver-ish, e.g. 0.2.0 or 0.2.0-beta.1\n");
    exit(1);
}

$isTheme = ! is_file($root.'/plugin.json') && is_file($root.'/theme.json');
$manifestFile = $isTheme ? 'theme.json' : 'plugin.json';

// File => key path of the "version" value to update.
$targets = [
    $manifestFile => ['version'],
    'composer.json' => $isTheme ? ['extra', 'dixlase', 'version'] : ['version'],
];

if (is_file($root.'/package.json')) {
    $targets['package.json'] = ['version'];
}

$updated = [];

try {
    foreach ($targets as $file => $keyPath) {
        $updated[$file] = bumpJsonVersion($root.'/'.$file, $keyPath, $new);
    }
} catch (RuntimeException $e) {
    fwrite(STDERR, $e->getMessage()."\n");
    fwrite(STDERR, "No files were changed.\n");
    exit(1);
}

foreach ($updated as $file => $contents) {
    if (file_put_contents($root.'/'.$file, $contents) === false) {
        fwrite(STDERR, "Failed to write {$file}\n");
        exit(1);
    }
}

fwrite(STDOUT, "Bumped version to {$new}:\n");
foreach ($targets as $file => $keyPath) {
    fwrite(STDOUT, '  '.$file.' ('.implode('.', $keyPath).")\n");
}

// The signature covers composer.json / package.json and the manifest's own
// version, so a signed extension must be re-signed after a bump.
$manifest = json_decode($updated[$manifestFile], true);
if (is_array($manifest) && array_key_exists('signing', $manifest)) {
    fwrite(STDOUT, "\n{$manifestFile} carries a signature: re-sign the extension before releasing it.\n");
}

/**
 * Return the file's contents with the value at $keyPath replaced by $new.
 *
 * @param  list<string>  $keyPath
 *
 * @throws RuntimeException when the file is unreadable, is not a JSON object,
 *                          lacks the key, or cannot be edited in place
 */
function bumpJsonVersion(string $path, array $keyPath, string $new): string
{
    $file = basename($path);
    $key = implode('.', $keyPath);

    $json = is_file($path) ? file_get_contents($path) : false;
    if ($json === false) {
        throw new RuntimeException("Failed to read {$file}");
    }

    $decoded = json_decode($json, true);
    if (! is_array($decoded)) {
        throw new RuntimeException("{$file} is not a valid JSON object");
    }

    $expected = $decoded;
    $cursor = &$expected;
    foreach ($keyPath as $segment) {
        if (! is_array($cursor) || ! array_key_exists($segment, $cursor)) {
            throw new RuntimeException("{$file} has no \"{$key}\" — add it before bumping");
        }
        $cursor = &$cursor[$segment];
    }
    $cursor = $new;
    unset($cursor);

    // Try each "version" occurrence in turn and keep the edit whose decoded
    // result is exactly the expected structure. This skips nested "version"
    // keys that are not the target without reformatting the file.
    preg_match_all('/("version"\s*:\s*")[^"]*(")/', $json, $matches, PREG_OFFSET_CAPTURE);

    foreach ($matches[0] as $i => [$whole, $offset]) {
        $replacement = $matches[1][$i][0].$new.$matches[2][$i][0];
        $candidate = substr_replace($json, $replacement, $offset, strlen($whole));

        if (json_decode($candidate, true) === $expected) {
            return $candidate;
        }
    }

    throw new RuntimeException("Failed to update \"{$key}\" in {$file} in place");
}
