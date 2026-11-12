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
                            {paths* : Markdown files to import, or directories whose *.md files are imported}
                            {--as= : The post\'s author: the member\'s account name or e-mail address}
                            {--storage= : Where the post is stored: file or database (default: the blog\'s setting for a new post, unchanged for an existing one)}
                            {--force : Import even when the post was changed since the file was taken}
                            {--draft : Save every post as a draft, whatever status the file gives}
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
        [$files, $many] = $this->files((array) $this->argument('paths'));

        // One file keeps the single-post output it always had.
        if (! $many) {
            $outcome = $this->importOne($importer, $files[0], $author, $dryRun);
            if (! $outcome['ok']) {
                return $this->failWith($outcome['errors']);
            }
            $this->option('json') ? $this->printJson($outcome['result'], $dryRun) : $this->printText($outcome['result'], $dryRun);

            return self::SUCCESS;
        }

        // Several files (#237): each is imported on its own, and one that
        // fails saves nothing and does not stop the others.
        $outcomes = [];
        foreach ($files as $file) {
            if (! $this->option('json')) {
                $this->newLine();
                $this->line('<comment>'.$file.'</comment>');
            }

            $outcome = $this->importOne($importer, $file, $author, $dryRun);
            $outcomes[] = ['file' => $file] + $outcome;

            if ($this->option('json')) {
                continue;
            }
            if ($outcome['ok']) {
                $this->printText($outcome['result'], $dryRun);
            } else {
                foreach ($outcome['errors'] as $error) {
                    $this->error($error);
                }
            }
        }

        $failed = count(array_filter($outcomes, fn (array $outcome): bool => ! $outcome['ok']));

        if ($this->option('json')) {
            $this->line((string) json_encode([
                'ok' => $failed === 0,
                'dry_run' => $dryRun,
                'results' => array_map(fn (array $outcome): array => $outcome['ok']
                    ? ['file' => $outcome['file']] + $this->jsonResult($outcome['result'], $dryRun)
                    : ['file' => $outcome['file'], 'ok' => false, 'errors' => $outcome['errors']], $outcomes),
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
        } else {
            $this->newLine();
            $this->line(__($dryRun ? 'dixlase-blog::import.summary_dry_run' : 'dixlase-blog::import.summary', [
                'ok' => count($outcomes) - $failed,
                'failed' => $failed,
            ]));
        }

        return $failed === 0 ? self::SUCCESS : self::FAILURE;
    }

    /**
     * The files to import, and whether there is more than one
     *
     * A directory stands for the `*.md` files directly inside it, in name
     * order; it counts as several even when it holds one, so a script gets
     * the same output shape whatever the directory holds.
     *
     * @param  array<int, mixed>  $paths
     * @return array{0: non-empty-list<string>, 1: bool}
     */
    private function files(array $paths): array
    {
        $files = [];
        $many = count($paths) > 1;

        foreach ($paths as $path) {
            $path = (string) $path;
            if (! is_dir($path)) {
                $files[] = $path;

                continue;
            }

            $many = true;
            $found = glob(rtrim($path, '/').'/*.md') ?: [];
            sort($found);
            // An empty directory is reported as a failure of its own.
            array_push($files, ...($found !== [] ? $found : [$path]));
        }

        return [$files === [] ? [''] : $files, $many];
    }

    /**
     * @return array{ok: true, result: array{action: string, post: ?\Plugins\DixlaseBlog\App\Models\BlogPost, status: string, storage: string, images: list<array<string, mixed>>}}|array{ok: false, errors: list<string>}
     */
    private function importOne(BlogPostImporter $importer, string $file, Member $author, bool $dryRun): array
    {
        if (is_dir($file)) {
            return ['ok' => false, 'errors' => [__('dixlase-blog::import.no_markdown_files', ['path' => $file])]];
        }

        $storage = $this->option('storage');
        $storage = is_string($storage) && trim($storage) !== '' ? trim($storage) : null;

        try {
            return ['ok' => true, 'result' => $importer->import($file, $author, $dryRun, $storage, (bool) $this->option('force'), (bool) $this->option('draft'))];
        } catch (PostImportException $e) {
            return ['ok' => false, 'errors' => $e->errors];
        } catch (Throwable $e) {
            report($e);

            return ['ok' => false, 'errors' => [$e->getMessage()]];
        }
    }

    private function resolveAuthor(): ?Member
    {
        $identifier = trim((string) $this->option('as'));
        if ($identifier === '') {
            $this->error(__('dixlase-blog::import.member_required'));

            return null;
        }

        // An account name may be digits only, so an ID is not accepted: a
        // mistyped number would otherwise name another real member (#245).
        $member = str_contains($identifier, '@')
            ? Member::where('email', $identifier)->first()
            : Member::where('account_name', $identifier)->first();

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
     * @param  array{action: string, post: ?\Plugins\DixlaseBlog\App\Models\BlogPost, status: string, storage: string, images: list<array<string, mixed>>}  $result
     */
    private function printJson(array $result, bool $dryRun): void
    {
        $this->line((string) json_encode($this->jsonResult($result, $dryRun), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
    }

    /**
     * @param  array{action: string, post: ?\Plugins\DixlaseBlog\App\Models\BlogPost, status: string, storage: string, images: list<array<string, mixed>>}  $result
     * @return array<string, mixed>
     */
    private function jsonResult(array $result, bool $dryRun): array
    {
        $post = $result['post'];

        return [
            'ok' => true,
            'dry_run' => $dryRun,
            'action' => $result['action'],
            'status' => $result['status'],
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
        ];
    }

    /**
     * @param  array{action: string, post: ?\Plugins\DixlaseBlog\App\Models\BlogPost, status: string, storage: string, images: list<array<string, mixed>>}  $result
     */
    private function printText(array $result, bool $dryRun): void
    {
        $post = $result['post'];

        if ($dryRun) {
            $this->info($post === null
                ? __('dixlase-blog::import.would_create')
                : __('dixlase-blog::import.would_update', ['id' => $post->id, 'slug' => $post->slug]));
            $this->line(__('dixlase-blog::import.would_status', ['status' => $result['status']]));
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
