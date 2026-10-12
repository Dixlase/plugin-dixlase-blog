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
 */

return [

    'nav' => [
        'blog' => [
            'text' => 'ブログ管理',
            'index' => 'ブログマスター',
            'create' => '新規作成',
            'categories' => 'カテゴリ',
            'trash' => 'ゴミ箱',
            'settings' => '設定',
        ],
    ],

    // 自動解決される画面見出し。コアの AdminInterfaceTrait がルート名から
    // ルックアップキー (例: dixlase-blog::admin.blog.index →
    // admin.blog.index.heading) を導き、管理画面レイアウトの上部バーに
    // 描画する。routes/web.php のルート名と階層を一致させて維持する。
    'blog' => [
        'index' => ['heading' => '記事一覧'],
        'create' => ['heading' => '新規記事'],
        'edit' => ['heading' => '記事編集'],
        'trash' => ['heading' => 'ゴミ箱'],
        'categories' => [
            'index' => ['heading' => 'カテゴリ'],
        ],
        'revisions' => [
            'index' => ['heading' => 'リビジョン履歴'],
            'show' => ['heading' => 'リビジョン差分'],
        ],
        'settings' => [
            'edit' => ['heading' => 'ブログの設定'],
        ],
    ],

    'page_title' => [
        'index' => '記事一覧',
        'trash' => 'ゴミ箱',
        'create' => '新規記事',
        'edit' => '記事編集',
        'categories' => 'カテゴリ',
        'revisions' => 'リビジョン履歴',
    ],

    'post' => [
        'title_label' => 'タイトル',
        'title_placeholder' => '記事タイトルを入力',
        'slug_label' => 'スラッグ',
        'slug_help' => '空欄ならタイトルから自動生成します。',
        'content_label' => '本文',
        'excerpt_label' => '抜粋',
        'excerpt_help' => '一覧表示や OG description で使用します。空欄なら本文先頭から 140 文字を自動生成します。',
        'featured_image_label' => 'アイキャッチ画像',
        'featured_image_help' => 'OG 画像や一覧表示に使用します。メディアライブラリから選択してください。',
        'status_label' => 'ステータス',
        'published_at_label' => '公開日時',
        'published_at_help' => '記事を公開する日時 (予約公開で使用)。',
        'closed_at_label' => '非公開日時',
        'closed_at_help' => '任意。指定時刻に自動で非公開化します。',
        'lang_label' => '言語',
        'lang_help' => 'この記事を書く言語です。ほかの言語は、保存後に翻訳として追加します。',
        'editor_type_not_allowed' => 'このエディタの種類は使えません。',
        'scheduled_needs_published_at' => '予約する記事には公開日時を設定してください。',
        'categories_label' => 'カテゴリ',
        'categories_help' => '1記事に複数カテゴリを設定できます。',
        'no_categories' => 'カテゴリが未作成です — 先にカテゴリを作成してください。',
        'tags_label' => 'タグ',
        'tags_placeholder' => 'タグを入力してスペースで確定',
        'tags_help' => 'スペース (または Enter) で確定。空欄状態の Backspace で末尾のタグを削除。先頭の # は省略可。',
        'created_at' => '作成日時',
        'updated_at' => '更新日時',
        'author' => '執筆者',
        'author_unknown' => '(不明)',
    ],

    'post_index' => [
        'heading' => '記事一覧',
        'description' => 'ブログ記事を管理します。',
        'new_button' => '新規記事',
        'trash_link' => 'ゴミ箱',
        'col_title' => 'タイトル',
        'col_status' => 'ステータス',
        'col_categories' => 'カテゴリ',
        'col_published_at' => '公開日',
        'col_actions' => '',
        'no_posts' => '記事がまだありません。',
        'edit' => '編集',
        'delete' => 'ゴミ箱へ移動',
        'delete_confirm_title' => 'ゴミ箱へ移動',
        'delete_confirm_message' => 'この記事をゴミ箱へ移動しますか? ゴミ箱から復元できます。',
    ],

    'post_form' => [
        'create_heading' => '新規記事',
        'edit_heading' => '記事編集',
        'sidebar_open' => 'サイドバーを開く',
        'sidebar_close' => 'サイドバーを閉じる',
        'publish_section' => '公開設定',
        'classification_section' => '分類',
        'media_section' => 'アイキャッチ画像',
        'meta_section' => 'タイトル・スラッグ・抜粋',
        'meta_info_section' => 'メタ情報',
        'meta_editor_locked_help' => '記事を一度保存するとエディタータイプは固定されます — 切り替えると本文の変換ロスが発生する恐れがあるためです。別のエディタータイプを使いたい場合は新規記事を作成してください。',
        'author_info_section' => '作成者情報',
        'revision_section' => 'リビジョン',
        'revision_link' => '履歴を見る',
        'revision_help' => '保存ごとにリビジョンを作成します (変更がない場合はスキップ)。',
        'preview_button' => 'プレビュー',
        'preview_show' => 'プレビュー表示',
        'preview_hide' => 'プレビュー非表示',
        'preview_help_edit' => '保存済みのバージョンを新しいタブで開きます。未保存の変更は中央のトグルでプレビューしてください。',
        'preview_help_create' => '記事を一度保存すると公開側プレビューが利用できるようになります。',
        'save_confirm_title' => '記事を保存',
        'save_confirm_message' => 'この記事の変更を保存しますか?',
        'back_to_posts' => '記事一覧へ戻る',
    ],

    'trash' => [
        'heading' => 'ゴミ箱',
        'description' => 'ゴミ箱内の記事を復元、または完全削除できます。',
        'col_title' => 'タイトル',
        'col_deleted_at' => '削除日時',
        'col_actions' => '',
        'no_posts' => 'ゴミ箱は空です。',
        'restore' => '復元',
        'delete_forever' => '完全削除',
        'delete_confirm_title' => '完全削除',
        'delete_confirm_message' => 'この記事を完全に削除しますか? 元に戻せません。',
        'back_to_posts' => '記事一覧へ戻る',
    ],

    'category' => [
        'heading' => 'カテゴリ',
        'description' => 'ブログのカテゴリを管理します。',
        'reorder_help' => '各行の左にあるハンドル (⋮⋮) をドラッグして並べ替えます。別のカテゴリの上にドロップするとそのカテゴリの子になります。',
        'drag_handle_label' => 'ドラッグして並べ替え',
        'saving' => '保存中…',
        'saved' => '保存しました',
        'new_button' => '新規カテゴリ',
        'col_label' => '名称',
        'lang_label' => '言語',
        'lang_help' => 'この名前を書く言語です。ほかの言語は翻訳として追加します。',
        'col_slug' => 'スラッグ',
        'col_posts' => '記事数',
        'col_actions' => '',
        'no_categories' => 'カテゴリがまだありません。',
        'edit' => '編集',
        'delete' => '削除',
        'save' => '保存',
        'cancel' => 'キャンセル',
        'label_label' => '名称',
        'slug_label' => 'スラッグ',
        'slug_help' => '空欄なら名称から自動生成します。',
        'parent_label' => '親カテゴリ',
        'parent_none' => '(最上位)',
        'delete_confirm_title' => 'カテゴリを削除',
        'delete_confirm_message' => 'このカテゴリを削除しますか? このカテゴリに属する記事はカテゴリ未設定になります。',
    ],

    'revision' => [
        'heading' => 'リビジョン履歴',
        'back_to_edit' => '記事へ戻る',
        'no_revisions' => 'リビジョンがまだありません。',
        'created_at' => '作成日時',
        'type' => '種別',
        'creator' => '作成者',
        'note' => 'メモ',
        'note_placeholder' => 'このリビジョンのメモ (任意)',
        'note_help' => '何が変わったかの簡単なメモを残せます。',
        'note_save' => 'メモを保存',
        'note_confirm_title' => 'メモを保存',
        'note_confirm_message' => 'このリビジョンのメモを保存しますか?',
        'protect' => '保護',
        'protect_help' => '保護されたリビジョンは保持件数超過時の自動削除対象から除外されます。',
        'protect_label_on' => '保護中',
        'protect_label_off' => '未保護',
        'protect_enable' => '保護する',
        'protect_disable' => '保護解除',
        'protect_count_summary' => ':protected 件保護中 / :retention 件保持',
        'protect_count_summary_over' => ':protected 件保護中 (保持上限 :retention 件を超過)',
        'actions' => '操作',
        'view_diff' => '差分を見る',
        'restore' => '復元',
        'restore_confirm_title' => 'リビジョンを復元',
        'restore_confirm_message' => 'このリビジョンを復元しますか? 復元前に現状をバックアップとして保存します。',
        'unknown_user' => '(不明)',
        'type_auto' => '自動',
        'type_manual' => '手動',
        'type_restore_backup' => '復元前バックアップ',
        'diff_heading' => '差分',
        'diff_no_changes' => '現状との差分はありません。',
        'diff_meta_heading' => 'メタ情報',
        'diff_left_label' => 'リビジョン',
        'diff_right_label' => '現在',
        'diff_field_title' => 'タイトル',
        'diff_field_slug' => 'スラッグ',
        'diff_field_content' => '本文',
        'diff_field_excerpt' => '抜粋',
        'diff_field_featured_media_id' => 'アイキャッチ画像',
        'diff_field_editor_type' => 'エディタ',
        'diff_field_status' => 'ステータス',
        'diff_field_lang' => '言語',
        'diff_field_published_at' => '公開日時',
        'diff_field_closed_at' => '非公開日時',
        'diff_field_user_id' => '執筆者',
        'diff_field_category_ids' => 'カテゴリ',
        'diff_field_tag_ids' => 'タグ',
        'pivot_heading' => 'カテゴリとタグ',
        'pivot_added' => '追加',
        'pivot_removed' => '削除',
    ],

    'settings' => [
        'listing_heading' => '一覧',
        'posts_per_page_label' => '1 ページの表示件数',
        'posts_per_page_help' => 'ブログの一覧と、カテゴリー・タグ・日付のアーカイブで 1 ページに出す記事の数です（:min〜:max）。',
        'confirm_title' => '設定を保存しますか？',
        'confirm_message' => '変更はすぐにブログの公開ページに反映されます。',
    ],

    'flash' => [
        'settings_saved' => '設定を保存しました。',
        'post_created' => '記事を作成しました。',
        'post_updated' => '記事を更新しました。',
        'post_trashed' => '記事をゴミ箱へ移動しました。',
        'post_restored' => '記事を復元しました。',
        'post_restored_renamed' => '記事を復元しました。slug がほかの記事で使われているため、「:slug」に変えました。',
        'post_deleted' => '記事を完全に削除しました。',
        'category_created' => 'カテゴリを作成しました。',
        'category_updated' => 'カテゴリを更新しました。',
        'category_deleted' => 'カテゴリを削除しました。',
        'category_has_children' => '子カテゴリが存在するため削除できません。先に子カテゴリを移動または削除してください。',
        'revision_restored' => 'リビジョンを復元しました。',
        'revision_restored_renamed' => 'リビジョンを復元しました。slug がほかの記事で使われているため、「:slug」に変えました。',
        'revision_protect_enabled' => 'リビジョンを保護しました。',
        'revision_protect_disabled' => 'リビジョンの保護を解除しました。',
        'revision_note_updated' => 'リビジョンのメモを更新しました。',
    ],


    'provider' => [
        'label' => 'ブログ記事',
    ],
];
