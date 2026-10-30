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

use App\Helpers\ConfigHelper;
use App\Traits\AdminInterfaceTrait;
use App\Traits\AdminLoggedInTrait;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Routing\Controller;
use Illuminate\View\View;
use Plugins\DixlaseBlog\App\Http\Requests\Admin\BlogSettingsUpdateRequest;
use Plugins\DixlaseBlog\App\Services\BlogComments;
use Plugins\DixlaseBlog\App\Support\BlogSettings;

class DixlaseBlogAdminSettingsController extends Controller
{
    use AdminInterfaceTrait;
    use AdminLoggedInTrait;

    // Not $settings: AdminInterfaceTrait owns that property (the site's
    // settings) and the view variable of the same name.
    public function __construct(
        private readonly BlogSettings $blogSettings,
        private readonly BlogComments $comments,
    ) {
        $this->initialize();
        $this->initializeAfterLogin();
    }

    /**
     * The address the settings used to live at, kept for old links.
     */
    public function edit(): RedirectResponse
    {
        return redirect()->route('dixlase-blog::admin.blog.settings.listing');
    }

    public function listing(): View
    {
        return $this->screen('listing', [
            'siteUrl' => rtrim(url('/'), '/'),
            'routeSlugMax' => BlogSettings::ROUTE_SLUG_MAX,
            'postsPerPageMin' => BlogSettings::POSTS_PER_PAGE_MIN,
            'postsPerPageMax' => BlogSettings::POSTS_PER_PAGE_MAX,
            'indexLayoutOptions' => [
                ['value' => 'cards', 'label' => __('dixlase-blog::admin.settings.index_layout_cards'), 'icon' => 'fas fa-table-cells-large'],
                ['value' => 'full', 'label' => __('dixlase-blog::admin.settings.index_layout_full'), 'icon' => 'fas fa-newspaper'],
            ],
            'cardFields' => array_map(
                fn (string $key) => [
                    'key' => $key,
                    'label' => __('dixlase-blog::admin.settings.card_field_'.$key),
                    'enabled' => in_array($key, $this->blogSettings->cardFields(), true),
                ],
                BlogSettings::CARD_FIELDS,
            ),
        ]);
    }

    public function sidebar(): View
    {
        return $this->screen('sidebar', [
            // Labels resolved here, as admin views take no logic.
            'sidebarWidgets' => array_map(
                fn (array $widget) => $widget + ['label' => __('dixlase-blog::admin.settings.sidebar_widget_'.$widget['key'])],
                $this->blogSettings->sidebarWidgets(),
            ),
            'sidebarPositionOptions' => $this->sidebarPositionOptions(),
            'recentPostsMin' => BlogSettings::RECENT_POSTS_MIN,
            'recentPostsMax' => BlogSettings::RECENT_POSTS_MAX,
        ]);
    }

    public function share(): View
    {
        return $this->screen('share', [
            'shareServices' => array_map(
                fn (string $key) => [
                    'key' => $key,
                    'label' => __('dixlase-blog::admin.settings.share_service_'.$key),
                    'enabled' => in_array($key, $this->blogSettings->shareServices(), true),
                ],
                BlogSettings::SHARE_SERVICES,
            ),
        ]);
    }

    public function comments(): View
    {
        return $this->screen('comments', [
            'commentModerationOptions' => [
                ['value' => 'approval', 'label' => __('dixlase-blog::admin.settings.comment_moderation_approval'), 'icon' => 'fas fa-user-check'],
                ['value' => 'immediate', 'label' => __('dixlase-blog::admin.settings.comment_moderation_immediate'), 'icon' => 'fas fa-bolt'],
            ],
            'commentsCloseAfterDaysMax' => BlogSettings::COMMENTS_CLOSE_AFTER_DAYS_MAX,
            'commentLinkLimitMax' => BlogSettings::COMMENT_LINK_LIMIT_MAX,
            'commentRateMaxAttemptsMax' => BlogSettings::COMMENT_RATE_MAX_ATTEMPTS_MAX,
            'commentRateDecayMinutesMax' => BlogSettings::COMMENT_RATE_DECAY_MINUTES_MAX,
            // Shown as the placeholder: where mail goes when the field is empty.
            'siteAdminEmail' => ConfigHelper::getNotificationEmail(),
            'commentIpRetentionDaysMax' => BlogSettings::COMMENT_IP_RETENTION_DAYS_MAX,
            // A plugin's privacy policy wins over the URL set here.
            'providedPrivacyUrl' => $this->comments->providedPrivacyPolicyUrl(),
        ]);
    }

    /**
     * @param  array<string, mixed>  $params
     */
    private function screen(string $screen, array $params): View
    {
        return view('dixlase-blog::admin.settings.'.$screen, array_merge($this->viewParams, [
            'blogSettings' => $this->blogSettings->all(),
            'screen' => $screen,
        ], $params));
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
        $previousSlug = $this->blogSettings->routeSlug();
        $this->blogSettings->save($request->settingsValues());

        // The prefix is baked into the routes when they are registered, so
        // a cached route table would keep serving the old one.
        if ($this->blogSettings->routeSlug() !== $previousSlug && app()->routesAreCached()) {
            Artisan::call('route:clear');
        }

        // Back to the screen that was saved; a form without one is the
        // listing screen's address.
        return redirect()
            ->route('dixlase-blog::admin.blog.settings.'.($request->screen() ?? 'listing'))
            ->with('success', __('dixlase-blog::admin.flash.settings_saved'));
    }
}
