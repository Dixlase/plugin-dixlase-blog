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

use App\Contracts\PluginIntegration\SeoMetaProviderInterface;
use App\DTO\PluginIntegration\SeoMetaDTO;
use App\Enums\ContentEditorType;
use App\Enums\ContentStatus;
use App\Enums\ContentStorageType;
use App\Enums\MemberRole;
use App\Models\Media;
use App\Models\Member;
use App\Models\SiteSetting;
use App\Services\Content\FileContentRegistry;
use Illuminate\Contracts\Console\Kernel as ConsoleKernel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Plugins\DixlaseBlog\App\Console\Commands\ImportPost;
use Plugins\DixlaseBlog\App\Models\BlogCategory;
use Plugins\DixlaseBlog\App\Models\BlogPost;
use Plugins\DixlaseBlog\App\Models\BlogPostRevision;
use Plugins\DixlaseBlog\App\Services\BlogPostService;
use Tests\TestCase;

/**
 * `dls:blog:post:import` turns a Markdown file into a post (#210)
 */
class BlogPostImportTest extends TestCase
{
    use RefreshDatabase;

    /** Where post files live on the local disk */
    private const FILES = 'dixlase-blog/posts';

    private Member $author;

    private string $dir;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
        Storage::fake(config('admin.files.storageDisk', 'public'));

        $this->artisan('migrate', [
            '--path' => base_path('plugins/DixlaseBlog/database/migrations'),
            '--realpath' => true,
        ]);
        $this->app['translator']->addNamespace('dixlase-blog', base_path('plugins/DixlaseBlog/lang'));
        $this->app->make(ConsoleKernel::class)->registerCommand($this->app->make(ImportPost::class));

        $this->author = Member::factory()->create(['role' => MemberRole::ADMIN, 'status' => 1, 'email' => 'author@example.com', 'account_name' => 'author']);
        BlogCategory::create(['label' => 'News', 'slug' => 'news', 'display_order' => 10]);

