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

namespace Plugins\DixlaseBlog\Tests\Feature;

use Illuminate\Support\Facades\File;
use Tests\TestCase;

/**
 * The views keep the CSP rules every Dixlase extension follows: the
 * policy sets script-src-attr 'none', so an inline event handler never
 * runs, and a <script> runs only with the request's nonce.
 */
class BlogViewCspTest extends TestCase
{
    public function test_views_have_no_inline_event_handlers(): void
    {
        $found = [];

        foreach ($this->views() as $path => $source) {
            // " onclick=", but not Alpine's "x-on:click=" or "@click=".
            if (preg_match_all('/\son[a-z]+\s*=\s*["\']/i', $source, $matches)) {
                foreach ($matches[0] as $match) {
                    $found[] = $path.': '.trim($match);
                }
            }
            if (preg_match_all('/href\s*=\s*["\']\s*javascript:/i', $source, $matches)) {
                $found[] = $path.': javascript: URL';
            }
        }

        $this->assertSame([], $found);
    }

    public function test_every_script_tag_carries_the_nonce(): void
    {
        $found = [];

        foreach ($this->views() as $path => $source) {
            preg_match_all('/<script\b[^>]*>/i', $source, $matches);
            foreach ($matches[0] as $tag) {
                $isData = (bool) preg_match('/type\s*=\s*["\']application\/(ld\+)?json["\']/i', $tag);
                if (! $isData && ! str_contains($tag, '@cspNonce') && ! str_contains($tag, 'nonce=')) {
                    $found[] = $path.': '.$tag;
                }
            }
        }

        $this->assertSame([], $found);
    }

    /**
     * @return array<string, string> source keyed by path inside the plugin
     */
    private function views(): array
    {
        $root = base_path('plugins/DixlaseBlog');
        $views = [];
        foreach (File::allFiles($root.'/resources/views') as $file) {
            if (str_ends_with($file->getFilename(), '.blade.php')) {
                $views[str_replace($root.'/', '', $file->getPathname())] = File::get($file->getPathname());
            }
        }
        $this->assertNotEmpty($views);

        return $views;
    }
}
