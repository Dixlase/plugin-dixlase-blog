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
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program. If not, see <https://www.gnu.org/licenses/>.
 */

namespace Plugins\DixlaseBlog\App\Services;

use App\Contracts\Front\FrontPageBodyProviderInterface;
use App\DTO\Front\FrontPageBodyContext;
use App\DTO\Front\FrontPageBodyDTO;
use App\DTO\Front\FrontPageBodyResult;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\View;
use Plugins\DixlaseBlog\App\Models\BlogPost;

/**
 * Offers the blog's top page as the body of a theme's front page (#257).
 *
 * The theme keeps its hero above and its footer below; what appears between
 * them is the blog's own listing, rendered by the blog. That is the point of
 * rendering here rather than handing the theme any data: every setting that
 * decides how the listing looks — the layout, the full text, posts per page,
 * the card fields, and the sidebar — applies without the theme knowing a
 * single one of their keys.
 *
 * One choice is offered, the top page. Unlike a provider that offers a page
 * each, there is nothing here for an operator to pick between.
 */
final class BlogFrontPageBodyProvider implements FrontPageBodyProviderInterface
{
    /**
     * The only choice this provider offers.
     */
    public const BODY_TOP = 'top';

    public function __construct(
        private readonly BlogIndexListing $listing,
        private readonly BlogFrontAssetUrls $assets,
    ) {}

    public function getPluginSlug(): string
    {
        return BlogPost::PLUGIN_SLUG;
    }

    public function isCapabilityAvailable(): bool
    {
        return true;
    }

    public function getFrontPageBodyLabel(): string
    {
        return __('dixlase-blog::admin.front_page_body.label');
    }

    /**
     * The top page, always.
     *
     * The contract allows an empty array when a plugin has nothing to offer,
     * and this deliberately does not use it for an empty blog. The top page is
     * a destination that exists whether or not a post has been published yet —
     * its own page says "no posts have been published yet" rather than 404 —
     * so withdrawing the choice would take the setting away from an operator
     * who is setting the site up before writing anything.
     *
     * @return FrontPageBodyDTO[]
     */
    public function availableFrontPageBodies(int $limit = 100): array
    {
        if ($limit < 1) {
            return [];
        }

        return [
            new FrontPageBodyDTO(
                id: self::BODY_TOP,
                label: __('dixlase-blog::admin.front_page_body.top'),
                description: __('dixlase-blog::admin.front_page_body.top_description'),
            ),
        ];
    }

    public function renderFrontPageBody(string $bodyId, FrontPageBodyContext $context): ?FrontPageBodyResult
    {
        if ($bodyId !== self::BODY_TOP) {
            return null;
        }

        return $this->inLocale($context->locale, function () use ($context): FrontPageBodyResult {
            $listing = $this->listing->for($context->page);
            $assets = $this->assets->forFrontPageBody();

            return new FrontPageBodyResult(
                content: View::make('dixlase-blog::front._index-body', [
                    ...$listing,
                    'showHeading' => $context->showHeading,
                    // The blog's own breadcrumbs lead back to the blog index.
                    // On the front page that trail starts where the visitor
                    // already is, so there is nothing to show.
                    'breadcrumbs' => [],
                ])->render(),
                title: __('dixlase-blog::front.index.title'),
                description: __('dixlase-blog::front.index.description'),
                // The blog's own stylesheet and script, plus the per-post CSS
                // that the full-text layout needs. index.blade.php pushes both
                // onto the theme's stacks itself; a body cannot, so they travel
                // here and the theme puts them where they belong.
                styleUrls: [...$assets['styleUrls'], ...$this->postStyleUrls($listing['fullPosts'])],
                scriptUrls: $assets['scriptUrls'],
                meta: ['page' => $context->page],
            );
        });
    }

    /**
     * Each post's own CSS, when the full-text layout is in use.
     *
     * @param  ?array<int, array{post: BlogPost, contentHtml: ?string, customCssUrl: ?string}>  $fullPosts
     * @return array<int, string>
     */
    private function postStyleUrls(?array $fullPosts): array
    {
        return array_values(array_unique(array_filter(
            array_column($fullPosts ?? [], 'customCssUrl')
        )));
    }

    /**
     * Run the render under the context's locale.
     *
     * The caller states which locale the page is being built for, and the
     * listing's titles and the strings above all come from the active one. In
     * practice a theme passes the locale it is already in, so this changes
     * nothing — but a caller that passes another one means it, and the locale
     * is restored either way.
     *
     * @template T
     *
     * @param  callable(): T  $render
     * @return T
     */
    private function inLocale(?string $locale, callable $render): mixed
    {
        if ($locale === null || $locale === App::getLocale()) {
            return $render();
        }

        $previous = App::getLocale();
        App::setLocale($locale);

        try {
            return $render();
        } finally {
            App::setLocale($previous);
        }
    }
}
