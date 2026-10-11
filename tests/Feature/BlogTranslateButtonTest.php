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
use App\Http\Middleware\CheckInstallationReady;
use App\Models\Member;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Route;
use Plugins\DixlaseBlog\App\Models\BlogPost;
use Tests\TestCase;

/**
 * The post edit screen embeds DixlaseMultilingual's translate button when
 * that plugin is present, and still renders when it is not.
 */
class BlogTranslateButtonTest extends TestCase
{
    use RefreshDatabase;

    private ?string $stubDir = null;

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
        Route::getRoutes()->refreshNameLookups();
        Route::getRoutes()->refreshActionLookups();

        // CI has no INSTALLED flag, and this global middleware would send
        // every request to the installer.
        $this->withoutMiddleware(CheckInstallationReady::class);

        $this->actingAs(Member::factory()->create(['role' => MemberRole::ADMIN, 'status' => 1]));
    }

    protected function tearDown(): void
    {
        if ($this->stubDir !== null) {
            File::deleteDirectory($this->stubDir);
        }

        parent::tearDown();
    }

    public function test_the_edit_screen_embeds_the_multilingual_button(): void
    {
        $this->fakeMultilingualButton();
        $post = $this->makePost('ja');

        $this->get(route('dixlase-blog::admin.blog.edit', ['post' => $post->id]))
            ->assertOk()
            ->assertSee('translate-button-stub type=dixlase-blog:post id='.$post->id.' source=ja', false);
    }

    public function test_the_edit_screen_renders_without_multilingual(): void
    {
        $this->assertFalse(view()->exists('dixlase-multilingual::components.translate-button'));
        $post = $this->makePost('en');

        $this->get(route('dixlase-blog::admin.blog.edit', ['post' => $post->id]))
            ->assertOk()
            ->assertDontSee('translate-button-stub', false);
    }

    /**
     * Stand in for DixlaseMultilingual's anonymous component, under a
     * throwaway directory rather than the real plugin.
     */
    private function fakeMultilingualButton(): void
    {
        $this->stubDir = storage_path('framework/testing/blog-translate-button-'.uniqid());
        File::ensureDirectoryExists($this->stubDir.'/components');
        File::put(
            $this->stubDir.'/components/translate-button.blade.php',
            "@props(['type', 'id' => null, 'sourceLocale' => null])\n<span>translate-button-stub type={{ \$type }} id={{ \$id }} source={{ \$sourceLocale }}</span>\n",
        );

        // <x-dixlase-multilingual::translate-button> resolves to the view
        // dixlase-multilingual::components.translate-button.
        $this->app['view']->addNamespace('dixlase-multilingual', $this->stubDir);
    }

    private function makePost(string $lang): BlogPost
    {
        return BlogPost::create([
            'title' => 'Hello',
            'slug' => 'hello',
            'content' => 'Body',
            'editor_type' => ContentEditorType::MARKDOWN,
            'status' => ContentStatus::DRAFT,
            'lang' => $lang,
        ]);
    }
}
