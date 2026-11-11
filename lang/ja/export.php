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
 */

return [
    'not_local' => '書き出しは、内容をあとでデプロイするローカルのサイト向けです。APP_ENV が「local」のときだけ動きます。ここで動かすには --allow-non-local を付けてください。',
    'post_not_found' => 'スラッグまたは ID が「:identifier」の記事がありません。',
    'not_markdown' => '記事「:slug」は Markdown で書かれていないため、ファイルに書き出せません。',
    'file_exists' => ':path は既にあります。上書きするには --force を付けてください。',
    'written' => '記事 #:id（:slug）を :path に書き出しました。',
];
