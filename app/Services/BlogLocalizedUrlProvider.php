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

namespace Plugins\DixlaseBlog\App\Services;

use App\Contracts\I18n\LocalizedUrlProvider;
use App\Helpers\LocaleHelper;
use Illuminate\Http\Request;

/**
 * The same blog page in every supported locale, for hreflang links.
 *
 * Answers only for the blog's front routes, and only on the /{locale}
 * copy of them that DixlaseMultilingual mounts. Every language shares the
 * post's slug, so each alternate is the current route with another
 * locale; DixlaseMultilingual keeps only the enabled locales.
 */
final class BlogLocalizedUrlProvider implements LocalizedUrlProvider
{
    public function getAlternateUrls(Request $request): array
    {
        $route = $request->route();
        $name = $route?->getName();

        if ($route === null || ! is_string($name) || ! str_starts_with($name, 'dixlase-blog::front.')
            || ! in_array('locale', $route->parameterNames(), true)) {
            return [];
        }

        $parameters = array_diff_key($route->parameters(), ['locale' => true]);
        $query = $request->getQueryString();

        $urls = [];
        foreach (LocaleHelper::supportedLocales() as $locale) {
            $urls[$locale] = route($name, ['locale' => $locale] + $parameters).($query !== null ? '?'.$query : '');
        }

        return $urls;
    }
}
