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

namespace Plugins\DixlaseBlog\App\Console\Commands;

use App\Enums\MemberStatus;
use App\Models\Member;
use Illuminate\Console\Command;
use Plugins\DixlaseBlog\App\Services\BlogPostImporter;
use Plugins\DixlaseBlog\App\Support\Import\PostImportException;
use Throwable;

/**
 * Create or update a post from a Markdown file with front matter (#210)
 *
 * For drafting outside the admin — by hand, with a script or with an
 * assistant — and then filling the editor in one step. The post is saved
 * as a draft unless the file says otherwise; read it in the admin before
 * publishing. See {@see BlogPostImporter} for what the file may contain.
 *
 * Importing is meant for a local site whose content is then deployed, so the
 * command refuses to run outside APP_ENV=local without --allow-non-local.
 */
class ImportPost extends Command
{
    protected $signature = 'dls:blog:post:import
                            {file : The Markdown file to import}
                            {--as= : The post\'s author (member ID or e-mail address)}
                            {--storage= : Where the post is stored: file or database (default: the blog\'s setting for a new post, unchanged for an existing one)}
                            {--dry-run : Check the file and show what would change, writing nothing}
                            {--json : Print the result as JSON}
                            {--allow-non-local : Run even when APP_ENV is not "local"}';

    protected $description = 'Create or update a blog post from a Markdown file with front matter';

    public function handle(BlogPostImporter $importer): int
    {
        if (! app()->environment('local') && ! $this->option('allow-non-local')) {
            $this->error(__('dixlase-blog::import.not_local'));

            return self::FAILURE;
        }

        $author = $this->resolveAuthor();
        if ($author === null) {
            return self::FAILURE;
        }

        $dryRun = (bool) $this->option('dry-run');

        try {
            $storage = $this->option('storage');
            $storage = is_string($storage) && trim($storage) !== '' ? trim($storage) : null;

            $result = $importer->import((string) $this->argument('file'), $author, $dryRun, $storage);
        } catch (PostImportException $e) {
            return $this->failWith($e->errors);
        } catch (Throwable $e) {
            report($e);

            return $this->failWith([$e->getMessage()]);
        }

        $this->option('json') ? $this->printJson($result, $dryRun) : $this->printText($result, $dryRun);

        return self::SUCCESS;
    }

    private function resolveAuthor(): ?Member
    {
        $identifier = trim((string) $this->option('as'));
        if ($identifier === '') {
            $this->error(__('dixlase-blog::import.member_required'));

            return null;
        }

        $member = ctype_digit($identifier)
            ? Member::find((int) $identifier)
            : Member::where('email', $identifier)->first();

        if (! $member instanceof Member) {
            $this->error(__('dixlase-blog::import.member_not_found', ['identifier' => $identifier]));

            return null;
        }
        if ($member->status !== MemberStatus::Active) {
            $this->error(__('dixlase-blog::import.member_inactive', ['identifier' => $identifier]));

            return null;
        }

        return $member;
    }

    /**
     * @param  list<string>  $errors
     */
    private function failWith(array $errors): int
    {
        if ($this->option('json')) {
            $this->line((string) json_encode(['ok' => false, 'errors' => $errors], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));

            return self::FAILURE;
        }

        foreach ($errors as $error) {
            $this->error($error);
        }
        $this->line(__('dixlase-blog::import.failed'));

        return self::FAILURE;
    }

    /**
     * @param  array{action: string, post: ?\Plugins\DixlaseBlog\App\Models\BlogPost, storage: string, images: list<array<string, mixed>>}  $result
     */
    private function printJson(array $result, bool $dryRun): void
    {
        $post = $result['post'];

        $this->line((string) json_encode([
            'ok' => true,
            'dry_run' => $dryRun,
            'action' => $result['action'],
            'storage' => $result['storage'],
            'post' => $post === null ? null : [
                'id' => $post->id,
                'slug' => $post->slug,
                'title' => $post->title,
                'lang' => $post->lang,
                'status' => $post->status->slug(),
                'storage' => $post->storage_type->slug(),
                'edit_path' => $this->editPath($post->id),
            ],
            'images' => $result['images'],
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
    }

    /**
     * @param  array{action: string, post: ?\Plugins\DixlaseBlog\App\Models\BlogPost, storage: string, images: list<array<string, mixed>>}  $result
     */
    private function printText(array $result, bool $dryRun): void
    {
        $post = $result['post'];

        if ($dryRun) {
            $this->info($post === null
                ? __('dixlase-blog::import.would_create')
                : __('dixlase-blog::import.would_update', ['id' => $post->id, 'slug' => $post->slug]));
            $this->line(__('dixlase-blog::import.would_store', ['storage' => $result['storage']]));

            if ($result['images'] !== []) {
                $this->line(__('dixlase-blog::import.would_import', ['count' => count($result['images'])]));
                foreach ($result['images'] as $image) {
                    $this->line('  '.$image['field'].': '.$image['source']);
                }
            }

            return;
        }

        if ($post === null) {
            return;
        }

        $this->info(__($result['action'] === 'create' ? 'dixlase-blog::import.created' : 'dixlase-blog::import.updated', [
            'id' => $post->id,
            'slug' => $post->slug,
            'status' => $post->status->slug(),
        ]));
        $this->line(__('dixlase-blog::import.stored', ['storage' => $post->storage_type->slug()]));

        if ($result['images'] !== []) {
            $reused = count(array_filter($result['images'], fn (array $image): bool => (bool) $image['reused']));
            $this->line(__('dixlase-blog::import.images', ['imported' => count($result['images']) - $reused, 'reused' => $reused]));
        }

        $path = $this->editPath($post->id);
        if ($path !== null) {
            $this->line($path);
        }
    }

    /**
     * The edit screen's path, when the admin routes are registered
     */
    private function editPath(int $id): ?string
    {
        try {
            return route('dixlase-blog::admin.blog.edit', ['post' => $id], false);
        } catch (Throwable) {
            return null;
        }
    }
}
