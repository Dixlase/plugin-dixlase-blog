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

namespace Plugins\DixlaseBlog\App\Http\Controllers\Admin;

use App\Traits\AdminInterfaceTrait;
use App\Traits\AdminLoggedInTrait;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Plugins\DixlaseBlog\App\Models\BlogComment;
use Plugins\DixlaseBlog\App\Services\BlogComments;

/**
 * Commenters' personal data, found by e-mail address: core's privacy
 * tools are keyed by member, and commenters are not members. An admin
 * removes the name, e-mail and IP address from one address's comments, or
 * deletes the comments.
 */
class DixlaseBlogAdminCommentPersonalDataController extends Controller
{
    use AdminInterfaceTrait;
    use AdminLoggedInTrait;

    public function __construct(private readonly BlogComments $comments)
    {
        $this->initialize();
        $this->initializeAfterLogin();
    }

    public function index(Request $request): View
    {
        $email = trim((string) $request->query('email', ''));
        $valid = $email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
        $found = $valid
            ? $this->comments->byEmail($email)->with(['post' => fn ($q) => $q->withTrashed()])->orderByDesc('created_at')->get()
            : collect();

        return view('dixlase-blog::admin.comments.personal-data', array_merge($this->viewParams, [
            'email' => $email,
            'emailInvalid' => $email !== '' && ! $valid,
            'searched' => $valid,
            'found' => $found->map(fn (BlogComment $comment) => [
                'id' => $comment->id,
                'author' => $comment->author_name,
                'excerpt' => Str::limit(preg_replace('/\s+/u', ' ', $comment->body) ?? '', 120),
                'post_title' => $comment->post?->title,
                'status_label' => $comment->trashed()
                    ? __('dixlase-blog::admin.comments.tab_trash')
                    : __('dixlase-blog::admin.comments.status_'.$comment->status),
                'date' => $comment->created_at,
            ])->all(),
        ]));
    }

    public function erase(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'email' => ['required', 'string', 'email', 'max:255'],
            'mode' => ['required', 'in:anonymise,delete'],
        ]);

        $count = $data['mode'] === 'delete'
            ? $this->comments->deleteByEmail($data['email'])
            : $this->comments->anonymiseByEmail($data['email']);

        return redirect()
            ->route('dixlase-blog::admin.blog.comments.personalData.index')
            ->with('success', trans_choice('dixlase-blog::admin.flash.personal_data_'.$data['mode'], $count, ['count' => $count]));
    }
}
