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

/*
 * A stand-in for DixlaseSEO's helper, loaded by SeoPageMetaRecorder::install().
 * It is variadic so it keeps argument names: a named argument arrives with
 * a string key, which is how the tests tell it from a positional one.
 */

const DLS_BLOG_TEST_SEO_PAGE_META_STUB = true;

function dls_seo_set_page_meta(mixed ...$arguments): void
{
    \Plugins\DixlaseBlog\Tests\Support\SeoPageMetaRecorder::$calls[] = $arguments;
}
