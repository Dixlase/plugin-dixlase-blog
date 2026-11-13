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

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Plugins\DixlaseBlog\App\Models\BlogPost;
use Plugins\DixlaseBlog\App\Services\BlogPostExporter;
use Plugins\DixlaseBlog\App\Support\Import\PostImportException;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Write a post as a Markdown file with front matter (#235)
 *
 * The file is the one `dls:blog:post:import` reads, so a post edited in the
 * admin can be continued in a file and imported again. See
 * {@see BlogPostExporter} for what the file holds.
 */
class ExportPost extends Command
{
    protected $signature = 'dls:blog:post:export
                            {post : The post\'s slug or ID}
                            {--output= : Write to this file instead of standard output; the post\'s CSS and JavaScript go next to it}
                            {--force : Overwrite the output files if they exist}';

    protected $description = 'Write a blog post as a Markdown file with front matter, as the import reads it';

    public function handle(BlogPostExporter $exporter): int
    {
        $identifier = trim((string) $this->argument('post'));
        $post = BlogPost::query()->where('slug', $identifier)->first()
            ?? (ctype_digit($identifier) ? BlogPost::query()->find((int) $identifier) : null);
        if (! $post instanceof BlogPost) {
            $this->error(__('dixlase-blog::export.post_not_found', ['identifier' => $identifier]));

            return self::FAILURE;
        }

        $output = trim((string) $this->option('output'));

        try {
            // The CSS and JavaScript are named after the post file (#251).
            $export = $exporter->export($post, $output === '' ? null : pathinfo($output, PATHINFO_FILENAME));
        } catch (PostImportException $e) {
            foreach ($e->errors as $error) {
                $this->error($error);
            }

            return self::FAILURE;
        }

        if ($output === '') {
            $this->output->write($export['markdown'], false, OutputInterface::OUTPUT_RAW);
            if ($export['omitted'] !== []) {
                // On standard error, so the file written from standard output stays clean.
                $this->getOutput()->getErrorStyle()->warning(__('dixlase-blog::export.assets_omitted', ['keys' => implode(', ', $export['omitted'])]));
            }

            return self::SUCCESS;
        }

        $dir = dirname($output);
        $files = [$output => $export['markdown']];
        foreach ($export['assets'] as $name => $contents) {
            $files[$dir.DIRECTORY_SEPARATOR.$name] = $contents;
        }

        // Nothing is written unless every file may be.
        if (! $this->option('force')) {
            foreach (array_keys($files) as $path) {
                if (File::exists($path)) {
                    $this->error(__('dixlase-blog::export.file_exists', ['path' => $path]));

                    return self::FAILURE;
                }
            }
        }

        File::ensureDirectoryExists($dir);
        foreach ($files as $path => $contents) {
            File::put($path, $contents);
        }
        $this->info(__('dixlase-blog::export.written', ['id' => $post->id, 'slug' => $post->slug, 'path' => $output]));
        foreach (array_keys($export['assets']) as $name) {
            $this->line('  '.$dir.DIRECTORY_SEPARATOR.$name);
        }

        return self::SUCCESS;
    }
}
