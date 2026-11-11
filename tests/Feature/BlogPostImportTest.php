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

        $this->author = Member::factory()->create(['role' => MemberRole::ADMIN, 'status' => 1, 'email' => 'author@example.com']);
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

        Artisan::call('dls:blog:post:import', ['file' => $file, '--as' => 'author@example.com', '--json' => true, '--allow-non-local' => true]);
        $result = json_decode(Artisan::output(), true);

        $this->assertTrue($result['ok']);
        $this->assertSame('create', $result['action']);
        $this->assertSame(BlogPost::sole()->id, $result['post']['id']);
        $this->assertSame('draft', $result['post']['status']);
        $this->assertSame('featured_image', $result['images'][0]['field']);
        $this->assertSame(Media::sole()->id, $result['images'][0]['media_id']);
    }

    public function test_it_refuses_to_run_outside_a_local_environment(): void
    {
        $file = $this->postFile("---\ntitle: Live\n---\n\nBody");

        $this->artisan('dls:blog:post:import', ['file' => $file, '--as' => 'author@example.com'])
            ->expectsOutputToContain(__('dixlase-blog::import.not_local'))
            ->assertFailed();

        $this->assertSame(0, BlogPost::count());
    }

    public function test_it_needs_an_active_author(): void
    {
        $file = $this->postFile("---\ntitle: Nobody\n---\n\nBody");

        $this->artisan('dls:blog:post:import', ['file' => $file, '--allow-non-local' => true])
            ->expectsOutputToContain(__('dixlase-blog::import.member_required'))
            ->assertFailed();

        $this->artisan('dls:blog:post:import', ['file' => $file, '--as' => 'nobody@example.com', '--allow-non-local' => true])
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

        Artisan::call('dls:blog:post:import', ['file' => $file, '--as' => 'author@example.com', '--json' => true, '--allow-non-local' => true, '--storage' => 'file']);
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

    /**
     * @param  array<string, mixed>  $options
     */
    private function import(string $file, array $options = []): \Illuminate\Testing\PendingCommand
    {
        return $this->artisan('dls:blog:post:import', ['file' => $file, '--as' => 'author@example.com', '--allow-non-local' => true] + $options);
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
