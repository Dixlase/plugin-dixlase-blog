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
declare(strict_types=1);

namespace Plugins\DixlaseBlog\App\Services;

use App\Contracts\PluginIntegration\CaptchaFormProviderInterface;
use App\DTO\PluginIntegration\CaptchaFormDTO;

/**
 * Offers the comment form to core's CAPTCHA settings, where the site turns
 * CAPTCHA on or off for it.
 */
class BlogCommentCaptchaFormProvider implements CaptchaFormProviderInterface
{
    /** The form's key in core's CAPTCHA settings: "{plugin slug}.{key}". */
    public const FORM_KEY = 'dixlase-blog.post_comment';

    public function getPluginSlug(): string
    {
        return 'dixlase-blog';
    }

    public function isCapabilityAvailable(): bool
    {
        return true;
    }

    public function getCaptchaForms(): array
    {
        return [
            new CaptchaFormDTO(
                key: 'post_comment',
                name: 'dixlase-blog::captcha.forms.post_comment',
                route: 'dixlase-blog::front.comment',
                category: 'contact',
                defaultEnabled: true,
                priority: 300,
            ),
        ];
    }
}
