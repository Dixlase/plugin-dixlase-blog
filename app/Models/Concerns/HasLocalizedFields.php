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

namespace Plugins\DixlaseBlog\App\Models\Concerns;

use App\Contracts\TranslationResolver;
use App\Helpers\LocaleHelper;
use Illuminate\Support\Facades\App;

/**
 * Reads translated field values through the core TranslationResolver
 * contract, which DixlaseMultilingual binds when it is installed.
 *
 * A record is written in one language (its source locale). Other locales
 * are overlays; where an overlay is missing or empty, the source text is
 * shown, in every locale. Raw attributes are never replaced, so admin
 * screens and the translation editor always see the source.
 */
trait HasLocalizedFields
{
    /** @var array<string, string|null> Resolved overlays keyed by "locale|field". */
    private array $localizedOverlays = [];

    /**
     * The language the record was written in. Falls back to the site's
     * default locale for records that have none.
     */
    public function sourceLocale(): string
    {
        $lang = $this->getAttribute('lang');

        return is_string($lang) && $lang !== '' ? $lang : LocaleHelper::getSiteDefaultLocale();
    }

    /**
     * The translated value of a field for a locale, or null when the
     * source text should be shown instead.
     */
    public function translationFor(string $field, ?string $locale = null): ?string
    {
        return $this->resolveOverlay($field, $locale ?? App::getLocale());
    }

    /**
     * The value to display for a field: the translation for the locale,
     * else the source text.
     */
    public function localized(string $field, ?string $locale = null): ?string
    {
        $value = $this->translationFor($field, $locale);

        return $value ?? $this->getAttribute($field);
    }

    /**
     * The BCP 47 tag of the language localized() returns for a field, for
     * the lang attribute of the element that shows it.
     */
    public function localizedLang(string $field, ?string $locale = null): string
    {
        $locale ??= App::getLocale();
        $shown = $this->translationFor($field, $locale) !== null ? $locale : $this->sourceLocale();

        return str_replace('_', '-', $shown);
    }

    protected function resolveOverlay(string $field, string $locale): ?string
    {
        if (! $this->exists || $locale === $this->sourceLocale() || ! App::bound(TranslationResolver::class)) {
            return null;
        }

        $key = $locale.'|'.$field;
        if (! array_key_exists($key, $this->localizedOverlays)) {
            $value = App::make(TranslationResolver::class)->resolve($this, $field, $locale);
            $this->localizedOverlays[$key] = is_string($value) && trim($value) !== '' ? $value : null;
        }

        return $this->localizedOverlays[$key];
    }
}
