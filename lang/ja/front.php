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

    'share' => [
        'label' => '共有',
        'x' => 'X',
        'facebook' => 'Facebook',
        'line' => 'LINE',
        'copy' => 'リンクをコピー',
        'copied' => 'コピーしました',
    ],

    'breadcrumbs' => [
        'label' => 'パンくずリスト',
        'home' => 'ホーム',
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
        // The edit link in the admin bar, for members who may edit posts.
        'admin_bar_edit' => 'この記事を編集',
        'pinned' => '固定',
    ],

    'comments' => [
        'heading' => 'コメント',
        'count' => 'コメント :count 件',
        'empty' => 'まだコメントはありません。',
        'form_heading' => 'コメントを書く',
        'reply_heading' => ':name さんへの返信',
        'reply' => '返信',
        'cancel_reply' => '返信をやめる',
        'site_badge' => 'サイト',
        'as_site' => 'サイトとしてコメントします（:name）。',
        'name_label' => '名前',
        'email_label' => 'メールアドレス',
        'email_help' => 'サイトには表示しません。',
        'body_label' => 'コメント',
        'trap_label' => 'ここには何も入力しないでください',
        'privacy_label' => '同意',
        'privacy_consent' => '名前とコメントが公開され、メールアドレスがサイトに保存されることに同意します。',
        'privacy_consent_link' => '<a href=":url" target="_blank" rel="noopener">プライバシーポリシー</a>に同意します。名前とコメントが公開され、メールアドレスがサイトに保存されます。',
        'privacy_required' => 'データの扱いへの同意が必要です。',
        'submit' => 'コメントを送信',
        'published_notice' => 'ありがとうございます。コメントを公開しました。',
        'pending_notice' => 'ありがとうございます。コメントは承認されたあとに表示されます。',
        'parent_missing' => '返信先のコメントは、もう表示されていません。',
        'anonymous' => '匿名',
    ],

    // Banner on the editors' preview of a post (any status).
    'preview_banner' => 'プレビュー — この記事の状態は「:status」です。このページは編集者にしか見えません。',
];
