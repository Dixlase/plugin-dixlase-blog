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
use App\Enums\MemberRole;
use App\Models\Member;
use App\Models\SiteSetting;
use Illuminate\Contracts\Console\Kernel as ConsoleKernel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Plugins\DixlaseBlog\App\Console\Commands\ExportPost;
use Plugins\DixlaseBlog\App\Console\Commands\ImportPost;
use Plugins\DixlaseBlog\App\Models\BlogCategory;
use Plugins\DixlaseBlog\App\Models\BlogPost;
use Plugins\DixlaseBlog\App\Models\BlogPostRevision;
use Plugins\DixlaseBlog\App\Services\BlogPostService;
use Tests\TestCase;

/**
 * `dls:blog:post:export` writes a post as the file the import reads (#235)
 */
class BlogPostExportTest extends TestCase
{
    use RefreshDatabase;

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
        $kernel = $this->app->make(ConsoleKernel::class);
        $kernel->registerCommand($this->app->make(ImportPost::class));
        $kernel->registerCommand($this->app->make(ExportPost::class));

        Member::factory()->create(['role' => MemberRole::ADMIN, 'status' => 1, 'email' => 'author@example.com']);
        BlogCategory::create(['label' => 'News', 'slug' => 'news', 'display_order' => 10]);

        $this->dir = storage_path('framework/testing/blog-export-'.uniqid());
        File::ensureDirectoryExists($this->dir);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->dir);

        parent::tearDown();
    }

    public function test_an_exported_file_imports_back_without_changing_the_post(): void
    {
        $post = $this->importPost(<<<'MD'
            ---
            title: "Quotes \" and: colons # not a comment"
            slug: round-trip
            lang: en
            status: published
            published_at: 2026-11-10 09:30:15
            categories: [news]
            tags: [dixlase, "a, b"]
            excerpt: "Line one\nline two"
            is_pinned: true
            comment_status: closed
            ---

            # Heading

            Body with ---

            ---

            And a rule above.
            MD);
        $content = $post->content;
        $token = app(BlogPostService::class)->versionToken($post);
        $revisions = BlogPostRevision::query()->where('post_id', $post->id)->count();

        $file = $this->dir.'/round-trip.md';
        $this->export('round-trip', ['--output' => $file])->assertSuccessful();
        $this->assertStringContainsString('revision: '.$token, File::get($file));

        $this->import($file)->assertSuccessful();

        $post = BlogPost::sole();
        $this->assertSame($content, $post->content);
        $this->assertSame($token, app(BlogPostService::class)->versionToken($post));
        $this->assertSame($revisions, BlogPostRevision::query()->where('post_id', $post->id)->count());
        $this->assertSame('Quotes " and: colons # not a comment', $post->title);
        $this->assertSame(ContentStatus::PUBLISHED, $post->status);
        $this->assertEqualsCanonicalizing(['dixlase', 'a, b'], $post->tags->pluck('name')->all());
    }

    public function test_it_prints_to_standard_output_by_default(): void
    {
        $this->importPost("---\ntitle: Printed\nslug: printed\n---\n\nBody");

        Artisan::call('dls:blog:post:export', ['post' => 'printed']);
        $output = Artisan::output();

        $this->assertStringStartsWith("---\ntitle: \"Printed\"\nslug: \"printed\"\n", $output);
        $this->assertStringEndsWith("---\n\nBody", $output);
    }

    public function test_it_finds_a_post_by_id_and_refuses_an_unknown_one(): void
    {
        $post = $this->importPost("---\ntitle: By id\nslug: by-id\n---\n\nBody");

        $this->export((string) $post->id)->assertSuccessful();
        $this->export('missing')
            ->expectsOutputToContain(__('dixlase-blog::export.post_not_found', ['identifier' => 'missing']))
            ->assertFailed();
    }

    public function test_it_does_not_overwrite_a_file_without_force(): void
    {
        $this->importPost("---\ntitle: Kept\nslug: kept\n---\n\nBody");
        $file = $this->dir.'/kept.md';
        File::put($file, 'mine');

        $this->export('kept', ['--output' => $file])
            ->expectsOutputToContain(__('dixlase-blog::export.file_exists', ['path' => $file]))
            ->assertFailed();
        $this->assertSame('mine', File::get($file));

        $this->export('kept', ['--output' => $file, '--force' => true])->assertSuccessful();
        $this->assertStringStartsWith('---', File::get($file));
    }

    public function test_a_post_without_a_text_body_is_refused(): void
    {
        $post = $this->importPost("---\ntitle: Blocks\nslug: gui-post\n---\n\nBody");
        $post->forceFill(['editor_type' => ContentEditorType::GUI])->saveQuietly();

        $this->export('gui-post')
            ->expectsOutputToContain(__('dixlase-blog::export.editor_not_exportable', ['slug' => 'gui-post', 'editor' => 'gui']))
            ->assertFailed();
    }

    public function test_a_markdown_post_with_css_round_trips_through_files_next_to_it(): void
    {
        File::put($this->dir.'/source.css', ".post {\r\n  color: red;\r\n}");
        $post = $this->importPost("---\ntitle: Styled\nslug: styled\ncustom_css: ./source.css\n---\n\nBody");
        $token = app(BlogPostService::class)->versionToken($post);
        $revisions = BlogPostRevision::query()->where('post_id', $post->id)->count();

        $file = $this->dir.'/out/styled.md';
        $this->export('styled', ['--output' => $file])->assertSuccessful();

        $markdown = File::get($file);
        $this->assertStringContainsString("editor: markdown\n", $markdown);
        $this->assertStringContainsString('custom_css: "./styled.css"', $markdown);
        $this->assertStringNotContainsString('custom_js', $markdown);
        $this->assertSame(".post {\r\n  color: red;\r\n}", File::get($this->dir.'/out/styled.css'));

        $this->import($file)->assertSuccessful();
        $this->assertSame($token, app(BlogPostService::class)->versionToken($post->fresh()));
        $this->assertSame($revisions, BlogPostRevision::query()->where('post_id', $post->id)->count());
    }

    public function test_an_html_post_round_trips_with_its_css_and_js(): void
    {
        SiteSetting::setValue('admin_mode', '1');
        File::put($this->dir.'/source.css', 'p { margin: 0; }');
        File::put($this->dir.'/source.js', 'console.log(1);');
        $post = $this->importPost("---\ntitle: Html\nslug: html-post\neditor: html\ncustom_css: ./source.css\ncustom_js: ./source.js\n---\n\n<p>Body</p>");
        $token = app(BlogPostService::class)->versionToken($post);

        $file = $this->dir.'/out/html-post.md';
        $this->export('html-post', ['--output' => $file])->assertSuccessful();

        $markdown = File::get($file);
        $this->assertStringContainsString("editor: html\n", $markdown);
        $this->assertStringContainsString('custom_js: "./html-post.js"', $markdown);
        $this->assertStringEndsWith("---\n\n<p>Body</p>", $markdown);
        $this->assertSame('console.log(1);', File::get($this->dir.'/out/html-post.js'));

        $this->import($file)->assertSuccessful();
        $post = $post->fresh();
        $this->assertSame($token, app(BlogPostService::class)->versionToken($post));
        $this->assertSame('p { margin: 0; }', $post->custom_css);
        $this->assertSame('console.log(1);', $post->custom_js);
    }

    public function test_standard_output_leaves_the_css_out(): void
    {
        File::put($this->dir.'/source.css', 'p {}');
        $this->importPost("---\ntitle: Printed\nslug: printed\ncustom_css: ./source.css\n---\n\nBody");

        Artisan::call('dls:blog:post:export', ['post' => 'printed']);

        // The note about it goes to standard error, which a test buffer shares.
        $this->assertStringNotContainsString('custom_css: ', Artisan::output());
    }

    public function test_an_existing_css_file_is_not_overwritten_without_force(): void
    {
        File::put($this->dir.'/source.css', 'p {}');
        $this->importPost("---\ntitle: Kept\nslug: kept\ncustom_css: ./source.css\n---\n\nBody");
        File::put($this->dir.'/kept.css', 'mine');

        $this->export('kept', ['--output' => $this->dir.'/kept.md'])
            ->expectsOutputToContain(__('dixlase-blog::export.file_exists', ['path' => $this->dir.'/kept.css']))
            ->assertFailed();
        $this->assertSame('mine', File::get($this->dir.'/kept.css'));
        $this->assertFalse(File::exists($this->dir.'/kept.md'));
    }

    public function test_it_runs_outside_a_local_environment(): void
    {
        $this->importPost("---\ntitle: Live\nslug: live\n---\n\nBody");

        // The tests run as "testing"; reading needs no confirmation.
        $this->artisan('dls:blog:post:export', ['post' => 'live'])->assertSuccessful();
    }

    private function importPost(string $markdown): BlogPost
    {
        $file = $this->dir.'/source-'.uniqid().'.md';
        File::put($file, $markdown);
        $this->import($file)->assertSuccessful();

        return BlogPost::query()->latest('id')->firstOrFail();
    }

    /**
     * @param  array<string, mixed>  $options
     */
    private function export(string $post, array $options = []): \Illuminate\Testing\PendingCommand
    {
        return $this->artisan('dls:blog:post:export', ['post' => $post] + $options);
    }

    private function import(string $file): \Illuminate\Testing\PendingCommand
    {
        return $this->artisan('dls:blog:post:import', ['paths' => [$file], '--as' => 'author@example.com', '--force' => true]);
    }
}
