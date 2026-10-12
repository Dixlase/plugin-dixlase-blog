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

use App\Enums\MemberRole;

/*
 * Default role permissions for the blog's admin screens.
 *
 * Core's EnsurePluginAdminAccess derives the permission key from the route
 * name (dixlase-blog::admin.blog.categories.store -> blog.categories.store),
 * walks it from most specific to least, and falls back to ADMIN when nothing
 * is declared. So every admin route is declared here, not only the sidebar
 * entries -- otherwise an editor could open a screen and then get a 403 on
 * save. The root `blog` key carries no access_roles on purpose: a route added
 * later and forgotten here stays ADMIN-only instead of inheriting EDITOR.
 *
 * The split follows DixlasePages, by whether an action can be taken back:
 *
 *   EDITOR  authoring, and deletions that land in the trash
 *   ADMIN   permanent deletion (trash, categories) and revision protection,
 *           which decides what may be pruned later
 *
 * Writing an HTML body is ADMIN-only regardless of this file; see
 * BlogScriptAuthoringPolicy.
 *
 * Only changes made in the admin panel are stored, as differences, in the
 * role_permission_overrides table.
 */

$editor = [
    'access_roles' => MemberRole::EDITOR->value,
    'view_roles' => MemberRole::EDITOR->value,
];

$admin = [
    'access_roles' => MemberRole::ADMIN->value,
    'view_roles' => MemberRole::ADMIN->value,
];

return [
    'permissions' => [
        'blog' => [
            'children' => [
                // Posts
                'index' => $editor,
                'create' => $editor,
                'store' => $editor,
                'edit' => $editor,
                'update' => $editor,
                'preview' => $editor,
                'previewFrame' => $editor,
                // Soft delete: the post goes to the trash and can be restored.
                'destroy' => $editor,

                // Trash
                'trash' => $editor,
                'restore' => $editor,
                'forceDelete' => $admin,

                // Categories. Deleting one is permanent, so it stays ADMIN.
                // Nested, not written as 'categories.store': PermissionRegistry
                // walks the array level by level, so a dotted key never matches.
                'categories' => [
                    'access_roles' => MemberRole::EDITOR->value,
                    'view_roles' => MemberRole::EDITOR->value,
                    'children' => [
                        'index' => $editor,
                        'store' => $editor,
                        'update' => $editor,
                        'reorder' => $editor,
                        'destroy' => $admin,
                    ],
                ],

                // Settings change how the whole blog behaves.
                'settings' => [
                    'access_roles' => MemberRole::ADMIN->value,
                    'view_roles' => MemberRole::ADMIN->value,
                    'children' => [
                        'edit' => $admin,
                        'update' => $admin,
                    ],
                ],

                // Revisions. Restoring is authoring (and still checks the
                // editor type of both versions). No bare `revisions` route
                // exists, so the parent has no roles of its own.
                'revisions' => [
                    'children' => [
                        'index' => $editor,
                        'show' => $editor,
                        'restore' => $editor,
                        'note' => $editor,
                        'protect' => $admin,
                    ],
                ],
            ],
        ],
    ],
];
