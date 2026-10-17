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

namespace Plugins\DixlaseBlog\Tests\Support;

use App\Contracts\TranslationResolver;
use Illuminate\Database\Eloquent\Model;

/**
 * In-memory stand-in for the TranslationResolver that a translation plugin
 * binds, so the blog's translation tests do not depend on one.
 */
final class FakeTranslationResolver implements TranslationResolver
{
    /** @var array<string, mixed> Values keyed by "class|id|locale|field". */
    private array $values = [];

    public function set(Model $model, string $locale, array $fields): void
    {
        foreach ($fields as $field => $value) {
            $this->store($model, $field, $value, $locale);
        }
    }

    public function resolve(Model $model, string $field, string $locale): mixed
    {
        return $this->values[$this->key($model, $field, $locale)] ?? null;
    }

    public function store(Model $model, string $field, mixed $value, string $locale): void
    {
        $this->values[$this->key($model, $field, $locale)] = $value;
    }

    public function all(Model $model, string $field): array
    {
        return [];
    }

    public function exists(Model $model, string $field, string $locale): bool
    {
        return array_key_exists($this->key($model, $field, $locale), $this->values);
    }

    public function delete(Model $model, string $field, ?string $locale = null): void
    {
    }

    public function getAvailableLocales(Model $model): array
    {
        return [];
    }

    public function copy(Model $source, Model $target, ?array $fields = null, ?array $locales = null): void
    {
    }

    private function key(Model $model, string $field, string $locale): string
    {
        return $model::class.'|'.$model->getKey().'|'.$locale.'|'.$field;
    }
}
