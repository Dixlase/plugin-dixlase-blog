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

use App\Contracts\I18n\LocaleUrlRouting;
use App\Enums\ContentEditorType;
use App\Enums\ContentStatus;
use App\Enums\MemberRole;
use App\Helpers\LocaleHelper;
use App\Http\Middleware\CheckInstallationReady;
use App\Models\Member;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Plugins\DixlaseBlog\App\Models\BlogPost;
use Plugins\DixlaseBlog\App\Models\BlogPostRevision;
use Plugins\DixlaseBlog\App\Models\BlogTag;
use Tests\TestCase;

/**
 * The Tags screen: list, add, rename and delete, and a revision that
 * names a deleted tag.
 */
class BlogAdminTagsTest extends TestCase
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

        Route::middleware('plugin')->prefix('admin-custom')->group(base_path('plugins/DixlaseBlog/routes/admin.php'));
        Route::middleware('web')->group(base_path('plugins/DixlaseBlog/routes/web.php'));
        Route::getRoutes()->refreshNameLookups();
        Route::getRoutes()->refreshActionLookups();

        $this->withoutMiddleware(CheckInstallationReady::class);

        $this->actingAs(Member::factory()->create(['role' => MemberRole::ADMIN, 'status' => 1]));
    }

    public function test_the_screen_lists_each_tag_with_its_posts(): void
    {
        $tag = BlogTag::create(['name' => 'Release', 'slug' => 'release', 'lang' => 'en']);
        $this->postWith($tag);
        BlogTag::create(['name' => '<b>Bold</b>', 'slug' => 'bold', 'lang' => 'en']);

        $this->get(route('dixlase-blog::admin.blog.tags.index'))->assertOk()
            ->assertSee('Release')
            ->assertSee('release')
            ->assertSee(trans_choice('dixlase-blog::admin.tags.posts_count', 1, ['count' => 1]))
            // The delete message is shown as HTML, so the name is escaped.
            ->assertDontSee('<b>Bold</b>', false);
    }

    public function test_a_tag_is_added_with_a_slug_from_its_name(): void
    {
        $this->post(route('dixlase-blog::admin.blog.tags.store'), ['name' => '  Release notes '])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('dixlase-blog::admin.blog.tags.index'));
        $this->post(route('dixlase-blog::admin.blog.tags.store'), ['name' => 'リリース', 'slug' => ''])
            ->assertSessionHasNoErrors();

        $this->assertSame('release-notes', BlogTag::query()->where('name', 'Release notes')->value('slug'));
        $this->assertMatchesRegularExpression('/^tag-[0-9a-f]{8}$/', (string) BlogTag::query()->where('name', 'リリース')->value('slug'));
    }

    public function test_a_name_or_slug_another_tag_uses_is_refused(): void
    {
        BlogTag::create(['name' => 'Release', 'slug' => 'release', 'lang' => 'en']);
        $other = BlogTag::create(['name' => 'Notes', 'slug' => 'notes', 'lang' => 'en']);

        $this->post(route('dixlase-blog::admin.blog.tags.store'), ['name' => 'Release'])->assertSessionHasErrors('name');
        $this->post(route('dixlase-blog::admin.blog.tags.store'), ['name' => 'New', 'slug' => 'release'])->assertSessionHasErrors('slug');
        $this->post(route('dixlase-blog::admin.blog.tags.store'), ['name' => 'Bad', 'slug' => 'Not A Slug'])->assertSessionHasErrors('slug');
        $this->put(route('dixlase-blog::admin.blog.tags.update', ['tag' => $other->id]), ['name' => 'Release', 'slug' => 'notes'])
            ->assertSessionHasErrors('name');

        $this->assertSame(2, BlogTag::query()->count());
    }

    public function test_a_tag_is_renamed_on_every_post(): void
    {
        $tag = BlogTag::create(['name' => 'Relase', 'slug' => 'relase', 'lang' => 'en']);
        $post = $this->postWith($tag);

        // Its own name and slug are not a clash.
        $this->put(route('dixlase-blog::admin.blog.tags.update', ['tag' => $tag->id]), ['name' => 'Relase', 'slug' => 'relase'])
            ->assertSessionHasNoErrors();
        $this->put(route('dixlase-blog::admin.blog.tags.update', ['tag' => $tag->id]), ['name' => 'Release', 'slug' => 'release'])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('dixlase-blog::admin.blog.tags.index'));

        $this->assertSame(['Release'], $post->fresh()->tags->pluck('name')->all());
        $this->assertSame('release', $tag->fresh()->slug);
    }

    public function test_deleting_a_tag_keeps_its_posts_and_their_revisions_restore(): void
    {
        $this->post(route('dixlase-blog::admin.blog.store'), [
            'title' => 'Hello',
            'content' => 'Body',
            'editor_type' => 'markdown',
            'status' => 'draft',
            'tags' => ['Release', 'Notes'],
        ])->assertSessionHasNoErrors();
        $post = BlogPost::query()->sole();
        $revision = BlogPostRevision::query()->where('post_id', $post->id)->sole();
        $release = BlogTag::query()->where('name', 'Release')->sole();

        $this->delete(route('dixlase-blog::admin.blog.tags.destroy', ['tag' => $release->id]))
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('dixlase-blog::admin.blog.tags.index'));

        $this->assertNull(BlogTag::query()->find($release->id));
        $this->assertSame(['Notes'], $post->fresh()->tags->pluck('name')->all());
        $this->assertSame(0, DB::table('plg_dixlase_blog_post_tag')->where('tag_id', $release->id)->count());

        // The revision still names the deleted tag; it is skipped.
        $this->post(route('dixlase-blog::admin.blog.revisions.restore', ['post' => $post->id, 'revisionId' => $revision->id]))
            ->assertSessionHasNoErrors();
        $this->assertSame(['Notes'], $post->fresh()->tags->pluck('name')->all());
    }

    public function test_a_single_language_site_keeps_tags_in_its_language(): void
    {
        $site = LocaleHelper::getSiteDefaultLocale();
        $other = $site === 'ja' ? 'en' : 'ja';
        $sample = BlogTag::create(['name' => 'Welcome', 'slug' => 'welcome']);
        $kept = BlogTag::create(['name' => 'Kept', 'slug' => 'kept', 'lang' => $other]);

        $this->get(route('dixlase-blog::admin.blog.tags.index'))->assertOk()
            ->assertDontSee('name="lang"', false);

        // A language sent anyway is not used.
        $this->post(route('dixlase-blog::admin.blog.tags.store'), ['name' => 'New', 'lang' => $other])->assertSessionHasNoErrors();
        $this->assertSame($site, BlogTag::query()->where('name', 'New')->value('lang'));

        // A tag saved without one takes the site's; one that has one keeps it.
        $this->put(route('dixlase-blog::admin.blog.tags.update', ['tag' => $sample->id]), ['name' => 'Welcome', 'slug' => 'welcome'])->assertSessionHasNoErrors();
        $this->put(route('dixlase-blog::admin.blog.tags.update', ['tag' => $kept->id]), ['name' => 'Kept', 'slug' => 'kept', 'lang' => $site])->assertSessionHasNoErrors();
        $this->assertSame($site, $sample->fresh()->lang);
        $this->assertSame($other, $kept->fresh()->lang);
    }

    public function test_a_multilingual_site_chooses_the_language(): void
    {
        $this->app->instance(LocaleUrlRouting::class, new class implements LocaleUrlRouting
        {
            public function isEnabled(): bool
            {
                return true;
            }
        });
        $tag = BlogTag::create(['name' => 'Release', 'slug' => 'release', 'lang' => 'en']);

        $this->get(route('dixlase-blog::admin.blog.tags.index'))->assertOk()
            ->assertSee('id="new-tag-lang"', false)
            ->assertSee('id="edit-tag-lang-'.$tag->id.'"', false);

        $this->post(route('dixlase-blog::admin.blog.tags.store'), ['name' => 'リリース', 'lang' => 'ja'])->assertSessionHasNoErrors();
        $this->assertSame('ja', BlogTag::query()->where('name', 'リリース')->value('lang'));

        $this->put(route('dixlase-blog::admin.blog.tags.update', ['tag' => $tag->id]), ['name' => 'Release', 'slug' => 'release', 'lang' => 'ja'])->assertSessionHasNoErrors();
        $this->assertSame('ja', $tag->fresh()->lang);

        $this->post(route('dixlase-blog::admin.blog.tags.store'), ['name' => 'Bad', 'lang' => 'xx'])->assertSessionHasErrors('lang');
    }

    private function postWith(BlogTag $tag): BlogPost
    {
        $post = BlogPost::create([
            'title' => 'Tagged',
            'slug' => 'tagged-'.$tag->id,
            'content' => 'Body',
            'editor_type' => ContentEditorType::MARKDOWN,
            'status' => ContentStatus::DRAFT,
            'lang' => 'en',
        ]);
        $post->tags()->attach($tag->id);

        return $post;
    }
}
