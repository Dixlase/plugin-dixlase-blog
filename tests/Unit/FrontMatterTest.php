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

use Plugins\DixlaseBlog\App\Support\Import\FrontMatter;
use Plugins\DixlaseBlog\App\Support\Import\FrontMatterException;
use Tests\TestCase;

/**
 * The front matter subset a post file may use (#210)
 */
class FrontMatterTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->app['translator']->addNamespace('dixlase-blog', base_path('plugins/DixlaseBlog/lang'));
    }

    public function test_it_reads_scalars_lists_and_one_level_of_mapping(): void
    {
        $matter = FrontMatter::parse(<<<'MD'
            ---
            title: "Hello: world # kept"
            slug: hello-world   # a comment
            is_pinned: false
            categories: [news, "a, b"]
            tags:
              - dixlase
              - 'it''s'
            excerpt:
            # a comment line
            seo:
              description: Meta description
              ogp_image: ./ogp.png
            ---

            Body
            MD);

        $this->assertSame([
            'title' => 'Hello: world # kept',
            'slug' => 'hello-world',
            'is_pinned' => false,
            'categories' => ['news', 'a, b'],
            'tags' => ['dixlase', "it's"],
            'excerpt' => null,
            'seo' => ['description' => 'Meta description', 'ogp_image' => './ogp.png'],
        ], $matter->fields);
        $this->assertSame('Body', $matter->body);
    }

    public function test_a_file_without_front_matter_is_all_body(): void
    {
        $matter = FrontMatter::parse("# Title\n\nText");

        $this->assertSame([], $matter->fields);
        $this->assertSame("# Title\n\nText", $matter->body);
    }

    public function test_crlf_and_a_byte_order_mark_are_accepted(): void
    {
        $matter = FrontMatter::parse("\xEF\xBB\xBF---\r\ntitle: x\r\n---\r\nBody");

        $this->assertSame(['title' => 'x'], $matter->fields);
        $this->assertSame('Body', $matter->body);
    }

    /**
     * @return array<string, array{string}>
     */
    public static function unreadable(): array
    {
        return [
            'unclosed' => ["---\ntitle: x\n"],
            'indented first line' => ["---\n  title: x\n---\n"],
            'duplicate key' => ["---\ntitle: x\ntitle: y\n---\n"],
            'no colon' => ["---\ntitle x\n---\n"],
            'unclosed list' => ["---\ntags: [a, b\n---\n"],
            'list mixed with map' => ["---\nseo:\n  - a\n  b: c\n---\n"],
            'unclosed quote' => ["---\ntitle: \"x\n---\n"],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('unreadable')]
    public function test_anything_else_is_refused(string $markdown): void
    {
        $this->expectException(FrontMatterException::class);

        FrontMatter::parse($markdown);
    }
}
