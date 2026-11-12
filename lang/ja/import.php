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
    'not_local' => 'このサイトはローカルではありません（APP_ENV は「:env」）。取り込みはこのサイトのデータベースに書き込みます。確認なしで動かすには --force を付けてください。',
    'member_required' => '記事の著者を --as で指定してください（メンバーのアカウント名またはメールアドレス）。',
    'member_not_found' => 'アカウント名またはメールアドレスが「:identifier」のメンバーがいません。--as にはメンバー ID ではなく、どちらかを指定してください。',
    'member_inactive' => 'メンバー「:identifier」は有効ではありません。',
    'file_not_found' => '読み込めるファイルではありません: :path',
    'front_matter_unclosed' => 'フロントマターが「---」で始まっていますが、閉じる「---」の行がありません。',
    'front_matter_line' => 'フロントマターの :line 行目を読めません: :text',
    'front_matter_duplicate' => 'フロントマターの :line 行目で、キー「:key」が重複しています。',
    'unknown_key' => 'フロントマターのキー「:key」は使えません。使えるキー: :keys',
    'seo_not_a_map' => '「seo」の下には字下げしたキーを書いてください: :keys',
    'not_text' => '「:key」はリストではなく、1 つの値にしてください。',
    'invalid_editor' => '「editor」は次のどれかにしてください: :editors（指定は「:editor」）。',
    'editor_not_importable' => '記事「:slug」は :editor エディタの記事で、ファイルから更新できる本文の形がありません。ファイルから更新できるのは、Markdown と HTML の記事だけです。',
    'editor_fixed' => '記事「:slug」は :editor で書かれています。保存した記事のエディタは変えられないため、「editor: :named」には従えません。「editor」を書かないか、:editor にしてください。',
    'html_not_allowed' => '記事「:slug」は HTML の記事で、スクリプトを書ける人（管理者以上）しか変更できません。',
    'custom_js_html_only' => '「custom_js」は HTML の記事だけで使えます。Markdown の記事はスクリプトを動かしません。',
    'custom_assets_not_allowed' => ':keys を設定できるのは、スクリプトを書ける人（管理者以上）だけです。',
    'asset_must_be_local' => ':key には、記事のファイルから見たローカルのファイルのパスを書いてください。',
    'asset_not_found' => ':key: ファイルが見つかりません: :path',
    'live_post' => '記事「:slug」は公開中か予約中で、著者のロールでは公開できないため、変更できません。',
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
    'stale_revision' => '記事「:slug」は、このファイルを書き出したあとに変更されています（ファイルの revision: :revision、今の記事: :current）。書き出し直してそのファイルを直すか、変更を上書きするなら --overwrite を付けてください。',
    'changed_in_admin' => '記事「:slug」は、取り込み以外（管理画面など）で最後に保存されています。書き出してそのファイルを直すか、変更を上書きするなら --overwrite を付けてください。',
    'draft_live_post' => '記事「:slug」は公開中か予約中です。--draft を付けるとサイトから取り下げることになるため、変更しませんでした。先に管理画面で非公開にするか、--draft を外して取り込んでください。',
    'would_status' => '状態: :status',
    'no_markdown_files' => 'ディレクトリ :path に .md ファイルがありません',
    'summary' => '完了: 取り込み :ok 件、失敗 :failed 件。',
    'summary_dry_run' => '確認: 取り込める :ok 件、失敗する :failed 件。',
    'invalid_storage' => '--storage は次のどれかにしてください: :types（指定された値: 「:storage」）',
    'storage_simple_mode' => '管理画面がシンプルモードのため記事の保存先を選べず、--storage=:storage には従えません。詳細モードに切り替えるか、--storage を外してください。',
    'would_store' => '保存先: :storage',
    'stored' => '保存先: :storage',
];
