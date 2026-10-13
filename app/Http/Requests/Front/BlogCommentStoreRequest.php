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

namespace Plugins\DixlaseBlog\App\Http\Requests\Front;

use App\Helpers\CaptchaHelper;
use App\Traits\VerifiesCaptcha;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Plugins\DixlaseBlog\App\Services\BlogCommentCaptchaFormProvider;
use Plugins\DixlaseBlog\App\Services\BlogComments;

/**
 * A comment posted from a post page. A visitor gives a name, an e-mail
 * address and consent to the privacy notice; an editor or above signed in
 * comments as the site and gives only the body.
 */
class BlogCommentStoreRequest extends FormRequest
{
    use VerifiesCaptcha {
        withValidator as verifyCaptchaWith;
    }

    public const BODY_MAX = 5000;

    public const NAME_MAX = 100;

    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $rules = [
            'body' => ['required', 'string', 'max:'.self::BODY_MAX],
            'parent_id' => ['nullable', 'integer'],
            // The honeypot: hidden from people, so only a bot fills it in.
            // Checked by the controller, which then pretends to accept.
            'website' => ['nullable'],
        ];

        if ($this->isBySite()) {
            return $rules;
        }

        $rules += [
            'author_name' => ['required', 'string', 'max:'.self::NAME_MAX],
            'author_email' => ['required', 'string', 'email', 'max:255'],
            'privacy_agreed' => ['accepted'],
        ];

        if (CaptchaHelper::shouldShowCaptcha($this->captchaFormKey())) {
            $rules = array_merge($rules, $this->getCaptchaRules());
        }

        return $rules;
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'body' => __('dixlase-blog::front.comments.body_label'),
            'author_name' => __('dixlase-blog::front.comments.name_label'),
            'author_email' => __('dixlase-blog::front.comments.email_label'),
            'privacy_agreed' => __('dixlase-blog::front.comments.privacy_label'),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'privacy_agreed.accepted' => __('dixlase-blog::front.comments.privacy_required'),
        ];
    }

    public function isBySite(): bool
    {
        return app(BlogComments::class)->siteCommenter() !== null;
    }

    /**
     * The site's own comments skip the CAPTCHA.
     */
    public function withValidator(Validator $validator): void
    {
        if (! $this->isBySite()) {
            $this->verifyCaptchaWith($validator);
        }
    }

    protected function captchaFormKey(): ?string
    {
        return BlogCommentCaptchaFormProvider::FORM_KEY;
    }

    /**
     * Back to the form, not to the top of the post.
     */
    protected function getRedirectUrl(): string
    {
        return strtok(parent::getRedirectUrl(), '#').'#comment-form';
    }
}
