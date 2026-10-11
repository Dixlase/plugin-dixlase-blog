# Changelog

All notable changes to the Dixlase Blog plugin are documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this plugin follows Semantic Versioning.

## [Unreleased]

Work toward the first release, 0.1.0 (#13).

### Added

- Editors can write posts (#31). They create, edit, preview and trash posts, restore them
  from the trash, work with revisions and manage categories; permanent deletion, deleting
  a category and protecting a revision stay with ADMIN. Writing an HTML body is still
  ADMIN-only. The defaults are in `config/admin/roles.php` and can be changed per role in
  the admin panel.
- DixlaseSEO integration (#11): published posts appear in the sitemap, blog pages offer
  their other-language URLs for hreflang, and each post has an SEO description and OGP
  image on its edit screen. The description is translatable and falls back to the
  excerpt. Nothing changes when DixlaseSEO is not installed.
- Default templates and styles for the blog index, post, category and tag pages, with
  previous / next pagination and dark mode (#12). A site or theme overrides a template
  under `custom/plugins/DixlaseBlog/resources/views/` or
  `themes/<theme>/plugins/DixlaseBlog/resources/views/`, as with DixlasePages; the old
  `themes/<theme>/views/dixlase-blog/` path is no longer read.
- Posts can be translated with DixlaseMultilingual (#8). A post is written in one language,
  chosen when it is created, and other languages are added as translations sharing the
  post's slug. Where a translation is missing, the original text is shown.
- Category and tag names can be translated the same way (#9); slugs stay shared. A
  category's language is chosen when it is created, and a new tag takes the language of
  its post. Run the plugin's migrations to add the language column.

### Fixed

- Saving a new post or creating a category no longer fails with a 500 error on MySQL (#33).
  Fields left empty are stored as NULL instead of an empty string.
- The admin routes moved to `routes/admin.php` (#21): the admin screens no longer sit under
  a language prefix, and they get the same middleware as core's admin screens, including
  the role check (`config/admin/roles.php`; without one, ADMIN and above).
- A bare `php artisan migrate` no longer tries to re-create the blog's tables; the plugin's
  migrations run only through core's plugin migrator (#28).
- Post, category and tag pages open under a language prefix such as `/en/blog/{slug}`;
  they used to read the language as the slug (#10). Links and the RSS feed stay in the
  current language, and the blog index links its feed.

### Changed

- The built front-end assets under `resources/assets/` are no longer tracked in git, and
  their file names carry a content hash so browsers pick up a new build (#5). Run
  `npm run build` in a local checkout.
