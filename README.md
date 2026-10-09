# Dixlase Blog

For Japanese, see [README.ja.md](./README.ja.md).

A blog for Dixlase: posts with categories and tags, featured images, revisions with restore, draft / published / scheduled statuses, a trash, and public list, post, archive and RSS pages under `/blog`.

## Features

- **Posts** — Title, slug, excerpt, body and featured image (picked from the core media library). The editor is the core content editor.
- **Publish workflow** — Draft / Published / Scheduled, with an optional end date (`closed_at`) after which the post is hidden.
- **Categories** — A hierarchy with drag-and-drop ordering. A post can belong to several categories.
- **Tags** — Entered as free text on the post screen and created on save.
- **Revisions** — A snapshot on every save, restorable at any time, with notes and protection against pruning.
- **Trash** — Deleted posts can be restored or deleted permanently.
- **Public pages** — `/blog` (paginated list), `/blog/{slug}` (post), `/blog/category/{slug}`, `/blog/tag/{slug}` and `/blog/feed.xml` (RSS). With DixlaseMultilingual's locale URLs enabled, the same pages are also served under `/{locale}/blog`.

## Installation

Open the admin panel under **Dashboard → Plugins**, find this plugin, then download and enable it. The plugin's tables are created automatically on enable.

When you place the plugin by cloning the repository instead, regenerate the autoloader from the Dixlase root and build the front-end assets:

```bash
php scripts/sync-local-autoload.php && composer dump-autoload --no-scripts
cd plugins/DixlaseBlog && npm ci && npm run build
```

The built files under `resources/assets/` are not tracked in git. The release ZIP and the deploy workflow build them.

## Usage

Once enabled, **Blog** appears in the admin sidebar with the post list, new post, categories and trash screens.

A post's public URL is `/blog/{slug}`. Use a short English slug; it is the post's permanent address.

## License

Dixlase Blog is distributed under a **dual license**:

- **Open Source License**: [GNU General Public License v3](./LICENSE)
- **Commercial License**: For use cases where GPL v3 compliance is not feasible, a separate commercial license is available — see [LICENSE.commercial](./LICENSE.commercial) (currently a draft) or contact **info@dixlase.org**.

A short overview of how these files fit together is in [NOTICE](./NOTICE) ([日本語](./NOTICE.ja)).

## Contributing

We do not yet accept external code Pull Requests. They will open once we have assessed core API stability and how the project operates after the initial release, and prepared a Contributor License Agreement (CLA) that has passed legal review. Once the CLA is finalized, contributions will fall under the [Dixlase Copyright Policy](https://github.com/Dixlase/dixlase-core/blob/main/COPYRIGHT-POLICY.md) and the Dixlase CLA (see CONTRIBUTING.md). Bug reports and proposals via Issues are welcome. For feature proposals, please take a look at [the Dixlase philosophy](https://dixlase.org/en/philosophy) — and consider whether the feature belongs in the core or could work as a plugin. It helps us align on direction.

---
© 2026 exc-D inc. and Dixlase contributors