        $this->dir = storage_path('framework/testing/blog-import-'.uniqid());
        File::ensureDirectoryExists($this->dir.'/images');
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->dir);

        parent::tearDown();
    }

    public function test_a_file_becomes_a_draft_post_with_its_images(): void
    {
        $this->image('images/cover.png', 120, 60);
        $this->image('images/figure.png', 30, 20);
        $file = $this->postFile(<<<'MD'
            ---
            title: "Hello: world"
            slug: hello-world
            lang: en
            categories: [news]
            tags: [dixlase, blog]
            excerpt: A short summary
            featured_image: ./images/cover.png
            ---

            Intro.

            ![A figure](./images/figure.png "Figure 1")

            ![Remote](https://example.com/remote.png)
            MD);

        $this->import($file)->assertSuccessful();

        $post = BlogPost::sole();
        $this->assertSame('Hello: world', $post->title);
        $this->assertSame('hello-world', $post->slug);
        $this->assertSame('en', $post->lang);
        $this->assertSame(ContentStatus::DRAFT, $post->status);
        $this->assertSame(ContentEditorType::MARKDOWN, $post->editor_type);
        $this->assertSame('A short summary', $post->excerpt);
        $this->assertSame($this->author->id, (int) $post->user_id);
        $this->assertSame(['news'], $post->categories->pluck('slug')->all());
        $this->assertEqualsCanonicalizing(['dixlase', 'blog'], $post->tags->pluck('name')->all());

        $this->assertSame(2, Media::count());
        $cover = Media::where('name', 'cover.png')->sole();
        $figure = Media::where('name', 'figure.png')->sole();
        $this->assertSame($cover->id, (int) $post->featured_media_id);

        $this->assertStringContainsString('![A figure](/storage/'.config('admin.files.mediaPath', 'media').'/'.$figure->path.' "Figure 1")', $post->content);
        $this->assertStringContainsString('![Remote](https://example.com/remote.png)', $post->content);
        $this->assertStringNotContainsString('./images/', $post->content);
    }

    public function test_importing_again_updates_the_post_and_keeps_what_the_file_leaves_out(): void
    {
        $file = $this->postFile("---\ntitle: First\nslug: same-post\nexcerpt: Kept\ntags: [one]\n---\n\nFirst body.");
        $this->import($file)->assertSuccessful();
        $post = BlogPost::sole();
        $revisions = BlogPostRevision::query()->where('post_id', $post->id)->count();

        $file = $this->postFile("---\ntitle: Second\nslug: same-post\n---\n\nSecond body.");
        $this->import($file)->assertSuccessful();

        $post = BlogPost::sole()->fresh(['tags']);
        $this->assertSame('Second', $post->title);
        $this->assertSame('Second body.', trim($post->content));
        $this->assertSame('Kept', $post->excerpt);
        $this->assertSame(['one'], $post->tags->pluck('name')->all());
        $this->assertSame($revisions + 1, BlogPostRevision::query()->where('post_id', $post->id)->count());
    }

    public function test_the_same_image_twice_is_one_media_item(): void
    {
        $this->image('images/a.png');
        $file = $this->postFile("---\ntitle: Twice\nfeatured_image: images/a.png\n---\n\n![a](images/a.png)");

        $this->import($file)->assertSuccessful();

        $this->assertSame(1, Media::count());
    }

    public function test_an_unknown_category_saves_nothing(): void
    {
        $this->image('images/a.png');
        $file = $this->postFile("---\ntitle: Bad\ncategories: [missing]\nfeatured_image: ./images/a.png\n---\n\nBody");

        $this->import($file)
            ->expectsOutputToContain(__('dixlase-blog::import.unknown_categories', ['slugs' => 'missing']))
            ->assertFailed();

        $this->assertSame(0, BlogPost::count());
        $this->assertSame(0, Media::count());
    }

    public function test_a_missing_image_saves_nothing(): void
    {
        $file = $this->postFile("---\ntitle: Bad\n---\n\n![x](./images/nope.png)");

        $this->import($file)
            ->expectsOutputToContain(__('dixlase-blog::import.image_not_found', ['field' => 'body', 'path' => './images/nope.png']))
            ->assertFailed();

        $this->assertSame(0, BlogPost::count());
    }

    public function test_an_invalid_field_saves_nothing_and_imports_no_image(): void
    {
        $this->image('images/a.png');
        $file = $this->postFile("---\ntitle: Bad\nslug: Not A Slug!\nfeatured_image: ./images/a.png\n---\n\nBody");

        $this->import($file)->expectsOutputToContain('slug:')->assertFailed();

        $this->assertSame(0, BlogPost::count());
        $this->assertSame(0, Media::count());
    }

    public function test_an_unknown_key_is_refused(): void
    {
        $file = $this->postFile("---\ntitle: Typo\ntagz: [one]\n---\n\nBody");

        $this->import($file)
            ->expectsOutputToContain(__('dixlase-blog::import.unknown_key', ['key' => 'tagz', 'keys' => implode(', ', \Plugins\DixlaseBlog\App\Services\BlogPostImporter::KEYS)]))
            ->assertFailed();
    }

    public function test_a_dry_run_writes_nothing(): void
    {
        $this->image('images/a.png');
        $file = $this->postFile("---\ntitle: Dry\nfeatured_image: ./images/a.png\n---\n\nBody");

        $this->import($file, ['--dry-run' => true])
            ->expectsOutputToContain(__('dixlase-blog::import.would_create'))
            ->assertSuccessful();

        $this->assertSame(0, BlogPost::count());
        $this->assertSame(0, Media::count());
    }

    public function test_json_output_names_the_post_and_its_images(): void
    {
        $this->image('images/a.png');
        $file = $this->postFile("---\ntitle: Json\nslug: json-post\nfeatured_image: ./images/a.png\n---\n\nBody");

        Artisan::call('dls:blog:post:import', ['paths' => [$file], '--as' => 'author@example.com', '--json' => true, '--force' => true]);
        $result = json_decode(Artisan::output(), true);

        $this->assertTrue($result['ok']);
        $this->assertSame('create', $result['action']);
        $this->assertSame(BlogPost::sole()->id, $result['post']['id']);
        $this->assertSame('draft', $result['post']['status']);
        $this->assertSame('featured_image', $result['images'][0]['field']);
        $this->assertSame(Media::sole()->id, $result['images'][0]['media_id']);
    }

    public function test_outside_a_local_environment_it_asks_first(): void
    {
        $file = $this->postFile("---\ntitle: Live\nslug: asked\n---\n\nBody");

        $this->artisan('dls:blog:post:import', ['paths' => [$file], '--as' => 'author@example.com'])
            ->expectsConfirmation('Are you sure you want to run this command?', 'no')
            ->assertFailed();
        $this->assertSame(0, BlogPost::count());

        $this->artisan('dls:blog:post:import', ['paths' => [$file], '--as' => 'author@example.com'])
            ->expectsConfirmation('Are you sure you want to run this command?', 'yes')
            ->assertSuccessful();
        $this->assertSame(1, BlogPost::count());
    }

    public function test_it_runs_in_a_local_environment_without_asking(): void
    {
        $this->app['env'] = 'local';
        $file = $this->postFile("---\ntitle: Local\nslug: local\n---\n\nBody");

        $this->artisan('dls:blog:post:import', ['paths' => [$file], '--as' => 'author@example.com'])->assertSuccessful();
        $this->assertSame(1, BlogPost::count());
    }

    public function test_it_needs_an_active_author(): void
    {
        $file = $this->postFile("---\ntitle: Nobody\n---\n\nBody");

        $this->artisan('dls:blog:post:import', ['paths' => [$file], '--force' => true])
            ->expectsOutputToContain(__('dixlase-blog::import.member_required'))
            ->assertFailed();

        $this->artisan('dls:blog:post:import', ['paths' => [$file], '--as' => 'nobody@example.com', '--force' => true])
            ->expectsOutputToContain(__('dixlase-blog::import.member_not_found', ['identifier' => 'nobody@example.com']))
            ->assertFailed();
    }

    public function test_storage_file_creates_a_post_stored_as_files(): void
    {
        $this->useAdvancedMode();
        $file = $this->postFile("---\ntitle: Files\nslug: files-post\n---\n\nBody on disk.");

        $this->import($file, ['--storage' => 'file'])
            ->expectsOutputToContain(__('dixlase-blog::import.stored', ['storage' => 'file']))
            ->assertSuccessful();

        $post = BlogPost::sole();
        $this->assertSame(ContentStorageType::FILE, $post->storage_type);
        $this->assertSame('Body on disk.', trim((string) Storage::disk('local')->get(self::FILES.'/'.$post->id.'/content.md')));
    }

    public function test_storage_moves_an_existing_post_and_is_kept_when_left_out(): void
    {
        $this->useAdvancedMode();
        $file = $this->postFile("---\ntitle: Moving\nslug: moving-post\n---\n\nBody.");

        $this->import($file)->assertSuccessful();
        $post = BlogPost::sole();
        $this->assertSame(ContentStorageType::DATABASE, $post->storage_type);

        $this->import($file, ['--storage' => 'file'])->assertSuccessful();
        $this->assertSame(ContentStorageType::FILE, $post->fresh()->storage_type);
        $this->assertTrue(Storage::disk('local')->exists(self::FILES.'/'.$post->id.'/content.md'));

        // Left out, the post stays where it is.
        $this->import($file)->assertSuccessful();
        $this->assertSame(ContentStorageType::FILE, $post->fresh()->storage_type);

        $this->import($file, ['--storage' => 'database'])->assertSuccessful();
        $this->assertSame(ContentStorageType::DATABASE, $post->fresh()->storage_type);
        $this->assertFalse(Storage::disk('local')->exists(self::FILES.'/'.$post->id.'/content.md'));
    }

    public function test_storage_is_refused_in_the_simple_mode(): void
    {
        $file = $this->postFile("---\ntitle: Simple\n---\n\nBody");

        $this->import($file, ['--storage' => 'file'])
            ->expectsOutputToContain(__('dixlase-blog::import.storage_simple_mode', ['storage' => 'file']))
            ->assertFailed();

        $this->assertSame(0, BlogPost::count());
    }

    public function test_an_unknown_storage_is_refused(): void
    {
        $this->useAdvancedMode();
        $file = $this->postFile("---\ntitle: Unknown\n---\n\nBody");

        $this->import($file, ['--storage' => 'cloud'])
            ->expectsOutputToContain(__('dixlase-blog::import.invalid_storage', ['storage' => 'cloud', 'types' => 'database, file']))
            ->assertFailed();

        $this->assertSame(0, BlogPost::count());
    }

    public function test_a_dry_run_and_json_report_the_storage(): void
    {
        $this->useAdvancedMode();
        $file = $this->postFile("---\ntitle: Report\nslug: report-post\n---\n\nBody");

        $this->import($file, ['--storage' => 'file', '--dry-run' => true])
            ->expectsOutputToContain(__('dixlase-blog::import.would_store', ['storage' => 'file']))
            ->assertSuccessful();
        $this->assertSame(0, BlogPost::count());

        Artisan::call('dls:blog:post:import', ['paths' => [$file], '--as' => 'author@example.com', '--json' => true, '--force' => true, '--storage' => 'file']);
        $result = json_decode(Artisan::output(), true);

        $this->assertSame('file', $result['storage']);
        $this->assertSame('file', $result['post']['storage']);
    }

    /**
     * Storage can be chosen only in the advanced admin mode, as in the editor.
     */
    private function useAdvancedMode(): void
    {
        SiteSetting::setValue('admin_mode', '1');
        app(FileContentRegistry::class)->register(BlogPost::class);
    }

    public function test_a_partial_seo_block_keeps_the_other_field(): void
    {
        $seo = $this->fakeSeo();
        $this->image('images/ogp.png');
        $file = $this->postFile("---\ntitle: Seo\nslug: seo-post\nseo:\n  description: First\n  ogp_image: ./images/ogp.png\n---\n\nBody");
        $this->import($file)->assertSuccessful();
        $post = BlogPost::sole();
        $ogp = Media::sole()->id;

        $file = $this->postFile("---\nslug: seo-post\nseo:\n  description: Second\n---\n\nBody");
        $this->import($file)->assertSuccessful();
        $this->assertEquals(new SeoMetaDTO('Second', $ogp), $seo->meta[$post->id]);

        $file = $this->postFile("---\nslug: seo-post\nseo:\n  ogp_image: null\n---\n\nBody");
        $this->import($file)->assertSuccessful();
        $this->assertEquals(new SeoMetaDTO('Second', null), $seo->meta[$post->id]);
    }

    public function test_a_file_from_an_older_version_is_refused(): void
    {
        $file = $this->postFile("---\ntitle: First\nslug: versioned\n---\n\nFirst body.");
        $this->import($file)->assertSuccessful();
        $post = BlogPost::sole();
        $token = app(BlogPostService::class)->versionToken($post);

        $stale = $this->postFile("---\ntitle: Stale\nslug: versioned\nrevision: 000000000000\n---\n\nStale body.");
        $this->import($stale, ['--dry-run' => true])
            ->expectsOutputToContain(__('dixlase-blog::import.stale_revision', ['slug' => 'versioned', 'revision' => '000000000000', 'current' => $token]))
            ->assertFailed();
        $this->import($stale)->assertFailed();
        $this->assertSame('First', $post->fresh()->title);

        $current = $this->postFile("---\ntitle: Second\nslug: versioned\nrevision: {$token}\n---\n\nSecond body.");
        $this->import($current)->assertSuccessful();
        $this->assertSame('Second', $post->fresh()->title);

        $this->import($stale, ['--overwrite' => true])->assertSuccessful();
        $this->assertSame('Stale', $post->fresh()->title);
    }

    public function test_a_hand_written_file_is_refused_after_a_save_in_the_admin(): void
    {
        $file = $this->postFile("---\ntitle: First\nslug: hand-written\n---\n\nFirst body.");
        $this->import($file)->assertSuccessful();
        $post = BlogPost::sole();
        $this->assertSame(BlogPostRevision::TYPE_IMPORT, BlogPostRevision::query()->where('post_id', $post->id)->latest('id')->value('type'));

        // Importing again is fine while the import made the latest change.
        $this->import($file)->assertSuccessful();

        BlogPostRevision::query()->forceCreate([
            'post_id' => $post->id,
            'snapshot' => [],
            'type' => BlogPostRevision::TYPE_MANUAL,
            'created_by' => $this->author->id,
            'created_at' => now()->addMinute(),
        ]);

        $this->import($file)
            ->expectsOutputToContain(__('dixlase-blog::import.changed_in_admin', ['slug' => 'hand-written']))
            ->assertFailed();

        $this->import($file, ['--overwrite' => true])->assertSuccessful();
    }

    public function test_draft_saves_a_draft_whatever_the_file_says(): void
    {
        $published = $this->postFile("---\ntitle: Published\nslug: says-published\nstatus: published\n---\n\nBody");
        $scheduled = $this->postFile("---\ntitle: Scheduled\nslug: says-scheduled\nstatus: scheduled\npublished_at: 2030-01-01 09:00\n---\n\nBody");

        $this->import($published, ['--draft' => true, '--dry-run' => true])
            ->expectsOutputToContain(__('dixlase-blog::import.would_status', ['status' => 'draft']))
            ->assertSuccessful();

        $this->import($published, ['--draft' => true])->assertSuccessful();
        $this->import($scheduled, ['--draft' => true])->assertSuccessful();

        $this->assertSame(ContentStatus::DRAFT, BlogPost::where('slug', 'says-published')->sole()->status);
        $this->assertSame(ContentStatus::DRAFT, BlogPost::where('slug', 'says-scheduled')->sole()->status);
    }

    public function test_draft_leaves_a_live_post_alone(): void
    {
        $file = $this->postFile("---\ntitle: Live\nslug: live-post\nstatus: published\n---\n\nBody");
        $this->import($file)->assertSuccessful();

        $edited = $this->postFile("---\ntitle: Edited\nslug: live-post\n---\n\nEdited body");
        $this->import($edited, ['--draft' => true])
            ->expectsOutputToContain(__('dixlase-blog::import.draft_live_post', ['slug' => 'live-post']))
            ->assertFailed();

        $post = BlogPost::sole();
        $this->assertSame(ContentStatus::PUBLISHED, $post->status);
        $this->assertSame('Live', $post->title);
    }

    public function test_several_files_and_a_directory_import_in_one_run(): void
    {
        File::ensureDirectoryExists($this->dir.'/batch');
        File::put($this->dir.'/batch/b.md', "---\ntitle: B\nslug: post-b\n---\n\nB");
        File::put($this->dir.'/batch/a.md', "---\ntitle: A\nslug: post-a\n---\n\nA");
        File::put($this->dir.'/batch/notes.txt', 'not a post');
        $single = $this->postFile("---\ntitle: C\nslug: post-c\n---\n\nC");

        $this->artisan('dls:blog:post:import', ['paths' => [$this->dir.'/batch', $single], '--as' => 'author@example.com', '--force' => true])
            ->expectsOutputToContain(__('dixlase-blog::import.summary', ['ok' => 3, 'failed' => 0]))
            ->assertSuccessful();

        $this->assertSame(['post-a', 'post-b', 'post-c'], BlogPost::query()->orderBy('id')->pluck('slug')->all());
    }

    public function test_a_failing_file_does_not_stop_the_others(): void
    {
        $good = $this->postFile("---\ntitle: Good\nslug: good\n---\n\nBody");
        $bad = $this->postFile("---\ntitle: Bad\ncategories: [missing]\n---\n\nBody");

        $this->artisan('dls:blog:post:import', ['paths' => [$bad, $good], '--as' => 'author@example.com', '--force' => true])
            ->expectsOutputToContain(__('dixlase-blog::import.unknown_categories', ['slugs' => 'missing']))
            ->expectsOutputToContain(__('dixlase-blog::import.summary', ['ok' => 1, 'failed' => 1]))
            ->assertFailed();

        $this->assertSame(['good'], BlogPost::query()->pluck('slug')->all());
    }

    public function test_json_lists_a_result_per_file(): void
    {
        $good = $this->postFile("---\ntitle: Good\nslug: good\n---\n\nBody");
        File::ensureDirectoryExists($this->dir.'/empty');

        Artisan::call('dls:blog:post:import', ['paths' => [$good, $this->dir.'/empty'], '--as' => 'author@example.com', '--json' => true, '--force' => true]);
        $result = json_decode(Artisan::output(), true);

        $this->assertFalse($result['ok']);
        $this->assertCount(2, $result['results']);
        $this->assertSame($good, $result['results'][0]['file']);
        $this->assertTrue($result['results'][0]['ok']);
        $this->assertSame('good', $result['results'][0]['post']['slug']);
        $this->assertFalse($result['results'][1]['ok']);
        $this->assertSame([__('dixlase-blog::import.no_markdown_files', ['path' => $this->dir.'/empty'])], $result['results'][1]['errors']);
    }

    public function test_the_author_is_named_by_account_name_or_e_mail_not_id(): void
    {
        $file = $this->postFile("---\ntitle: Named\nslug: named\n---\n\nBody");

        $this->artisan('dls:blog:post:import', ['paths' => [$file], '--as' => 'author', '--force' => true])->assertSuccessful();
        $this->assertSame($this->author->id, (int) BlogPost::sole()->user_id);

        $id = (string) $this->author->id;
        $this->artisan('dls:blog:post:import', ['paths' => [$file], '--as' => $id, '--force' => true])
            ->expectsOutputToContain(__('dixlase-blog::import.member_not_found', ['identifier' => $id]))
            ->assertFailed();

        // A name of digits only is an account name, whatever IDs exist.
        $digits = Member::factory()->create(['role' => MemberRole::ADMIN, 'status' => 1, 'account_name' => $id.'0']);
        $other = $this->postFile("---\ntitle: Digits\nslug: digits\n---\n\nBody");
        $this->artisan('dls:blog:post:import', ['paths' => [$other], '--as' => $id.'0', '--force' => true])->assertSuccessful();
        $this->assertSame($digits->id, (int) BlogPost::where('slug', 'digits')->sole()->user_id);
    }

    /**
     * An SEO provider that keeps the meta in memory
     */
    public function test_custom_css_is_read_from_a_file_and_kept_when_left_out(): void
    {
        File::put($this->dir.'/post.css', '.post { color: red; }');
        $file = $this->postFile("---\ntitle: Styled\nslug: styled\ncustom_css: ./post.css\n---\n\nBody");

        $this->import($file)->assertSuccessful();
        $post = BlogPost::sole();
        $this->assertSame(ContentEditorType::MARKDOWN, $post->editor_type);
        $this->assertSame('.post { color: red; }', $post->custom_css);

        // Left out, the CSS stays; null clears it.
        File::put($file, "---\ntitle: Styled again\nslug: styled\n---\n\nBody");
        $this->import($file, ['--overwrite' => true])->assertSuccessful();
        $this->assertSame('.post { color: red; }', $post->fresh()->custom_css);

        File::put($file, "---\ntitle: Styled again\nslug: styled\ncustom_css: null\n---\n\nBody");
        $this->import($file, ['--overwrite' => true])->assertSuccessful();
        $this->assertNull($post->fresh()->custom_css);
    }

    public function test_an_html_post_takes_its_css_js_and_body_images(): void
    {
        $this->useAdvancedMode();
        $this->image('images/figure.png', 30, 20);
        File::put($this->dir.'/post.css', 'p { margin: 0; }');
        File::put($this->dir.'/post.js', 'console.log(1);');
        $file = $this->postFile(<<<'MD'
            ---
            title: Html post
            slug: html-post
            editor: html
            custom_css: ./post.css
            custom_js: ./post.js
            ---

            <p>Intro</p>
            <img alt="A figure" src="./images/figure.png">
            <img src="https://example.com/remote.png">
            MD);

        $this->import($file)->assertSuccessful();

        $post = BlogPost::sole();
        $this->assertSame(ContentEditorType::HTML, $post->editor_type);
        $this->assertSame('p { margin: 0; }', $post->custom_css);
        $this->assertSame('console.log(1);', $post->custom_js);
        $figure = Media::where('name', 'figure.png')->sole();
        $this->assertStringContainsString('src="/storage/'.config('admin.files.mediaPath', 'media').'/'.$figure->path.'"', (string) $post->content);
        $this->assertStringContainsString('src="https://example.com/remote.png"', (string) $post->content);
        $this->assertStringNotContainsString('./images/figure.png', (string) $post->content);
    }

    public function test_custom_js_is_refused_on_a_markdown_post(): void
    {
        File::put($this->dir.'/post.js', 'alert(1);');
        $file = $this->postFile("---\ntitle: Script\ncustom_js: ./post.js\n---\n\nBody");

        $this->import($file)
            ->expectsOutputToContain(__('dixlase-blog::import.custom_js_html_only'))
            ->assertFailed();
        $this->assertSame(0, BlogPost::count());
    }

    public function test_css_js_and_html_need_a_member_who_may_author_scripts(): void
    {
        $this->useAdvancedMode();
        Member::factory()->create(['role' => MemberRole::EDITOR, 'status' => 1, 'account_name' => 'writer']);
        File::put($this->dir.'/post.css', 'p {}');

        $css = $this->postFile("---\ntitle: Css\ncustom_css: ./post.css\n---\n\nBody");
        $this->import($css, ['--as' => 'writer'])
            ->expectsOutputToContain(__('dixlase-blog::import.custom_assets_not_allowed', ['keys' => 'custom_css']))
            ->assertFailed();

        $html = $this->postFile("---\ntitle: Html\nslug: html-post\neditor: html\n---\n\n<p>Body</p>");
        $this->import($html, ['--as' => 'writer'])->assertFailed();

        $this->assertSame(0, BlogPost::count());

        // An HTML post made by an admin cannot be changed by the writer.
        $this->import($html)->assertSuccessful();
        $this->import($html, ['--as' => 'writer', '--overwrite' => true])
            ->expectsOutputToContain(__('dixlase-blog::import.html_not_allowed', ['slug' => 'html-post']))
            ->assertFailed();
    }

    public function test_a_saved_post_keeps_its_editor(): void
    {
        $file = $this->postFile("---\ntitle: Fixed\nslug: fixed\n---\n\nBody");
        $this->import($file)->assertSuccessful();

        File::put($file, "---\ntitle: Fixed\nslug: fixed\neditor: html\n---\n\n<p>Body</p>");
        $this->import($file)
            ->expectsOutputToContain(__('dixlase-blog::import.editor_fixed', ['slug' => 'fixed', 'editor' => 'markdown', 'named' => 'html']))
            ->assertFailed();

        $this->assertSame(ContentEditorType::MARKDOWN, BlogPost::sole()->editor_type);
    }

    public function test_an_unknown_editor_and_a_missing_asset_save_nothing(): void
    {
        $unknown = $this->postFile("---\ntitle: Gui\neditor: gui\n---\n\nBody");
        $this->import($unknown)
            ->expectsOutputToContain(__('dixlase-blog::import.invalid_editor', ['editor' => 'gui', 'editors' => 'markdown, html']))
            ->assertFailed();

        $missing = $this->postFile("---\ntitle: Missing\ncustom_css: ./missing.css\n---\n\nBody");
        $this->import($missing)
            ->expectsOutputToContain(__('dixlase-blog::import.asset_not_found', ['key' => 'custom_css', 'path' => './missing.css']))
            ->assertFailed();

        $this->assertSame(0, BlogPost::count());
    }

    public function test_a_dry_run_with_css_writes_nothing(): void
    {
        File::put($this->dir.'/post.css', 'p {}');
        $file = $this->postFile("---\ntitle: Dry\ncustom_css: ./post.css\n---\n\nBody");

        $this->import($file, ['--dry-run' => true])->assertSuccessful();

        $this->assertSame(0, BlogPost::count());
    }

    private function fakeSeo(): object
    {
        $provider = new class implements SeoMetaProviderInterface
        {
            /** @var array<string, SeoMetaDTO> */
            public array $meta = [];

            public function getMeta(string $pluginSlug, string $entityId): ?SeoMetaDTO
            {
                return $this->meta[$entityId] ?? null;
            }

            public function saveMeta(string $pluginSlug, string $entityId, SeoMetaDTO $meta): void
            {
                $this->meta[$entityId] = $meta;
            }

            public function deleteMeta(string $pluginSlug, string $entityId): void
            {
                unset($this->meta[$entityId]);
            }

            public function purgeByPlugin(string $pluginSlug): int
            {
                return 0;
            }

            public function isEnabledForPlugin(string $pluginSlug): bool
            {
                return true;
            }
        };
        $this->app->instance(SeoMetaProviderInterface::class, $provider);

        return $provider;
    }

    /**
     * @param  array<string, mixed>  $options
     */
    private function import(string $file, array $options = []): \Illuminate\Testing\PendingCommand
    {
        return $this->artisan('dls:blog:post:import', array_replace(['paths' => [$file], '--as' => 'author@example.com', '--force' => true], $options));
    }

    private function postFile(string $markdown): string
    {
        $path = $this->dir.'/post-'.uniqid().'.md';
        File::put($path, $markdown);

        return $path;
    }

    private function image(string $relative, int $width = 10, int $height = 10): void
    {
        // Held in a variable: the fake's temporary file goes when it does.
        $fake = UploadedFile::fake()->image(basename($relative), $width, $height);
        File::copy($fake->getPathname(), $this->dir.'/'.$relative);
    }
}
