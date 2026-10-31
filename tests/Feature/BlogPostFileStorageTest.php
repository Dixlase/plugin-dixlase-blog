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

namespace Plugins\DixlaseBlog\Tests\Feature;

use App\Enums\ContentPublishMode;
use App\Enums\ContentStorageType;
use App\Enums\MemberRole;
use App\Http\Middleware\CheckInstallationReady;
use App\Http\Middleware\CheckMaintenanceMode;
use App\Models\AuditLog;
use App\Models\Member;
use App\Models\SiteSetting;
use App\Services\Content\FileContentPublisher;
use App\Services\Content\FileContentRegistry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\DataProvider;
use Plugins\DixlaseBlog\App\Models\BlogPost;
use Plugins\DixlaseBlog\App\Models\BlogPostRevision;
use Plugins\DixlaseBlog\App\Providers\DixlaseBlogServiceProvider;
use Plugins\DixlaseBlog\App\Services\BlogContentRenderer;
use Plugins\DixlaseBlog\App\Support\BlogSettings;
use Tests\TestCase;

/**
 * A post stored as files is published through core's shared layer (#193).
 *
 * The columns hold the published copy and the files a working copy. A change
 * made to the files outside the admin is recorded once and waits on the edit
 * screen until someone publishes it; under the file save mode it is taken in
 * at once -- in a local environment only, which core decides.
 */
class BlogPostFileStorageTest extends TestCase
{
    use RefreshDatabase;

    private const DIR = 'dixlase-blog/posts';

    private string $themeDirectory;

    protected function setUp(): void
    {
        parent::setUp();

        // Posts stored as files must never write into the real storage tree.
        Storage::fake('local');

        $this->artisan('migrate', [
            '--path' => base_path('plugins/DixlaseBlog/database/migrations'),
            '--realpath' => true,
        ]);

        $this->app['translator']->addNamespace('dixlase-blog', base_path('plugins/DixlaseBlog/lang'));
        $this->app['view']->addNamespace('dixlase-blog', base_path('plugins/DixlaseBlog/resources/views'));
        $this->app['view']->addNamespace('admin', resource_path('views/'.config('themes.admin_theme', 'admin')));
        $this->app['view']->addNamespace('components', resource_path('views/components'));
        $this->app['view']->addNamespace('layouts', resource_path('views/layouts'));

        // The preview frame and the post page extend the theme's layouts.
        $this->themeDirectory = storage_path('framework/testing/blog-file-theme-'.uniqid());
        File::ensureDirectoryExists($this->themeDirectory.'/layouts');
        File::put($this->themeDirectory.'/layouts/preview.blade.php', '<html><body>@yield(\'content\')@stack(\'scripts\')</body></html>');
        File::put($this->themeDirectory.'/layouts/app.blade.php', '<html><title>@yield(\'title\')</title><body>@yield(\'content\')</body></html>');
        $this->app['view']->replaceNamespace('themes', [$this->themeDirectory]);

        Route::middleware('web')->group(base_path('plugins/DixlaseBlog/routes/web.php'));
        Route::middleware('plugin')->prefix('admin-custom')->group(base_path('plugins/DixlaseBlog/routes/admin.php'));
        Route::getRoutes()->refreshNameLookups();
        Route::getRoutes()->refreshActionLookups();

        $this->withoutMiddleware([
            CheckInstallationReady::class,
            CheckMaintenanceMode::class,
        ]);

        // The provider is not booted here; it registers the model so (see
        // the first test).
        app(FileContentRegistry::class)->register(BlogPost::class);

        // Simple mode stores every post in the database; file storage needs
        // the advanced admin mode.
        SiteSetting::setValue('admin_mode', '1');

        $this->actingAs(Member::factory()->create(['role' => MemberRole::ADMIN, 'status' => 1]));
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->themeDirectory);

