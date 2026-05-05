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

declare(strict_types=1);

namespace Plugins\DixlaseBlog\Database\Seeders;

use App\Enums\ContentEditorType;
use App\Enums\ContentStatus;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Plugins\DixlaseBlog\App\Models\BlogCategory;
use Plugins\DixlaseBlog\App\Models\BlogPost;
use Plugins\DixlaseBlog\App\Models\BlogTag;

/**
 * Seeds a single sample post (with a sample category and tag) so the
 * admin and public pages have something to render right after install.
 *
 * Locale: chosen at run-time from config('app.locale'), narrowed down
 * to the languages declared in config('language.languages', []) so a
 * site that disables one of the bundled languages still gets a working
 * sample. Defaults to English when neither matches.
 *
 * Idempotent: if any blog post already exists the seeder returns early,
 * so reseeding does not duplicate or overwrite operator content.
 */
class BlogPostSeeder extends Seeder
{
    public function run(): void
    {
        if (BlogPost::query()->withTrashed()->exists()) {
            return;
        }

        $locale = $this->resolveSeedLocale();
        $sample = $locale === 'ja' ? $this->japaneseSample() : $this->englishSample();

        DB::transaction(function () use ($sample): void {
            $category = BlogCategory::query()->create([
                'label' => $sample['category_label'],
                'slug' => $sample['category_slug'],
                'parent_id' => null,
                'display_order' => 10,
            ]);

            $tag = BlogTag::query()->create([
                'name' => $sample['tag_name'],
                'slug' => $sample['tag_slug'],
            ]);

            $post = BlogPost::query()->create([
                'title' => $sample['title'],
                'slug' => $sample['slug'],
                'content' => $sample['content'],
                'excerpt' => $sample['excerpt'],
                'editor_type' => ContentEditorType::MARKDOWN,
                'status' => ContentStatus::PUBLISHED,
                'lang' => $sample['lang'],
                'user_id' => null,
                'published_at' => now(),
            ]);

            $post->categories()->attach($category->id);
            $post->tags()->attach($tag->id);
        });
    }

    /**
     * Pick the seed locale from config('app.locale'), filtered against
     * the configured supported languages so a misconfigured app does
     * not produce orphaned content in a language the site cannot serve.
     */
    private function resolveSeedLocale(): string
    {
        $locale = strtolower((string) (config('app.locale') ?? 'en'));
        $normalized = str_starts_with($locale, 'ja') ? 'ja' : 'en';

        $supported = array_keys((array) config('language.languages', []));
        if (! empty($supported) && ! in_array($normalized, $supported, true)) {
            // Fall back to the first supported language if the chosen
            // one is not available (e.g. an EN-only site with app.locale=ja).
            $normalized = $supported[0];
        }

        return $normalized;
    }

    /**
     * @return array<string, string>
     */
    private function englishSample(): array
    {
        return [
            'lang' => 'en',
            'title' => 'Welcome to your new blog',
            'slug' => 'welcome-to-dixlase-blog',
            'category_label' => 'Announcements',
            'category_slug' => 'announcements',
            'tag_name' => 'welcome',
            'tag_slug' => 'welcome',
            'excerpt' => 'A sample post installed by the Dixlase Blog plugin so you can see how things look out of the box. Edit or delete it any time from the admin panel.',
            'content' => <<<'MD'
# Welcome to your new blog

This is a sample post installed by the **Dixlase Blog** plugin so you can see
how things look out of the box. Edit or delete it from the admin panel any
time.

## What's included

- Posts written in **Markdown** or **HTML**
- Hierarchical, multi-select **categories**
- **Tags** added with `#tag` + space
- Featured images via the core media library
- Revision history with side-by-side diff
- A trash bin for soft-deleted posts
- Public list, detail, category and tag archive pages
- An RSS 2.0 feed at `/blog/feed.xml`

## Next steps

1. Create a category that fits your topics (the bundled "Announcements" one
   is just an example).
2. Replace this post with your own first article.
3. Wire the public `/blog` route into your theme's navigation.

Happy writing!
MD,
        ];
    }

    /**
     * @return array<string, string>
     */
    private function japaneseSample(): array
    {
        return [
            'lang' => 'ja',
            'title' => 'ブログを始めましょう',
            'slug' => 'welcome-to-dixlase-blog',
            'category_label' => 'お知らせ',
            'category_slug' => 'announcements',
            'tag_name' => 'はじめまして',
            'tag_slug' => 'welcome',
            'excerpt' => 'Dixlase Blog プラグインがインストール時に追加するサンプル記事です。最初の動作確認用なので、管理画面から自由に編集・削除してください。',
            'content' => <<<'MD'
# ブログを始めましょう

これは **Dixlase Blog** プラグインがインストール時に追加するサンプル記事です。
最初の動作確認用なので、管理画面から自由に編集・削除してください。

## 同梱される機能

- **Markdown** または **HTML** で本文を執筆
- 複数選択可能な階層カテゴリ
- `#タグ名` + スペースでタグ付け
- コア機能の Media ライブラリ経由でアイキャッチ画像を選択
- リビジョン履歴 (差分表示・復元・保護)
- ソフト削除のゴミ箱
- 公開側の一覧 / 詳細 / カテゴリ・タグアーカイブ
- `/blog/feed.xml` の RSS 2.0 フィード

## 次のステップ

1. テーマや方針に合わせてカテゴリを作成 (同梱の「お知らせ」はあくまで例です)
2. このサンプル記事を最初の記事で置き換える
3. テーマのナビゲーションに `/blog` への導線を追加する

それでは、ブログ運営をお楽しみください!
MD,
        ];
    }
}
