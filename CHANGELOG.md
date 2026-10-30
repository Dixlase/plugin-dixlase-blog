# Changelog

All notable changes to the Dixlase Blog plugin are documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this plugin follows Semantic Versioning.

## [0.2.0] - 2026-10-30

### Added

- A `[blog]` shortcode lists the latest posts wherever content goes through core's shortcode parser,
  such as the front page (#168, fixes #167). `count` (1 to 50, default 3), `layout` (`cards`, `list`
  or `full`), `category` / `tag` (a slug) and `link` (whether to link to the blog) choose what it
  shows. It renders nothing when there are no posts.
- A Markdown post carries its own CSS, as an HTML post does, so the classes it gives elements
  (`{.class}`) have something to style them (#163, fixes #162). The post page, the previews and the
  top page in full load it; JavaScript stays HTML-only. Only members who may author scripts (ADMIN
  and above) set the CSS, and an editor who saves the post keeps the CSS an admin wrote. The editor
  shows the CSS tab for Markdown with core 0.1.20 or later; on an earlier core the tab stays
  HTML-only and nothing else changes.

### Changed

- A scheduled post is marked published once its publish datetime has passed (#177, fixes #176). It
  appeared on the site on time, but the admin kept calling it scheduled.
  `dls:blog:publish-scheduled` runs every minute from the scheduler; it records no revision and
  leaves `updated_at` alone. A post still appears on time when the scheduler runs late.
- `requires.dixlase` states the oldest core this works on as a floor (`>=0.1.13`) rather than a
  caret, so a core 0.2 release does not refuse to install or update it (#180;
  Dixlase/dixlase-core#790).
- The plugin thumbnail is replaced with a version with an adjusted layout and colours (#171, fixes
  #170).

### Fixed

- The publish and close datetimes of a post are read and saved in the site's display timezone (#165,
  fixes #164). They were read as UTC, so on a site displaying `Asia/Tokyo` a post scheduled for
  10:00 went live at 19:00. The help text names the timezone.
- A post saves when an empty tag or category field arrives as `null` rather than `''` (#173, fixes
  #169). It was refused with a validation error.

### Development

- Pull requests against `next`, the development branch, run the checks (#161, fixes #160).
- The release build compares the files with the signature manifest at the tag and stops before
  building the ZIP on a mismatch (#166; part of Dixlase/dixlase-core#520).
- The deploy workflow recognises a checkout of this repository by its identity rather than by the
  text of its URL, and a site it refuses no longer holds the update back from the others (#172;
  fixes Dixlase/dixlase-core#518 and #519).
- `Plugin Lint` lets a stale signature through on `next` and on pull requests; the plugin is signed
  only at a release (#175, fixes #174).
- `BlogAdminBarEditLinkTest` pins its locale, so it passes where `APP_LOCALE` is not English (#159,
  fixes #158).

## [0.1.4] - 2026-10-24

### Added

- The blog settings choose how the top page (`/{blog}`) shows posts: as cards (the default,
  unchanged) or the latest posts in full (#155, fixes #154). In full, each post shows its date,
  author, linked title, featured image, body, categories and tags; pinned posts come first, and an
  HTML post's own CSS is loaded but not its JavaScript. "Posts per page" now applies to both
  layouts, and its default drops from 12 to 10; a site that already stored a count keeps it. The
  category, tag and date archives keep their cards.
- The post editor shows the post's URL under "Author info" (#148, fixes #147). A post readers can
  see links to its page; a draft, a scheduled post or a closed post links to the preview, with a
  note that it is not public yet. A new post shows nothing until it has been saved.
- A published post's page offers core's admin bar a link to the post's edit screen, for a member
  signed in to the admin who may edit posts (#157, fixes #156). The link appears with Dixlase
  0.1.19 or later.

### Fixed

- The live preview in the post editor starts below the theme's fixed header, so the post title is
  no longer hidden under it (#153, fixes #152).
- In the tag field, confirming an input-method conversion with Enter or Space no longer adds the
  unconverted text as a tag (#146, fixes #145).

### Changed

- The package descriptions call the product Dixlase instead of Dixlase CMS (#149).

### Development

- `BlogPostSeoPageMetaTest` ends as skipped, instead of as six failures, when DixlaseSEO is loaded
  (#151, fixes #150). No runtime code changed.

## [0.1.3] - 2026-10-20

### Added

- With the HTML editor, a post can carry its own CSS and JavaScript, under the same rules as
  DixlasePages (#143, fixes #142). Only members who may author scripts (ADMIN and above) see the
  tabs. The code is kept in revisions and served as `/blog/{slug}/custom-style.css` and
  `custom-script.js` for a published HTML post only. The live preview applies the CSS as it is
  typed; the new-tab preview runs the JavaScript as well.

### Upgrade notes

This release adds a database migration (two columns on the posts table). Plugin migrations are
not run by `php artisan migrate`. Updating with `dls:plugin:update` runs it; a site that receives
the files another way runs `php artisan dls:plugin:migrate DixlaseBlog --force` once.

## [0.1.2] - 2026-10-18

### Added

- The "Preview" button on the post editor shows the text being edited, before it is saved, and
  works on the create screen as well, where it used to be disabled (#141, fixes #140). Nothing is
  written while previewing. The preview is rendered in the post's source language and sent with
  `noindex, nofollow`.

### Changed

- The README, comments, tests, language files, `plugin.json` and this changelog no longer name plugins that are not distributed publicly. Each integration is
  described by the core mechanism it goes through (#137, fixes #136; #139, fixes #138). No behaviour changed.

## [0.1.1] - 2026-10-17

### Added

- Each language shares its own OGP image (#135, fixes #134). The translation editor takes an OGP
  image per language (`seo_ogp_media_id`, type `media`). In a translated language a shared post
  shows that language's OGP image, else its featured image, then the source language's SEO image
  and featured image, then the site's default. The source language is unchanged. Needs
  DixlaseSEO 0.2.3.

### Fixed

- The "Preview" button opens a draft that has no publish date again, instead of a 500 error
  (#132, fixes #131). A post with no publish date has no previous or next post.

## [0.1.0] - 2026-10-16

The first release (#13).

### Added

- Category reordering with buttons (#110, #113): every row has up, down and "move into the
  category above" buttons, for keyboard and touch users. The row moves in place and the tree is
  saved like a drop; focus stays on the pressed button, and the save status is read out.
- Comment counts in the post list (#116): published comments, and the ones waiting for approval as
  a link to the comments screen's Pending tab.
- The comment rate limit is set on the settings screen (#123): a number of comments per period
  from one IP address, five per ten minutes by default.
- A plugin thumbnail for the plugin list (#109, #119).
- A featured image per language (#99). The translation editor takes a featured image for each
  language. The post page, the lists, related posts and the fallback OGP
  image show it, and fall back to the post's own image when none is set or the media item is gone.
- Commenters' personal data (#63). An ADMIN finds every comment written with one e-mail address
  (the trash included) and removes the name, e-mail and IP address from them or deletes them.
  IP addresses are kept for a set number of days (30 by default; 0 keeps none) and then cleared by
  the daily `dls:blog:prune-comment-ips` task. Trashed comments are offered to core's database
  cleanup. The comment form links the privacy policy a plugin provides, else a URL set on the
  settings screen.
- A comments screen (#62): every post's comments in tabs (pending, published, spam, trash), with
  approve, unpublish, mark as spam, trash and restore, singly or in bulk, and a reply as the site
  (which approves the comment it answers). Editors moderate; deleting a comment for good stays with
  ADMIN. A new visitor comment is e-mailed to the address on the settings screen, or to the site's
  system admin address, and the dashboard shows how many comments wait for approval.
- Comments on posts (#61). The settings screen turns them on (off by default), chooses whether a
  new comment is published at once or held for approval, can close comments a number of days after
  publishing, and holds a comment with too many links. Each post follows the blog setting or is
  opened or closed on its own. Visitors give a name, an e-mail address (never shown) and consent to
  the privacy notice (linked when a plugin provides one); an editor or above signed in comments
  as the site. Replies nest to any depth, comments are shared by every language of a post, and the
  cards show the comment count. Spam is kept down by core's CAPTCHA (the form is listed in its
  settings), a rate limit and a honeypot field. Overridable as `front/_comments.blade.php` and
  `front/_comment.blade.php`.
- The blog's URL prefix can be changed on the settings screen (`/blog` by default, e.g. `/news`)
  (#79). Through core's route slug registry, a prefix another plugin, the admin URL or a reserved
  word already uses is refused, as are the site's language codes; other plugins' checks see the
  blog's prefix too. The route cache is cleared when the prefix changes.
- Breadcrumbs on every public blog page — Home > Blog > the category path, the date or the search
  > the page — with matching BreadcrumbList structured data (#60). Overridable as
  `front/_breadcrumbs.blade.php`.
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
- Posts can be translated (#8). A post is written in one language,
  chosen when it is created, and other languages are added as translations sharing the
  post's slug. Where a translation is missing, the original text is shown.
- Category and tag names can be translated the same way (#9); slugs stay shared. A
  category's language is chosen when it is created, and a new tag takes the language of
  its post. Run the plugin's migrations to add the language column.

### Fixed

- Reordering categories by drag and drop saves again (#107). Every save sent an empty tree and
  was refused, and the error showed as "[object Object]".
- The comments screen and the personal data screen show their titles instead of a translation key
  (#115). A test now checks that every admin page has a title in English and Japanese.
- The post editor's "Preview" button opens the post again (#105). The preview moved under the
  admin URL: core keeps the admin session apart from the front one, so the front route never saw
  the admin login and answered 404 for every post.
- The live preview beside the post editor shows the featured image, and follows when it is
  chosen, changed or removed in the sidebar (#97).
- The post editor's "Preview" button opens drafts, scheduled and closed posts too (#96). It opens
  `/{blog}/preview/{id}`, which shows a post in any status in the site's layout to members who may
  edit posts, with a banner, `noindex` and no caching; anyone else gets 404.
- Post and category slugs may contain underscores (#95).
- The post editor with core's shared content editor (#93): a Markdown post no longer shows a
  second preview inside the editor, the editor fits beside the right sidebar again after the
  sidebar is closed and reopened, and the title and status carry the required badge.
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

- The package metadata describes 0.1.0 (#121): the description, tags and permission notes in
  `plugin.json`, the `composer.json` description, and `license-info.json` (GPL-3.0-or-later with
  the dual-license header). The README is rewritten for 0.1.0 in both languages (#122).
- The post editor uses the live preview of core's shared content editor (#90), with the blog's own
  frame and renderer, instead of a split pane of its own; the title and the featured image are sent
  to it from the sidebar. Requires Dixlase 0.1.13.
- Wider blog on large screens (#98): from Tailwind's `xl` the blog grows to 80rem and from `2xl`
  to 96rem, a post beside the sidebar fills its column, and listings show three cards beside the
  sidebar at `2xl`. Every media query uses Tailwind's breakpoints. The translation screen names
  the types "Blog post", "Blog category" and "Blog tag", and the category tree's action buttons
  line up at every depth.
- The category screen builds the tree in place (#88). A row's + creates a child under it, and
  "Move up a level" moves a child or grandchild next to its parent, so the parent select box is
  gone from both forms. Categories nest three levels deep at most; the forms, the reorder endpoint
  and drag and drop all refuse a fourth, and while dragging the drop target is highlighted. Edit
  and delete are icon buttons, as on the post list. A form that fails validation opens again with
  its errors.
- The post trash uses the same table as the post list: ID column, deletion date, Actions header
  and icon buttons, and cards at phone width (#85).
- The admin screens name their Alpine components and pass values through `data-*` attributes
  (post form, post editor, tag input, category reorder), ready for the CSP build of Alpine (#84).
- The admin post list shows each post's permalink instead of its slug (a link while the post is
  public) and uses the DixlasePages table: ID column, status badge, Actions header, icon buttons
  (#74).
- A click anywhere on a post card in the listings opens the post; the category and tag chips
  still open their archives (#52). Hovering a card lightens its background; the title keeps its
  colour (#64).
- The built front-end assets under `resources/assets/` are no longer tracked in git, and
  their file names carry a content hash so browsers pick up a new build (#5). Run
  `npm run build` in a local checkout.
