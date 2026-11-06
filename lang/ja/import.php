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
    'not_local' => '取り込みはこのサイトのデータベースに書き込みます。APP_ENV が「local」のときだけ動きます。ここで動かすには --allow-non-local を付けてください。',
    'member_required' => '記事の著者を --as で指定してください（メンバー ID またはメールアドレス）。',
    'member_not_found' => '「:identifier」に一致するメンバーがいません。',
    'member_inactive' => 'メンバー「:identifier」は有効ではありません。',
    'file_not_found' => '読み込めるファイルではありません: :path',
    'front_matter_unclosed' => 'フロントマターが「---」で始まっていますが、閉じる「---」の行がありません。',
    'front_matter_line' => 'フロントマターの :line 行目を読めません: :text',
    'front_matter_duplicate' => 'フロントマターの :line 行目で、キー「:key」が重複しています。',
    'unknown_key' => 'フロントマターのキー「:key」は使えません。使えるキー: :keys',
    'seo_not_a_map' => '「seo」の下には字下げしたキーを書いてください: :keys',
    'not_text' => '「:key」はリストではなく、1 つの値にしてください。',
    'not_markdown' => '記事「:slug」は Markdown で書かれていないため、ファイルから更新できません。',
    'unknown_categories' => 'このスラッグのカテゴリがありません: :slugs',
    'image_not_found' => ':field: 画像が見つかりません: :path',
    'image_must_be_local' => ':field にはローカルの画像ファイルのパスを書いてください。',
    'image_failed' => ':field: :path を取り込めませんでした: :error',
    'failed' => '何も保存していません。',
    'would_create' => '新しい記事を作成します。',
    'would_update' => '記事 #:id（:slug）を更新します。',
    'would_import' => ':count 件の画像を取り込みます:',
    'created' => '記事 #:id（:slug）を :status で作成しました。',
    'updated' => '記事 #:id（:slug）を更新しました。状態は :status です。',
    'images' => '画像: 取り込み :imported 件、登録済み :reused 件。',
];
