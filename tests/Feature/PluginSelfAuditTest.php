<?php

/**
 * This file is part of Dixlase Blog.
 *
 * Copyright (C) 2026 exc-D inc. and Dixlase contributors
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

namespace Plugins\DixlaseBlog\Tests\Feature;

use App\Services\Csp\CspComplianceScanner;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

/**
 * CI-time self-audit against core's own detectors.
 *
 * The two checks here replay what the admin plugin audit dashboard runs:
 * if these fail, the dashboard would light up on a site that installs
 * the release. Pattern taken from `plugin-dixlase-inquiry` PR #72, where
 * seven signed releases shipped a `settings.write_own` permission
 * mismatch before anyone noticed in the admin panel.
 */
class PluginSelfAuditTest extends TestCase
{
    private const SLUG = 'dixlase-blog';

    private const PACKAGE_DIR = 'DixlaseBlog';

    public function test_no_permission_mismatches(): void
    {
        Artisan::call('dls:plugin:audit', [
            'plugin' => self::PACKAGE_DIR,
            '--json' => true,
        ]);

        $raw = Artisan::output();
        $result = json_decode($raw, true);

        $this->assertIsArray(
            $result,
            'dls:plugin:audit --json output was not parsable: '.substr($raw, 0, 200),
        );

        $optional = $this->optionalPermissions();

        $surviving = array_values(array_filter(
            $result['mismatches'] ?? [],
            fn (array $m) => ! in_array($m['permission'] ?? '', $optional, true),
        ));

        $this->assertSame(
            [],
            $surviving,
            "Permission mismatches detected by dls:plugin:audit after filtering _optional:\n"
                .json_encode($surviving, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE),
        );
    }

    public function test_no_csp_strict_mode_violations(): void
    {
        /** @var CspComplianceScanner $scanner */
        $scanner = app(CspComplianceScanner::class);
        $result = $scanner->scanPlugin(self::SLUG);

        $blockers = array_values(array_filter(
            $result['violations'] ?? [],
            fn (array $v) => in_array($v['severity'] ?? '', ['warning', 'critical'], true),
        ));

        $this->assertSame(
            [],
            $blockers,
            "CSP strict-mode violations (warning/critical) detected:\n"
                .json_encode($blockers, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE),
        );
    }

    /**
     * Permissions this plugin's manifest tags as _optional — known false
     * positives from the shortlist regexes in core's detection patterns.
     *
     * @return array<string>
     */
    private function optionalPermissions(): array
    {
        $manifest = json_decode(
            file_get_contents(dirname(__DIR__, 2).'/plugin.json'),
            true,
        );

        return $manifest['permissions']['_optional'] ?? [];
    }
}
