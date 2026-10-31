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

use App\Enums\MemberRole;
use App\Helpers\AdminHelper;
use App\Http\Middleware\EnsurePluginAdminAccess;
use App\Models\Member;
use App\Services\PermissionRegistry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

/**
 * config/admin/roles.php lets editors write posts, keeps the actions that
 * cannot be taken back with ADMIN, and declares every admin route so none
 * falls back to core's ADMIN default.
 */
class BlogAdminRolesTest extends TestCase
{
    use RefreshDatabase;

    private const ADMIN_ONLY = [
        'dixlase-blog::admin.blog.forceDelete',
        'dixlase-blog::admin.blog.categories.destroy',
        'dixlase-blog::admin.blog.tags.destroy',
        'dixlase-blog::admin.blog.revisions.protect',
        'dixlase-blog::admin.blog.settings.edit',
        'dixlase-blog::admin.blog.settings.listing',
        'dixlase-blog::admin.blog.settings.sidebar',
        'dixlase-blog::admin.blog.settings.share',
        'dixlase-blog::admin.blog.settings.comments',
        'dixlase-blog::admin.blog.settings.storage',
        'dixlase-blog::admin.blog.settings.update',
        'dixlase-blog::admin.blog.comments.forceDelete',
        'dixlase-blog::admin.blog.comments.personalData.index',
        'dixlase-blog::admin.blog.comments.personalData.erase',
    ];

    protected function setUp(): void
    {
        parent::setUp();

        PermissionRegistry::clearCache();
        Route::group([], base_path('plugins/DixlaseBlog/routes/admin.php'));
        Route::getRoutes()->refreshNameLookups();
    }

    public function test_every_admin_route_has_its_own_declaration(): void
    {
        $names = $this->adminRouteNames();
        $this->assertNotEmpty($names);

        foreach ($names as $name) {
            $key = substr($name, strlen('dixlase-blog::admin.'));
            $this->assertTrue(
                PermissionRegistry::hasPluginDefinition('DixlaseBlog', $key),
                "{$name} is not declared in config/admin/roles.php",
            );

            $expected = in_array($name, self::ADMIN_ONLY, true) ? MemberRole::ADMIN : MemberRole::EDITOR;
            $effective = PermissionRegistry::getPluginEffective('DixlaseBlog', $key);
            $this->assertSame($expected->value, $effective['access_roles'], $name);
        }
    }

    public function test_an_editor_can_write_and_trash_posts(): void
    {
        $this->actingAs($this->member(MemberRole::EDITOR));

        foreach ([
            'dixlase-blog::admin.blog.index',
            'dixlase-blog::admin.blog.create',
            'dixlase-blog::admin.blog.store',
            'dixlase-blog::admin.blog.update',
            'dixlase-blog::admin.blog.publishFile',
            'dixlase-blog::admin.blog.preview',
            'dixlase-blog::admin.blog.destroy',
            'dixlase-blog::admin.blog.restore',
            'dixlase-blog::admin.blog.categories.store',
            'dixlase-blog::admin.blog.tags.index',
            'dixlase-blog::admin.blog.tags.store',
            'dixlase-blog::admin.blog.tags.update',
            'dixlase-blog::admin.blog.previewPage',
            'dixlase-blog::admin.blog.previewForm',
            'dixlase-blog::admin.blog.categories.outdent',
            'dixlase-blog::admin.blog.revisions.restore',
            'dixlase-blog::admin.blog.comments.index',
            'dixlase-blog::admin.blog.comments.status',
            'dixlase-blog::admin.blog.comments.bulk',
            'dixlase-blog::admin.blog.comments.reply',
            'dixlase-blog::admin.blog.comments.destroy',
            'dixlase-blog::admin.blog.comments.restore',
        ] as $name) {
            $this->assertTrue($this->passes($name), "EDITOR was refused {$name}");
        }
    }

    public function test_an_editor_cannot_take_irreversible_actions(): void
    {
        $this->actingAs($this->member(MemberRole::EDITOR));

        foreach (self::ADMIN_ONLY as $name) {
            $this->assertFalse($this->passes($name), "EDITOR was allowed {$name}");
        }
    }

    public function test_an_admin_can_take_irreversible_actions(): void
    {
        $this->actingAs($this->member(MemberRole::ADMIN));

        foreach (self::ADMIN_ONLY as $name) {
            $this->assertTrue($this->passes($name), "ADMIN was refused {$name}");
        }
    }

    public function test_a_contributor_cannot_open_the_blog_admin(): void
    {
        $this->actingAs($this->member(MemberRole::CONTRIBUTOR));

        $this->assertFalse($this->passes('dixlase-blog::admin.blog.index'));
        $this->assertFalse($this->passes('dixlase-blog::admin.blog.store'));
    }

    public function test_the_sidebar_shows_the_blog_menu_to_editors(): void
    {
        $nav = (require base_path('plugins/DixlaseBlog/config/admin.php'))['nav']['blog'];
        $this->assertSame('DixlaseBlog', $nav['plugin_slug']);

        $this->actingAs($this->member(MemberRole::EDITOR));

        foreach (['index', 'create', 'categories', 'tags', 'trash'] as $child) {
            $this->assertArrayHasKey($child, $nav['children']);
            $this->assertTrue(AdminHelper::canViewMenuOrPlugin('DixlaseBlog', "blog.{$child}"), "blog.{$child}");
        }
    }

    public function test_the_settings_menu_is_for_admins_only(): void
    {
        $nav = (require base_path('plugins/DixlaseBlog/config/admin.php'))['nav']['blog'];
        $this->assertSame(
            ['dixlase-blog::admin.blog.settings.listing', 'dixlase-blog::admin.blog.settings.sidebar', 'dixlase-blog::admin.blog.settings.share', 'dixlase-blog::admin.blog.settings.comments', 'dixlase-blog::admin.blog.settings.storage'],
            array_values(array_column($nav['children']['settings']['children'], 'route')),
        );

        $this->actingAs($this->member(MemberRole::EDITOR));
        $this->assertFalse(AdminHelper::canViewMenuOrPlugin('DixlaseBlog', 'blog.settings'));

        $this->actingAs($this->member(MemberRole::ADMIN));
        $this->assertTrue(AdminHelper::canViewMenuOrPlugin('DixlaseBlog', 'blog.settings'));
    }

    /**
     * Run core's plugin.admin.access middleware against the named route.
     */
    private function passes(string $name): bool
    {
        $route = Route::getRoutes()->getByName($name);
        $this->assertNotNull($route, $name);

        $uri = preg_replace('/\{[^}]+\}/', '1', $route->uri());
        $method = array_values(array_diff($route->methods(), ['HEAD']))[0];

        $request = Request::create('/'.ltrim((string) $uri, '/'), $method);
        $route->bind($request);
        $request->setRouteResolver(fn () => $route);

        try {
            (new EnsurePluginAdminAccess())->handle($request, fn () => response('ok'), 'DixlaseBlog');
        } catch (HttpException $e) {
            $this->assertSame(403, $e->getStatusCode());

            return false;
        }

        return true;
    }

    /**
     * @return list<string>
     */
    private function adminRouteNames(): array
    {
        return array_values(array_filter(
            array_keys(Route::getRoutes()->getRoutesByName()),
            fn (string $name) => str_starts_with($name, 'dixlase-blog::admin.'),
        ));
    }

    private function member(MemberRole $role): Member
    {
        return Member::factory()->create(['role' => $role, 'status' => 1]);
    }
}
