{{--
This file is part of Dixlase Blog.

Copyright (C) 2026 exc-D inc.
https://exc-d.com

Dixlase Blog is dual-licensed. Same terms as edit.blade.php.

Comments under a post: the published ones as a tree (_comment), then the
form while the post takes comments. Comments are shared by every language
of the post. Overridable.
--}}

@if ($commentsShown ?? false)
    <section class="dixlase-blog-comments" id="comments" aria-labelledby="comments-heading">
        <h2 class="dixlase-blog-comments__heading" id="comments-heading">
            {{ __('dixlase-blog::front.comments.heading') }}
            @if ($commentCount > 0)
                <span class="dixlase-blog-comments__count">({{ $commentCount }})</span>
            @endif
        </h2>

        @if ($commentTree !== [])
            <ol class="dixlase-blog-comments__list">
                @foreach ($commentTree as $node)
                    @include('dixlase-blog::front._comment', ['node' => $node, 'depth' => 0])
                @endforeach
            </ol>
        @elseif ($commentsOpen)
            <p class="dixlase-blog-comments__empty">{{ __('dixlase-blog::front.comments.empty') }}</p>
        @endif

        @if ($commentsOpen)
            <div class="dixlase-blog-comment-form" id="comment-form">
                <h3 class="dixlase-blog-comment-form__heading">
                    {{ $commentReplyTo ? __('dixlase-blog::front.comments.reply_heading', ['name' => $commentReplyTo->author_name]) : __('dixlase-blog::front.comments.form_heading') }}
                </h3>

                @if ($commentFlash === 'published')
                    <p class="dixlase-blog-comment-form__notice" role="status">{{ __('dixlase-blog::front.comments.published_notice') }}</p>
                @elseif ($commentFlash === 'pending')
                    <p class="dixlase-blog-comment-form__notice" role="status">{{ __('dixlase-blog::front.comments.pending_notice') }}</p>
                @endif

                @if ($errors->any())
                    <ul class="dixlase-blog-comment-form__errors" role="alert">
                        @foreach ($errors->all() as $message)
                            <li>{{ $message }}</li>
                        @endforeach
                    </ul>
                @endif

                <form method="POST" action="{{ route('dixlase-blog::front.comment', ['slug' => $post->slug]) }}">
                    @csrf
                    @if ($commentReplyTo)
                        <input type="hidden" name="parent_id" value="{{ $commentReplyTo->id }}">
                        <p class="dixlase-blog-comment-form__replying">
                            <a href="{{ $postUrl }}#comment-form">{{ __('dixlase-blog::front.comments.cancel_reply') }}</a>
                        </p>
                    @endif

                    @if ($commentSiteCommenter)
                        <p class="dixlase-blog-comment-form__as-site">{{ __('dixlase-blog::front.comments.as_site', ['name' => $commentSiteCommenter->display_name ?: $commentSiteCommenter->account_name]) }}</p>
                    @else
                        <div class="dixlase-blog-comment-form__row">
                            <label class="dixlase-blog-comment-form__label" for="comment-author-name">{{ __('dixlase-blog::front.comments.name_label') }}</label>
                            <input class="dixlase-blog-comment-form__input" id="comment-author-name" type="text" name="author_name"
                                   value="{{ old('author_name') }}" maxlength="{{ $commentNameMax }}" required autocomplete="name">
                        </div>
                        <div class="dixlase-blog-comment-form__row">
                            <label class="dixlase-blog-comment-form__label" for="comment-author-email">{{ __('dixlase-blog::front.comments.email_label') }}</label>
                            <input class="dixlase-blog-comment-form__input" id="comment-author-email" type="email" name="author_email"
                                   value="{{ old('author_email') }}" maxlength="255" required autocomplete="email"
                                   aria-describedby="comment-author-email-help">
                            <p class="dixlase-blog-comment-form__help" id="comment-author-email-help">{{ __('dixlase-blog::front.comments.email_help') }}</p>
                        </div>
                    @endif

                    <div class="dixlase-blog-comment-form__row">
                        <label class="dixlase-blog-comment-form__label" for="comment-body">{{ __('dixlase-blog::front.comments.body_label') }}</label>
                        <textarea class="dixlase-blog-comment-form__input" id="comment-body" name="body" rows="5"
                                  maxlength="{{ $commentBodyMax }}" required>{{ old('body') }}</textarea>
                    </div>

                    {{-- Honeypot: hidden from people and from assistive technology. --}}
                    <div class="dixlase-blog-comment-form__trap" aria-hidden="true">
                        <label for="comment-website">{{ __('dixlase-blog::front.comments.trap_label') }}</label>
                        <input id="comment-website" type="text" name="website" value="" tabindex="-1" autocomplete="off">
                    </div>

                    @unless ($commentSiteCommenter)
                        <div class="dixlase-blog-comment-form__row">
                            <x-form-toggle
                                id="comment-privacy"
                                name="privacy_agreed"
                                :required="true"
                                :rawLabel="$commentPrivacyUrl !== null"
                                :label="$commentPrivacyUrl !== null
                                    ? __('dixlase-blog::front.comments.privacy_consent_link', ['url' => e($commentPrivacyUrl)])
                                    : __('dixlase-blog::front.comments.privacy_consent')"
                                :checked="(bool) old('privacy_agreed')"
                            />
                        </div>

                        <x-captcha :enabled="$captchaEnabled ?? false" :widget="$captchaWidget ?? null" />
                    @endunless

                    <button type="submit" class="dixlase-blog-comment-form__submit">{{ __('dixlase-blog::front.comments.submit') }}</button>
                </form>
            </div>
        @endif
    </section>
@endif
