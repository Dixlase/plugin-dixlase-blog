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
            'text' => 'ブログ管理',
            'index' => 'ブログマスター',
            'create' => '記事作成',
        ],
    ],

    'features' => [
        'blog' => [
            'title' => '記事タイトル',
            'slug' => 'スラッグ',
            'content' => 'コンテンツ',
            'status' => 'ステータス',
            'index' => [
                'heading' => 'ページマスター',
            ],
            'create' => [
                'heading' => '新規記事作成',
            ],
            'edit' => [
                'heading' => '記事編集',
            ],
        ]
    ],
];
