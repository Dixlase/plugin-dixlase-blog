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

namespace Plugins\DixlaseBlog\Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * Guards that the manifest and composer.json never disagree on the version.
 *
 * Dixlase core reads the version from the manifest (plugin.json, or theme.json
 * for a theme) first and falls back to composer.json — top-level "version" for
 * a plugin, extra.dixlase.version for a theme. A stale composer.json copy is
 * confusing and resurfaces whenever the fallback is taken, and it has drifted
 * before. Bump every copy together with `php scripts/bump-version.php <x.y.z>`.
 *
 * package.json is kept in step by that script but is deliberately not checked
 * here: nothing in Dixlase reads its version.
 *
 * Pure file reads: no framework boot, no database.
 */
class VersionManifestConsistencyTest extends TestCase
{
    private const REPO_ROOT = __DIR__.'/../..';

    public function test_manifest_declares_a_semver_version(): void
    {
        $manifest = $this->readJson($this->manifestFile());

        $this->assertArrayHasKey('version', $manifest, $this->manifestFile().' must declare a "version".');
        $this->assertIsString($manifest['version']);
        $this->assertMatchesRegularExpression(
            '/^\d+\.\d+\.\d+(?:[-+][0-9A-Za-z.-]+)?$/',
            $manifest['version'],
            $this->manifestFile().' "version" must be semver-ish (e.g. 0.2.0 or 0.2.0-beta.1).'
        );
    }

    public function test_composer_json_version_matches_the_manifest(): void
    {
        $manifest = $this->readJson($this->manifestFile());
        $composer = $this->readJson('composer.json');

        $key = $this->isTheme() ? 'extra.dixlase.version' : 'version';
        $composerVersion = $this->isTheme()
            ? ($composer['extra']['dixlase']['version'] ?? null)
            : ($composer['version'] ?? null);

        $this->assertNotNull($composerVersion, "composer.json must declare \"{$key}\".");
        $this->assertSame(
            $manifest['version'] ?? null,
            $composerVersion,
            "composer.json \"{$key}\" must match the ".$this->manifestFile().' "version". '
                .'Run `php scripts/bump-version.php <x.y.z>` to bump them together.'
        );
    }

    private function isTheme(): bool
    {
        return ! is_file(self::REPO_ROOT.'/plugin.json') && is_file(self::REPO_ROOT.'/theme.json');
    }

    private function manifestFile(): string
    {
        return $this->isTheme() ? 'theme.json' : 'plugin.json';
    }

    /**
     * @return array<string, mixed>
     */
    private function readJson(string $file): array
    {
        $raw = @file_get_contents(self::REPO_ROOT.'/'.$file);
        $this->assertNotFalse($raw, "{$file} is missing or unreadable.");

        $decoded = json_decode($raw, true);
        $this->assertIsArray($decoded, "{$file} must be valid JSON.");

        return $decoded;
    }
}