        parent::tearDown();
    }

    public function test_the_provider_registers_the_post_model_with_core(): void
    {
        $this->app->register(DixlaseBlogServiceProvider::class);

        $this->assertContains(BlogPost::class, app(FileContentRegistry::class)->models());
    }

    public function test_a_post_stored_as_files_writes_its_files(): void
    {
        $post = $this->createPost('Saved body', 'markdown', ['custom_css' => 'p { color: red; }']);

        $this->assertSame(ContentStorageType::FILE, $post->storage_type);
        $this->assertSame('Saved body', $this->file($post, 'content.md'));
        $this->assertSame('p { color: red; }', $this->file($post, 'style.css'));
        // A Markdown post keeps no JavaScript, so it has no file for it.
        $this->assertFalse(Storage::disk('local')->exists(self::DIR.'/'.$post->id.'/script.js'));
        $this->assertSame(0, app(FileContentRegistry::class)->pendingCount());
    }

    public function test_a_file_change_waits_to_be_published_under_the_default_mode(): void
    {
        $post = $this->createPost('Saved body');

        $this->putFile($post, 'content.md', 'Edited on disk');

        $this->assertStringContainsString('Saved body', $this->render($post));
        // A second read of the same change does not record it again.
        $this->assertStringContainsString('Saved body', $this->render($post));

        $logs = $this->logs('dixlase_blog.post.file_changed');
        $this->assertCount(1, $logs);
        $this->assertSame(AuditLog::SEVERITY_WARNING, $logs->first()->severity);
        $this->assertSame(['content'], $logs->first()->context['parts'] ?? null);
        $this->assertFalse($logs->first()->context['published'] ?? null);
        $this->assertSame(1, app(FileContentRegistry::class)->pendingCount());
    }

    public function test_saving_from_the_admin_writes_the_files_and_leaves_nothing_to_publish(): void
    {
        $post = $this->createPost('First body');
        $this->updatePost($post, ['content' => 'Second body']);

        $this->assertSame('Second body', $this->file($post, 'content.md'));
        $this->assertStringContainsString('Second body', $this->render($post));
        $this->assertCount(0, $this->logs('dixlase_blog.post.file_changed'));
        $this->assertSame(0, app(FileContentRegistry::class)->pendingCount());
    }

    public function test_the_edit_screen_shows_the_change_and_edits_the_working_copy(): void
    {
        $post = $this->createPost('Saved body');
        $this->putFile($post, 'content.md', 'Edited on disk');

        $this->get(route('dixlase-blog::admin.blog.edit', ['post' => $post->id]))
            ->assertOk()
            ->assertSee(__('common.file_changes.title'))
            ->assertSee(route('dixlase-blog::admin.blog.publishFile', ['post' => $post->id]), false)
            ->assertSee('Edited on disk')
            ->assertSee('storage/app/private/'.self::DIR.'/'.$post->id, false);
    }

    public function test_publishing_takes_the_file_in_and_records_it(): void
    {
        $post = $this->createPost('Saved body');
        $this->putFile($post, 'content.md', 'Edited on disk');
        $revisions = BlogPostRevision::query()->where('post_id', $post->id)->count();

        $this->post(route('dixlase-blog::admin.blog.publishFile', ['post' => $post->id]))
            ->assertRedirect(route('dixlase-blog::admin.blog.edit', ['post' => $post->id]))
            ->assertSessionHas('success');

        $post->refresh();
        $this->assertSame('Edited on disk', $post->getAttributes()['content']);
        // An excerpt made from the body follows it.
        $this->assertSame('Edited on disk', $post->excerpt);
        $this->assertSame($revisions + 1, BlogPostRevision::query()->where('post_id', $post->id)->count());

        $logs = $this->logs('dixlase_blog.post.published_from_file');
        $this->assertCount(1, $logs);
        $this->assertSame(['content'], $logs->first()->context['parts'] ?? null);
        $this->assertSame(0, app(FileContentRegistry::class)->pendingCount());
    }

    public function test_publishing_keeps_an_excerpt_the_author_wrote(): void
    {
        $post = $this->createPost('Saved body', 'markdown', ['excerpt' => 'Written by hand']);
        $this->putFile($post, 'content.md', 'Edited on disk');

        $this->post(route('dixlase-blog::admin.blog.publishFile', ['post' => $post->id]))->assertSessionHas('success');

        $this->assertSame('Written by hand', $post->refresh()->excerpt);
    }

    public function test_publishing_with_nothing_changed_fails(): void
    {
        $post = $this->createPost('Saved body');

        $this->post(route('dixlase-blog::admin.blog.publishFile', ['post' => $post->id]))
            ->assertRedirect()
            ->assertSessionHas('error', __('common.file_changes.nothing_to_publish'));

        $this->assertCount(0, $this->logs('dixlase_blog.post.published_from_file'));
    }

    public function test_an_editor_cannot_publish_the_files_of_an_html_post(): void
    {
        $post = $this->createPost('<p>Saved</p>', 'html');
        $this->putFile($post, 'content.html', '<p>Edited</p>');

        $this->actingAs(Member::factory()->create(['role' => MemberRole::EDITOR, 'status' => 1]))
            ->post(route('dixlase-blog::admin.blog.publishFile', ['post' => $post->id]))
            ->assertForbidden();

        $this->assertSame('<p>Saved</p>', $post->refresh()->getAttributes()['content']);
    }

    public function test_the_file_save_mode_takes_every_part_of_an_html_post_in_locally(): void
    {
        app(BlogSettings::class)->save(['publish_mode' => ContentPublishMode::FILE_SAVE->value]);
        $post = $this->createPost('<p>Body</p>', 'html', [
            'custom_css' => 'p { color: red; }',
            'custom_js' => 'console.log(1);',
        ]);
        $revisions = BlogPostRevision::query()->where('post_id', $post->id)->count();
        $this->useLocalEnvironment();

        $this->putFile($post, 'content.html', '<p>Edited</p><script>console.log(2);</script>');
        $this->putFile($post, 'style.css', 'p { color: blue; }');
        $this->putFile($post, 'script.js', 'console.log(3);');

        $this->assertStringContainsString('<p>Edited</p>', $this->render($post));
        $attributes = $post->refresh()->getAttributes();
        $this->assertSame('p { color: blue; }', $attributes['custom_css']);
        $this->assertSame('console.log(3);', $attributes['custom_js']);
        $this->assertSame($revisions + 1, BlogPostRevision::query()->where('post_id', $post->id)->count());

        $log = $this->logs('dixlase_blog.post.file_changed')->first();
        $this->assertNotNull($log);
        $this->assertTrue($log->context['published'] ?? null);
        $this->assertEqualsCanonicalizing(['content', 'custom_css', 'custom_js'], $log->context['parts'] ?? null);
        $this->assertSame(0, app(FileContentRegistry::class)->pendingCount());
    }

    /**
     * @return array<string, array{string}>
     */
    public static function nonLocalEnvironments(): array
    {
        return [
            'testing' => ['testing'],
            'staging' => ['staging'],
            'production' => ['production'],
            'a mistyped value' => ['locale'],
        ];
    }

    #[DataProvider('nonLocalEnvironments')]
    public function test_the_file_save_mode_waits_outside_a_local_environment(string $environment): void
    {
        app(BlogSettings::class)->save(['publish_mode' => ContentPublishMode::FILE_SAVE->value]);
        $post = $this->createPost('Saved body');
        $this->app['env'] = $environment;

        $this->putFile($post, 'content.md', 'Edited on disk');

        $this->assertStringContainsString('Saved body', $this->render($post));
        // Core decides the mode in effect; the setting is kept as chosen.
        $this->assertSame(ContentPublishMode::ADMIN, app(FileContentPublisher::class)->effectivePublishMode($post));
        $this->assertSame(ContentPublishMode::FILE_SAVE, $post->fileContentPublishMode());

        $logs = $this->logs('dixlase_blog.post.file_changed');
        $this->assertCount(1, $logs);
        $this->assertFalse($logs->first()->context['published'] ?? null);
        $this->assertSame(1, app(FileContentRegistry::class)->pendingCount());
    }

    public function test_an_html_body_is_declared_script_capable(): void
    {
        $html = $this->createPost('<p>Body</p>', 'html');
        $markdown = $this->createPost('Body', 'markdown', ['title' => 'Other', 'slug' => 'other']);

        $this->assertEqualsCanonicalizing(['custom_css', 'custom_js', 'content'], $html->scriptCapableFileContentParts());
        $this->assertSame(['content', 'custom_css'], array_keys($markdown->fileContentParts()));
    }

    public function test_the_custom_css_waits_as_well(): void
    {
        $post = $this->createPost('Body', 'markdown', ['custom_css' => 'p { color: red; }', 'status' => 'published']);

        $this->putFile($post, 'style.css', 'p { color: blue; }');

        $this->get(route('dixlase-blog::front.custom-style', ['slug' => $post->slug]))
            ->assertOk()
            ->assertSee('p { color: red; }', false);
        $this->assertSame(['custom_css'], $this->logs('dixlase_blog.post.file_changed')->first()?->context['parts']);
    }

    public function test_a_post_moves_between_the_database_and_files_without_losing_its_content(): void
    {
        $post = $this->createPost('Saved body', 'markdown', ['storage_type' => 'database']);
        $this->assertFalse(Storage::disk('local')->exists(self::DIR.'/'.$post->id));

        $this->updatePost($post, ['storage_type' => 'file']);
        $this->assertSame('Saved body', $this->file($post, 'content.md'));

        $this->updatePost($post, ['storage_type' => 'database']);
        $post->refresh();
        $this->assertSame(ContentStorageType::DATABASE, $post->storage_type);
        $this->assertSame('Saved body', $post->getAttributes()['content']);
        $this->assertFalse(Storage::disk('local')->exists(self::DIR.'/'.$post->id));
        $this->assertSame(['checked' => 0, 'pending' => 0], app(FileContentRegistry::class)->checkAll(app(FileContentPublisher::class)));
    }

    public function test_simple_mode_keeps_posts_in_the_database(): void
    {
        SiteSetting::setValue('admin_mode', '0');
        app(BlogSettings::class)->save(['default_storage_type' => 'file']);

        $post = $this->createPost('Saved body');

        $this->assertSame(ContentStorageType::DATABASE, $post->storage_type);
        $this->assertFalse(Storage::disk('local')->exists(self::DIR.'/'.$post->id));
    }

    public function test_a_new_post_takes_the_default_storage_from_the_settings(): void
    {
        app(BlogSettings::class)->save(['default_storage_type' => 'file']);

        $this->post(route('dixlase-blog::admin.blog.store'), [
            'title' => 'Hello',
            'slug' => 'hello',
            'content' => 'Body',
            'editor_type' => 'markdown',
            'status' => 'draft',
        ])->assertSessionHasNoErrors();

        $this->assertSame(ContentStorageType::FILE, BlogPost::query()->where('slug', 'hello')->firstOrFail()->storage_type);
    }

    public function test_restoring_a_revision_replaces_a_waiting_change(): void
    {
        $post = $this->createPost('First body');
        $first = BlogPostRevision::query()->where('post_id', $post->id)->latest('id')->firstOrFail();
        $this->updatePost($post, ['content' => 'Second body']);
        $this->putFile($post, 'content.md', 'Edited on disk');
        $this->render($post);
        $this->assertSame(1, app(FileContentRegistry::class)->pendingCount());

        $this->post(route('dixlase-blog::admin.blog.revisions.restore', ['post' => $post->id, 'revisionId' => $first->id]))
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertSame('First body', $this->file($post, 'content.md'));
        $this->assertSame(0, app(FileContentRegistry::class)->pendingCount());
    }

    public function test_the_storage_screen_says_where_the_file_save_mode_applies(): void
    {
        $this->get(route('dixlase-blog::admin.blog.settings.storage'))
            ->assertOk()
            ->assertSee(__('common.content_publish_mode.not_in_effect'), false)
            ->assertDontSee(__('common.content_publish_mode.in_effect_local'), false);

        $this->useLocalEnvironment();

        $this->get(route('dixlase-blog::admin.blog.settings.storage'))
            ->assertOk()
            ->assertSee(__('common.content_publish_mode.in_effect_local'), false);
    }

    public function test_changing_the_publish_mode_is_audited(): void
    {
        $this->put(route('dixlase-blog::admin.blog.settings.update'), [
            'screen' => 'storage',
            'default_storage_type' => 'file',
            'publish_mode' => ContentPublishMode::FILE_SAVE->value,
        ])->assertRedirect(route('dixlase-blog::admin.blog.settings.storage'));

        $this->assertSame(ContentPublishMode::FILE_SAVE, app(BlogSettings::class)->publishMode());
        $log = AuditLog::query()->where('action', AuditLog::ACTION_SETTINGS_UPDATED)->latest('id')->first();
        $this->assertNotNull($log);
        $this->assertSame('dixlase_blog.settings', $log->context['settings_page'] ?? null);

        // Saving another screen writes no entry.
        $count = AuditLog::query()->where('action', AuditLog::ACTION_SETTINGS_UPDATED)->count();
        $this->put(route('dixlase-blog::admin.blog.settings.update'), ['screen' => 'share', 'share_services' => ['x' => '1']]);
        $this->assertSame($count, AuditLog::query()->where('action', AuditLog::ACTION_SETTINGS_UPDATED)->count());
    }

    /**
     * Run the rest of the test as a local environment
     *
     * Only after the posts are created: the test-only CSRF exemption holds
     * while the environment is `testing`, so a POST made after this is refused.
     */
    private function useLocalEnvironment(): void
    {
        $this->app['env'] = 'local';
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function createPost(string $content, string $editor = 'markdown', array $overrides = []): BlogPost
    {
        $form = array_merge([
            'title' => 'Hello',
            'slug' => 'hello',
            'content' => $content,
            'editor_type' => $editor,
            'storage_type' => 'file',
            'status' => 'draft',
        ], $overrides);

        $this->post(route('dixlase-blog::admin.blog.store'), $form)
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        return BlogPost::query()->where('slug', $form['slug'])->firstOrFail();
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function updatePost(BlogPost $post, array $overrides): void
    {
        $post->refresh();

        $this->put(route('dixlase-blog::admin.blog.update', ['post' => $post->id]), array_merge([
            'title' => $post->title,
            'slug' => $post->slug,
            'content' => $post->getAttributes()['content'],
            'editor_type' => $post->editor_type->slug(),
            'storage_type' => $post->storage_type->slug(),
            'status' => $post->status->slug(),
        ], $overrides))
            ->assertRedirect()
            ->assertSessionHasNoErrors();
    }

    private function render(BlogPost $post): string
    {
        return app(BlogContentRenderer::class)->renderPost($post->refresh());
    }

    private function file(BlogPost $post, string $name): string
    {
        return (string) Storage::disk('local')->get(self::DIR.'/'.$post->id.'/'.$name);
    }

    private function putFile(BlogPost $post, string $name, string $content): void
    {
        Storage::disk('local')->put(self::DIR.'/'.$post->id.'/'.$name, $content);
    }

    /**
     * @return Collection<int, AuditLog>
     */
    private function logs(string $action): Collection
    {
        return AuditLog::query()->where('action', $action)->get();
    }
}
