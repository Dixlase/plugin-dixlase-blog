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
use Illuminate\Support\Facades\Route;
use Plugins\DixlaseBlog\App\Models\BlogPost;
use Tests\TestCase;

/**
 * The translation files stay complete: en and ja define the same keys
 * with the same placeholders, and every key the plugin names exists.
 * A missing key renders as the key itself, which no other test notices.
 */
class BlogTranslationKeysTest extends TestCase
{
    private const LOCALES = ['en', 'ja'];

    public function test_every_locale_defines_the_same_keys(): void
    {
        $en = $this->keys('en');

        foreach (self::LOCALES as $locale) {
            $keys = $this->keys($locale);
            $this->assertSame([], array_values(array_diff(array_keys($en), array_keys($keys))), "missing in lang/{$locale}");
            $this->assertSame([], array_values(array_diff(array_keys($keys), array_keys($en))), "only in lang/{$locale}");
        }
    }

    public function test_translations_are_filled_in_and_keep_their_placeholders(): void
    {
        $en = $this->keys('en');
        $problems = [];

        foreach (self::LOCALES as $locale) {
            foreach ($this->keys($locale) as $key => $text) {
                // A few headers are blank on purpose (the actions column);
                // English decides which.
                if (trim($text) === '' && trim($en[$key] ?? '') !== '') {
                    $problems[] = "{$locale}: {$key} is empty";
                }
                if (isset($en[$key]) && $this->placeholders($text) !== $this->placeholders($en[$key])) {
                    $problems[] = "{$locale}: {$key} has placeholders ".implode(' ', $this->placeholders($text));
                }
            }
        }

        $this->assertSame([], $problems);
    }

    public function test_every_key_the_plugin_names_exists(): void
    {
        $en = $this->keys('en');
        $missing = [];

        foreach ($this->sourceFiles() as $path) {
            $source = File::get($path);
            preg_match_all("/(?:__|trans|trans_choice|@lang|Lang::get)\(\s*'dixlase-blog::([^']+)'/", $source, $calls);
            preg_match_all("/'text'\s*=>\s*'dixlase-blog::([^']+)'/", $source, $menus);

            foreach (array_merge($calls[1], $menus[1]) as $key) {
                // A key that ends in a dot or underscore is completed at
                // run time; see the tests below.
                if (! str_ends_with($key, '.') && ! str_ends_with($key, '_') && ! isset($en[$key])) {
                    $missing[] = $key.' ('.str_replace($this->pluginPath().'/', '', $path).')';
                }
            }
        }

        $this->assertSame([], $missing);
    }

    /**
     * Core titles an admin page from its route name (AdminInterfaceTrait):
     * dixlase-blog::admin.blog.comments.index reads
     * dixlase-blog::admin.blog.comments.index.heading.
     */
    public function test_every_admin_page_has_a_heading(): void
    {
        Route::middleware('plugin')->prefix('admin-custom')->group($this->pluginPath().'/routes/admin.php');
        Route::getRoutes()->refreshNameLookups();

        $missing = [];
        foreach (Route::getRoutes()->getRoutes() as $route) {
            $name = (string) $route->getName();
            // The previews render the theme's layout, not an admin page.
            if (! str_starts_with($name, 'dixlase-blog::admin.') || ! in_array('GET', $route->methods(), true)
                || str_starts_with($name, 'dixlase-blog::admin.blog.preview')) {
                continue;
            }
            $key = substr($name, strlen('dixlase-blog::')).'.heading';
            foreach (self::LOCALES as $locale) {
                if (! array_key_exists($key, $this->keys($locale))) {
                    $missing[] = "{$locale}: {$key}";
                }
            }
        }

        $this->assertSame([], $missing);
    }

    public function test_every_revision_field_has_a_diff_label(): void
    {
        $en = $this->keys('en');
        // Columns from the model, and the pivot ids BlogPostService adds.
        $fields = array_merge((new BlogPost())->revisionableFields(), ['category_ids', 'tag_ids']);

        $missing = array_values(array_filter($fields, fn (string $field) => ! isset($en['admin.revision.diff_field_'.$field])));

        $this->assertSame([], $missing);
    }

    public function test_core_revision_components_find_their_keys(): void
    {
        $en = $this->keys('en');
        $missing = [];

        // The revision screens pass translationPrefix="dixlase-blog::admin.revision"
        // to core's components, which append their own key names.
        foreach (File::glob(base_path('resources/views/components/revision/*.blade.php')) as $path) {
            preg_match_all("/\\\$translationPrefix\s*\.\s*'\.([a-z_]+)'/", File::get($path), $matches);
            foreach (array_unique($matches[1]) as $name) {
                if (! str_ends_with($name, '_') && ! isset($en['admin.revision.'.$name])) {
                    $missing[] = 'admin.revision.'.$name.' ('.basename($path).')';
                }
            }
        }

        $this->assertSame([], $missing);
    }

    /**
     * Every translation of one locale, keyed "file.dotted.key".
     *
     * @return array<string, string>
     */
    private function keys(string $locale): array
    {
        $keys = [];
        foreach (File::glob($this->pluginPath()."/lang/{$locale}/*.php") as $path) {
            $keys += $this->flatten(require $path, basename($path, '.php').'.');
        }
        ksort($keys);

        return $keys;
    }

    /**
     * @param  array<string, mixed>  $values
     * @return array<string, string>
     */
    private function flatten(array $values, string $prefix): array
    {
        $flat = [];
        foreach ($values as $key => $value) {
            if (is_array($value)) {
                $flat += $this->flatten($value, $prefix.$key.'.');
            } else {
                $flat[$prefix.$key] = (string) $value;
            }
        }

        return $flat;
    }

    /**
     * @return list<string>
     */
    private function placeholders(string $text): array
    {
        preg_match_all('/:[a-z_]+/i', $text, $matches);
        $names = array_unique($matches[0]);
        sort($names);

        return $names;
    }

    /**
     * @return list<string>
     */
    private function sourceFiles(): array
    {
        $files = [];
        foreach (['app', 'config', 'resources/views', 'routes'] as $directory) {
            foreach (File::allFiles($this->pluginPath().'/'.$directory) as $file) {
                if (str_ends_with($file->getFilename(), '.php')) {
                    $files[] = $file->getPathname();
                }
            }
        }

        return $files;
    }

    private function pluginPath(): string
    {
        return base_path('plugins/DixlaseBlog');
    }
}
