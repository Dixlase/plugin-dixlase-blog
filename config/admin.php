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

return [
    // Route name for the plugin's admin entry.
    //
    // AdminPluginsSettingsController::getPluginSettingsUrl() reads this
    // key to decide whether to render the "Settings" button on the
    // installed-plugin card. Blog does not have a dedicated settings
    // screen, so the button lands on the post list — the primary place
    // operators start from.
    'settings_route' => 'dixlase-blog::admin.blog.index',

    'nav' => [
        'blog' => [
            // Insert the Blog menu after the Front menu in the admin sidebar.
            // Use '_insert_before' => '<menu-key>' to place it before another item instead.
            '_insert_after' => 'front',
            'text' => 'dixlase-blog::admin.nav.blog.text',
            'icon' => 'fas fa-fw fa-newspaper',
            // Route the sidebar permission check through this plugin's
            // config/admin/roles.php. Without it the check falls back to
            // core's PermissionRegistry (no `blog` entry) and the menu is
            // hidden for everyone below SUPER_ADMIN. Value is the plugin
            // directory basename.
            'plugin_slug' => 'DixlaseBlog',
            'can' => 'admin',
            'children' => [
                'index' => [
                    'text' => 'dixlase-blog::admin.nav.blog.index',
                    'route' => 'dixlase-blog::admin.blog.index',
                    'icon' => 'fas fa-fw fa-file-lines',
                    'can' => 'admin',
                ],
                'create' => [
                    'text' => 'dixlase-blog::admin.nav.blog.create',
                    'route' => 'dixlase-blog::admin.blog.create',
                    'icon' => 'fas fa-fw fa-pen-to-square',
                    'can' => 'admin',
                ],
                'categories' => [
                    'text' => 'dixlase-blog::admin.nav.blog.categories',
                    'route' => 'dixlase-blog::admin.blog.categories.index',
                    'icon' => 'fas fa-fw fa-folder-tree',
                    'can' => 'admin',
                ],
                'trash' => [
                    'text' => 'dixlase-blog::admin.nav.blog.trash',
                    'route' => 'dixlase-blog::admin.blog.trash',
                    'icon' => 'fas fa-fw fa-trash-can',
                    'can' => 'admin',
                ],
            ],
        ],
    ],
];
