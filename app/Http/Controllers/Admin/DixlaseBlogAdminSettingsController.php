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

namespace Plugins\DixlaseBlog\App\Http\Controllers\Admin;

use App\Traits\AdminInterfaceTrait;
use App\Traits\AdminLoggedInTrait;
use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Controller;
use Illuminate\View\View;
use Plugins\DixlaseBlog\App\Http\Requests\Admin\BlogSettingsUpdateRequest;
use Plugins\DixlaseBlog\App\Support\BlogSettings;

class DixlaseBlogAdminSettingsController extends Controller
{
    use AdminInterfaceTrait;
    use AdminLoggedInTrait;

    // Not $settings: AdminInterfaceTrait owns that property (the site's
    // settings) and the view variable of the same name.
    public function __construct(private readonly BlogSettings $blogSettings)
    {
        $this->initialize();
        $this->initializeAfterLogin();
    }

    public function edit(): View
    {
        return view('dixlase-blog::admin.settings.edit', array_merge($this->viewParams, [
            'blogSettings' => $this->blogSettings->all(),
            'postsPerPageMin' => BlogSettings::POSTS_PER_PAGE_MIN,
            'postsPerPageMax' => BlogSettings::POSTS_PER_PAGE_MAX,
            // Labels resolved here, as admin views take no logic.
            'sidebarWidgets' => array_map(
                fn (array $widget) => $widget + ['label' => __('dixlase-blog::admin.settings.sidebar_widget_'.$widget['key'])],
                $this->blogSettings->sidebarWidgets(),
            ),
            'sidebarPositionOptions' => $this->sidebarPositionOptions(),
            'shareServices' => array_map(
                fn (string $key) => [
                    'key' => $key,
                    'label' => __('dixlase-blog::admin.settings.share_service_'.$key),
                    'enabled' => in_array($key, $this->blogSettings->shareServices(), true),
                ],
                BlogSettings::SHARE_SERVICES,
            ),
            'recentPostsMin' => BlogSettings::RECENT_POSTS_MIN,
            'recentPostsMax' => BlogSettings::RECENT_POSTS_MAX,
        ]));
    }

    /**
     * @return list<array{value: string, label: string, icon: string}>
     */
    private function sidebarPositionOptions(): array
    {
        return [
            ['value' => 'right', 'label' => __('dixlase-blog::admin.settings.sidebar_position_right'), 'icon' => 'fas fa-table-columns'],
            ['value' => 'left', 'label' => __('dixlase-blog::admin.settings.sidebar_position_left'), 'icon' => 'fas fa-table-columns fa-flip-horizontal'],
        ];
    }

    public function update(BlogSettingsUpdateRequest $request): RedirectResponse
    {
        $this->blogSettings->save($request->settingsValues());

        return redirect()
            ->route('dixlase-blog::admin.blog.settings.edit')
            ->with('success', __('dixlase-blog::admin.flash.settings_saved'));
    }
}
