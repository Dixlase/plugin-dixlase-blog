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

namespace Plugins\DixlaseBlog\App\Services;

/**
 * The blog's front-end asset URLs, for a caller that needs URLs rather than tags.
 *
 * front/_assets.blade.php pushes the blog's stylesheet and script onto the
 * theme's stacks itself, which is all its own pages need. A front-page body
 * cannot do that: FrontPageBodyResult carries asset URLs and the theme puts
 * them on its stacks, because a body is rendered inside the theme's layout and
 * must not reach into it (#257).
 *
 * The URLs are read back out of core's load_plugin_assets() rather than
 * resolved again here. That helper is the one place that knows where a
 * plugin's built assets live — a Vite manifest, a static fallback, or the dev
 * server — and a second implementation beside it would drift the moment any of
 * that changes.
 */
final class BlogFrontAssetUrls
{
    /**
     * The entries front/_assets.blade.php loads. Kept in step with it by
     * BlogFrontAssetUrlsTest, which fails if the two lists diverge.
     *
     * @var array<int, string>
     */
    public const FILES = ['css/style.scss', 'js/front.js'];

    /**
     * @return array{styleUrls: array<int, string>, scriptUrls: array<int, string>}
     */
    public function forFrontPageBody(): array
    {
        $html = load_plugin_assets('DixlaseBlog', self::FILES);

        return [
            'styleUrls' => $this->stylesheetHrefs($html),
            'scriptUrls' => $this->scriptSources($html),
        ];
    }

    /**
     * Only `rel="stylesheet"` links.
     *
     * The manifest path also emits `<link rel="preload" as="font">` for a
     * font an entry pulls in, and handing that to a theme as a stylesheet
     * would have it rendered as one.
     *
     * @return array<int, string>
     */
    private function stylesheetHrefs(string $html): array
    {
        preg_match_all('/<link\b[^>]*\brel="stylesheet"[^>]*\bhref="([^"]*)"/i', $html, $matches);

        return array_values(array_filter($matches[1]));
    }

    /**
     * @return array<int, string>
     */
    private function scriptSources(string $html): array
    {
        preg_match_all('/<script\b[^>]*\bsrc="([^"]*)"/i', $html, $matches);

        return array_values(array_filter($matches[1]));
    }
}
