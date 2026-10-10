# Dixlase Blog

English version: [README.md](./README.md)

Dixlase のブログ機能です。カテゴリーとタグ付きの記事、アイキャッチ画像、復元できるリビジョン、下書き・公開・予約公開の状態、ごみ箱、そして `/blog` 以下の一覧・記事・アーカイブ・RSS の公開ページを提供します。

## 機能

- **記事** — タイトル、slug、抜粋、本文、アイキャッチ画像(コアのメディアライブラリから選択)。エディタはコアのコンテンツエディタです。
- **公開の流れ** — 下書き・公開・予約公開。公開終了日(`closed_at`)を指定すると、その後は表示されなくなります。
- **カテゴリー** — ドラッグ&ドロップで並べ替えられる階層構造。1 つの記事を複数のカテゴリーに入れられます。
- **タグ** — 記事の画面で自由に入力し、保存時に作成されます。
- **リビジョン** — 保存のたびにスナップショットを残し、いつでも復元できます。メモと、自動削除からの保護も付けられます。
- **ごみ箱** — 削除した記事は復元するか、完全に削除できます。
- **公開ページ** — `/blog`(ページ送り付きの一覧)、`/blog/{slug}`(記事)、`/blog/category/{slug}`、`/blog/tag/{slug}`、`/blog/feed.xml`(RSS)。DixlaseMultilingual のロケール付き URL を有効にしている場合は、同じページが `/{locale}/blog` でも表示されます。

## インストール

管理画面の **ダッシュボード → プラグイン** でこのプラグインを探し、ダウンロードして有効化してください。有効化するとプラグインのテーブルが自動で作成されます。

リポジトリを clone して配置した場合は、Dixlase のルートで autoload を作り直し、画面用ファイルをビルドしてください。

```bash
php scripts/sync-local-autoload.php && composer dump-autoload --no-scripts
cd plugins/DixlaseBlog && npm ci && npm run build
```

`resources/assets/` のビルドしたファイルは git で追跡していません。リリースの ZIP とデプロイのワークフローがビルドします。

## 使い方

有効化すると、管理画面のサイドバーに **ブログ** が現れ、記事一覧・新規作成・カテゴリー・ごみ箱の画面を使えます。

記事の公開 URL は `/blog/{slug}` です。slug は短い英語にしてください。記事の恒久的なアドレスになります。

## ライセンス

Dixlase Blog は **デュアルライセンス** で配布されています。

- **オープンソースライセンス**: [GNU General Public License v3](./LICENSE)
- **商用ライセンス**: GPL v3 の遵守が現実的でないユースケース向けに、別途商用ライセンスを用意しています。[LICENSE.commercial](./LICENSE.commercial)(現在は草案)を参照するか、**info@dixlase.org** までご連絡ください。

各ファイルの関係の概要は [NOTICE.ja](./NOTICE.ja)([English](./NOTICE))にあります。

## コントリビューションについて

現在、外部からのコード Pull Request は受け付けていません。受付は、コア API の安定性と初期リリース後の運用状況を見極め、法務レビューを経たコントリビューターライセンス契約(CLA)を用意した上で開始します。CLA の確定後は、コントリビューションは [Dixlase 著作権ポリシー](https://github.com/Dixlase/dixlase-core/blob/main/COPYRIGHT-POLICY.ja.md) と Dixlase CLA(CONTRIBUTING.ja.md を参照)の対象になります。Issue でのバグ報告や提案は歓迎します。機能の提案の際は、[Dixlase の思想](https://dixlase.org/ja/philosophy) に目を通し、その機能がコアに属するものか、プラグインとして実現できるものかもご検討ください。方向性を揃える助けになります。

---
© 2026 exc-D inc. and Dixlase contributors
