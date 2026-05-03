<?php

/**
 * This file is part of MySoftware.
 *
 * Copyright (C) 2025 exc-D inc.
 * Website: https://exc-d.com
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program. If not, see <https://www.gnu.org/licenses/>.
 */

return [
    'nav' => [
        'blog' => [
            '_insert_after' => 'front', // フロントページ管理のあとに追加、または '_insert_before' => 'media'
            'text' => 'blog-plugin::admin.nav.blog.text', // ブログ管理
            'icon' => 'fas fa-fw fa-newspaper',
            'can' => 'admin',
            'children' => [
                'index' => [
                    'text' => 'blog-plugin::admin.nav.blog.index', // 記事一覧
                    'route' => 'blog-plugin::admin.blog.index',
                    'icon' => 'fas fa-fw fa-file-lines',
                    'can' => 'admin',
                ],
                'create' => [
                    'text' => 'blog-plugin::admin.nav.blog.create', // 記事作成
                    'route' => 'blog-plugin::admin.blog.create',
                    'icon' => 'fas fa-fw fa-pen-to-square',
                    'can' => 'admin',
                ],
            ],
        ],
    ],
];
