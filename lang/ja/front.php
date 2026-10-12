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
 */

return [

    'index' => [
        'title' => 'ブログ',
        'heading' => 'ブログ',
        'description' => '最新の記事一覧です。',
        'no_posts' => 'まだ公開された記事はありません。',
        'feed_link' => 'RSS フィード',
    ],

    'archive' => [
        'category_title' => 'カテゴリ: :name',
        'tag_title' => 'タグ: :name',
        'category_kicker' => 'カテゴリ',
        'tag_kicker' => 'タグ',
        'no_posts' => 'このアーカイブにはまだ記事がありません。',
        'back_to_index' => '← 記事一覧へ戻る',
        'date_title' => 'アーカイブ: :date',
        'date_kicker' => 'アーカイブ',
        'search_title' => '検索: :query',
        'search_kicker' => '検索',
        // Carbon isoFormat patterns for the date archive headings.
        'month_format' => 'YYYY年M月',
        'day_format' => 'YYYY年M月D日',
    ],

    'search' => [
        'label' => 'ブログ内を検索',
        'placeholder' => 'キーワード',
        'button' => '検索',
        'no_results' => '検索に一致する記事はありません。',
    ],

    'sidebar' => [
        'label' => 'ブログのサイドバー',
        'search' => '検索',
        'categories' => 'カテゴリ',
        'recent' => '最近の記事',
        'archives' => 'アーカイブ',
        'calendar' => 'カレンダー',
        'tags' => 'タグ',
    ],

    'taxonomy' => [
        'categories' => 'カテゴリ',
        'tags' => 'タグ',
    ],

    'feed_title' => ':site のブログ',
    'feed_description' => ':site の最新ブログ記事。',


    'pagination' => [
        'label' => 'ページ送り',
        'previous' => '新しい記事',
        'next' => '古い記事',
        'status' => ':current / :last ページ',
    ],

    'post' => [
        'nav_label' => 'ほかの記事',
        'previous' => '前の記事',
        'next' => '次の記事',
        'related' => '関連記事',
        'back_to_index' => '← 記事一覧へ戻る',
        'pinned' => '固定',
    ],
];
