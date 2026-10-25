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

use App\Contracts\TranslationResolver;
use App\Enums\ContentEditorType;
use App\Enums\ContentStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Plugins\DixlaseBlog\App\Models\BlogCategory;
use Plugins\DixlaseBlog\App\Models\BlogPost;
use Plugins\DixlaseBlog\App\Models\BlogTag;
use Plugins\DixlaseBlog\App\Shortcodes\BlogShortcode;
use Plugins\DixlaseBlog\Tests\Support\FakeTranslationResolver;
use Tests\TestCase;

/**
 * [blog]: the latest posts in other content, such as the front page.
 */
class BlogShortcodeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->artisan('migrate', [
            '--path' => base_path('plugins/DixlaseBlog/database/migrations'),
            '--realpath' => true,
        ]);

        $this->app['translator']->addNamespace('dixlase-blog', base_path('plugins/DixlaseBlog/lang'));
        $this->app['view']->addNamespace('dixlase-blog', base_path('plugins/DixlaseBlog/resources/views'));
        $this->app->instance(TranslationResolver::class, new FakeTranslationResolver());

        Route::middleware('web')->group(base_path('plugins/DixlaseBlog/routes/web.php'));
        Route::getRoutes()->refreshNameLookups();
        Route::getRoutes()->refreshActionLookups();
    }

    public function test_it_lists_three_posts_as_cards_with_a_link_to_the_blog_by_default(): void
    {
        $this->makePosts(4);

        $html = $this->render();

        $this->assertStringContainsString('dixlase-blog-embed--cards', $html);
        $this->assertSame(3, substr_count($html, 'class="dixlase-blog-card__title"'));
        $this->assertStringContainsString('<h3 class="dixlase-blog-card__title"', $html);
        $this->assertStringContainsString('Post 4', $html);
        $this->assertStringNotContainsString('Post 1<', $html);
        $this->assertStringContainsString('href="'.route('dixlase-blog::front.index').'"', $html);
        $this->assertStringContainsString(__('dixlase-blog::front.shortcode.more'), $html);
    }

    public function test_count_layout_and_link_are_attributes(): void
    {
        $this->makePosts(4);

        $list = $this->render(['count' => '2', 'layout' => 'list', 'link' => 'false']);
        $this->assertStringContainsString('dixlase-blog-embed--list', $list);
        $this->assertSame(2, substr_count($list, 'class="dixlase-blog-embed__item"'));
        $this->assertStringNotContainsString(__('dixlase-blog::front.shortcode.more'), $list);

        $full = $this->render(['count' => '1', 'layout' => 'full', 'link' => 'hide']);
        $this->assertStringContainsString('dixlase-blog-embed--full', $full);
        $this->assertStringContainsString('<h3 class="dixlase-blog-post__title"', $full);
        $this->assertStringContainsString('Body of post 4', $full);
        $this->assertStringNotContainsString(__('dixlase-blog::front.shortcode.more'), $full);
    }

    public function test_an_invalid_count_or_layout_falls_back(): void
    {
        $this->makePosts(5);

        foreach (['0', '-1', 'many', '51'] as $count) {
            $this->assertSame(3, substr_count($this->render(['count' => $count]), 'class="dixlase-blog-card__title"'), $count);
        }
        $this->assertStringContainsString('dixlase-blog-embed--cards', $this->render(['layout' => 'grid']));
    }

    public function test_it_follows_the_top_page_order_and_hides_posts_not_visible(): void
    {
        $this->makePosts(2);
        $pinned = $this->makePost('Old pinned post', now()->subYear());
        $pinned->update(['is_pinned' => true]);
        $this->makePost('Draft post', now()->subMinute(), ContentStatus::DRAFT);
        $this->makePost('Scheduled post', now()->addDay());

        $html = $this->render(['layout' => 'list', 'count' => '10']);

        $this->assertMatchesRegularExpression('/Old pinned post.*Post 2.*Post 1/s', $html);
        $this->assertStringNotContainsString('Draft post', $html);
        $this->assertStringNotContainsString('Scheduled post', $html);
    }

    public function test_category_and_tag_narrow_the_posts(): void
    {
        $this->makePosts(2);
        $category = BlogCategory::query()->create(['label' => 'Release', 'slug' => 'release', 'display_order' => 10]);
        $tag = BlogTag::query()->create(['name' => 'Howto', 'slug' => 'howto']);
        BlogPost::query()->where('title', 'Post 1')->sole()->categories()->attach($category);
        BlogPost::query()->where('title', 'Post 2')->sole()->tags()->attach($tag);

        $byCategory = $this->render(['category' => 'release', 'layout' => 'list']);
        $this->assertStringContainsString('Post 1', $byCategory);
        $this->assertStringNotContainsString('Post 2', $byCategory);

        $byTag = $this->render(['tag' => 'howto', 'layout' => 'list']);
        $this->assertStringContainsString('Post 2', $byTag);
        $this->assertStringNotContainsString('Post 1', $byTag);
    }

    public function test_it_renders_nothing_without_posts(): void
    {
        $this->assertSame('', $this->render());

        $this->makePosts(1);
        $this->assertSame('', $this->render(['category' => 'no-such-category']));
    }

    public function test_the_manifest_declares_the_shortcode(): void
    {
        $manifest = json_decode((string) file_get_contents(base_path('plugins/DixlaseBlog/plugin.json')), true, flags: JSON_THROW_ON_ERROR);

        $this->assertTrue($manifest['provides']['shortcodes']);
        $this->assertTrue($manifest['permissions']['system']['register_shortcodes']);
    }

    /**
     * @param  array<string, string>  $attributes
     */
    private function render(array $attributes = []): string
    {
        return app(BlogShortcode::class)->render($attributes);
    }

    private function makePosts(int $count): void
    {
        for ($i = 1; $i <= $count; $i++) {
            $this->makePost('Post '.$i, now()->subDays(10 - $i));
        }
    }

    private function makePost(string $title, \DateTimeInterface $publishedAt, ContentStatus $status = ContentStatus::PUBLISHED): BlogPost
    {
        return BlogPost::create([
            'title' => $title,
            'slug' => strtolower(str_replace(' ', '-', $title)),
            'content' => 'Body of '.strtolower($title),
            'editor_type' => ContentEditorType::MARKDOWN,
            'status' => $status,
            'lang' => 'en',
            'published_at' => $publishedAt,
        ]);
    }
}
