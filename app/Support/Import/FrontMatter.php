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

namespace Plugins\DixlaseBlog\App\Support\Import;

/**
 * The front matter of a post file (#210)
 *
 * Reads the block between two `---` lines at the top of a Markdown file.
 * Core ships no YAML parser outside development, so this reads the small
 * subset a post needs, and refuses anything else rather than guess:
 *
 * - `key: value` — a string; quotes ("…" or '…') are optional and keep `#`
 *   and surrounding spaces. Unquoted, ` # …` starts a comment, and `true`,
 *   `false`, `null`, `~` and an empty value mean what they do in YAML
 * - `key: [a, "b, c"]` — a list on one line
 * - `key:` followed by `  - item` lines — a list
 * - `key:` followed by indented `name: value` lines — one level of mapping
 * - whole-line `#` comments and blank lines are ignored
 */
final class FrontMatter
{
    /**
     * @param  array<string, mixed>  $fields
     */
    private function __construct(
        public readonly array $fields,
        public readonly string $body,
    ) {}

    /**
     * @throws FrontMatterException
     */
    public static function parse(string $markdown): self
    {
        $markdown = preg_replace('/^\xEF\xBB\xBF/', '', $markdown) ?? $markdown;
        $lines = preg_split('/\r\n|\r|\n/', $markdown) ?: [];

        if (($lines[0] ?? null) === null || rtrim($lines[0]) !== '---') {
            return new self([], $markdown);
        }

        $end = null;
        for ($i = 1, $count = count($lines); $i < $count; $i++) {
            if (rtrim($lines[$i]) === '---') {
                $end = $i;
                break;
            }
        }
        if ($end === null) {
            throw new FrontMatterException(__('dixlase-blog::import.front_matter_unclosed'));
        }

        $fields = self::fields(array_slice($lines, 1, $end - 1));
        $body = implode("\n", array_slice($lines, $end + 1));

        return new self($fields, ltrim($body, "\n"));
    }

    /**
     * @param  list<string>  $lines
     * @return array<string, mixed>
     *
     * @throws FrontMatterException
     */
    private static function fields(array $lines): array
    {
        $fields = [];
        $key = null;          // the top-level key a nested block belongs to
        $nested = null;       // 'list' or 'map' once the block's shape is known

        foreach ($lines as $index => $raw) {
            $number = $index + 2;    // the first field is line 2 of the file
            $line = rtrim($raw);

            if (trim($line) === '' || str_starts_with(ltrim($line), '#')) {
                continue;
            }

            if (preg_match('/^\s+/', $line) === 1) {
                if ($key === null) {
                    throw self::error($number, $raw);
                }

                $item = ltrim($line);
                if (str_starts_with($item, '- ') || $item === '-') {
                    if ($nested === 'map') {
                        throw self::error($number, $raw);
                    }
                    $nested = 'list';
                    $fields[$key][] = self::scalar(substr($item, 1), $number, $raw);

                    continue;
                }

                if (preg_match('/^([A-Za-z_][A-Za-z0-9_]*):(?:\s+(.*))?$/', $item, $m) === 1 && $nested !== 'list') {
                    $nested = 'map';
                    $fields[$key][$m[1]] = self::value($m[2] ?? '', $number, $raw);

                    continue;
                }

                throw self::error($number, $raw);
            }

            if (preg_match('/^([A-Za-z_][A-Za-z0-9_]*):(?:\s+(.*))?$/', $line, $m) !== 1) {
                throw self::error($number, $raw);
            }

            if (array_key_exists($m[1], $fields)) {
                throw new FrontMatterException(__('dixlase-blog::import.front_matter_duplicate', ['line' => $number, 'key' => $m[1]]));
            }

            $value = trim(self::stripComment($m[2] ?? ''));
            if ($value === '') {
                // Either an empty value or the start of a nested block.
                $key = $m[1];
                $nested = null;
                $fields[$key] = [];

                continue;
            }

            $key = null;
            $fields[$m[1]] = self::value($m[2], $number, $raw);
        }

        // A key with nothing under it is an empty value, not an empty list.
        foreach ($fields as $name => $value) {
            if ($value === []) {
                $fields[$name] = null;
            }
        }

        return $fields;
    }

    /**
     * @return string|bool|list<string|bool|null>|null
     *
     * @throws FrontMatterException
     */
    private static function value(string $value, int $number, string $raw): string|bool|array|null
    {
        $trimmed = trim($value);

        if (str_starts_with($trimmed, '[')) {
            $trimmed = trim(self::stripComment($trimmed));
            if (! str_ends_with($trimmed, ']')) {
                throw self::error($number, $raw);
            }

            return self::inlineList(substr($trimmed, 1, -1), $number, $raw);
        }

        return self::scalar($trimmed, $number, $raw);
    }

    /**
     * @return list<string|bool|null>
     *
     * @throws FrontMatterException
     */
    private static function inlineList(string $inner, int $number, string $raw): array
    {
        if (trim($inner) === '') {
            return [];
        }

        preg_match_all('/\s*("(?:[^"\\\\]|\\\\.)*"|\'(?:[^\']|\'\')*\'|[^,]*)\s*(?:,|$)/', $inner, $matches);

        $items = [];
        foreach ($matches[1] as $i => $item) {
            // The pattern also matches the empty string at the very end.
            if ($item === '' && $i === count($matches[1]) - 1) {
                continue;
            }
            if (trim($item) === '') {
                throw self::error($number, $raw);
            }
            $items[] = self::scalar($item, $number, $raw);
        }

        return $items;
    }

    /**
     * @throws FrontMatterException
     */
    private static function scalar(string $value, int $number, string $raw): string|bool|null
    {
        $value = trim($value);

        if (str_starts_with($value, '"')) {
            if (preg_match('/^"((?:[^"\\\\]|\\\\.)*)"\s*(?:#.*)?$/', $value, $m) !== 1) {
                throw self::error($number, $raw);
            }

            return stripcslashes($m[1]);
        }

        if (str_starts_with($value, "'")) {
            if (preg_match("/^'((?:[^']|'')*)'\\s*(?:#.*)?$/", $value, $m) !== 1) {
                throw self::error($number, $raw);
            }

            return str_replace("''", "'", $m[1]);
        }

        $value = trim(self::stripComment($value));

        return match (strtolower($value)) {
            '', 'null', '~' => null,
            'true' => true,
            'false' => false,
            default => $value,
        };
    }

    /**
     * Drop a ` # comment` from an unquoted value
     */
    private static function stripComment(string $value): string
    {
        return preg_replace('/(^|\s)#.*$/', '', $value) ?? $value;
    }

    private static function error(int $number, string $raw): FrontMatterException
    {
        return new FrontMatterException(__('dixlase-blog::import.front_matter_line', ['line' => $number, 'text' => trim($raw)]));
    }
}
