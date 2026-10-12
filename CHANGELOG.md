# Changelog

All notable changes to the Dixlase Blog plugin are documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this plugin follows Semantic Versioning.

## [Unreleased]

Work toward the first release, 0.1.0 (#13).

### Added

- Share buttons under each post for X, Facebook and LINE, and a copy-link button (#59). They are
  plain links, so nothing from these services loads until a reader clicks; the settings screen
  chooses which show. A small front script (`js/front.js`) handles the copy button.
- The post page links the previous and next visible posts and lists up to three related posts
  (sharing a category or tag, most shared first) (#58). Overridable as `front/_post-nav.blade.php`
  and `front/_related.blade.php`.
- A sidebar on the blog index, archives and post pages (#57), set up on the settings screen: on
  or off, left or right, and which items show in which order (search, categories with post counts,
  recent posts, monthly archive, calendar, tags). It moves below the content on narrow screens.
  Each item is its own overridable template under `front/sidebar/`, and every page now renders
  through `front/_layout.blade.php`.
- The settings entry in the Blog menu reads "Blog Settings" (#70).
- Month and day archives (`/blog/2026/10`, `/blog/2026/10/10`) and a blog search
  (`/blog/search?q=`) (#56). Search matches every word in the title, excerpt or body of the text a
  post was written in; translations are not searched yet. Search results send
  `X-Robots-Tag: noindex`.
- Posts can be pinned to the top of the blog index (#55), with a toggle on the edit screen; the
  card shows a "Pinned" badge. Archives and the feed keep date order. Run the plugin's migrations
  to add the column.
- A blog settings screen (#54), under the Blog menu and behind the plugin card's Settings button,
  for ADMIN and above. Its first setting is the number of posts per page on the index and the
  archives (default 12). Run the plugin's migrations to add the settings table.
- Editors can write posts (#31). They create, edit, preview and trash posts, restore them
  from the trash, work with revisions and manage categories; permanent deletion, deleting
  a category and protecting a revision stay with ADMIN. Writing an HTML body is still
  ADMIN-only. The defaults are in `config/admin/roles.php` and can be changed per role in
  the admin panel.
- With DixlaseSEO 0.2.0 or later, a shared post shows its own title, its OGP image or else its
  featured image, and `og:type` `article` (#26). Older DixlaseSEO versions keep working as before.
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

- Saving a post with an existing tag whose slug does not come from its name (such as the sample
  tag 「はじめまして」) no longer fails with a 500 error (#72).
- The revision diff screen labels the category and tag rows instead of showing translation
  keys (#49).
- Restoring a post from the trash, or restoring a revision, no longer gives it a slug that
  another post uses; it takes the next free one (such as `hello-2`) and the message says so (#43).
- A post saved as scheduled appears once its publish date passes; it used to stay hidden for
  good (#42). A scheduled post now needs a publish date.
- Editing a category no longer fails with "The slug has already been taken" when its slug is
  unchanged, and a category can no longer be chosen as its own parent (#45).
- The language badge on the post edit screen names the language in the admin's language
  ("日本語" in the Japanese admin) instead of its English name (#37).
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

- The admin post list shows each post's permalink instead of its slug (a link while the post is
  public) and uses the DixlasePages table: ID column, status badge, Actions header, icon buttons
  (#74).
- A click anywhere on a post card in the listings opens the post; the category and tag chips
  still open their archives (#52). Hovering a card lightens its background; the title keeps its
  colour (#64).
- The post edit screen uses DixlaseMultilingual's translate button instead of its own
  (#35); the help text now names the source language in the admin's language. The
  `post_form.translate_button` and `post_form.translate_help` strings were removed.
- The built front-end assets under `resources/assets/` are no longer tracked in git, and
  their file names carry a content hash so browsers pick up a new build (#5). Run
  `npm run build` in a local checkout.
