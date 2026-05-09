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

/**
 * Plugin-local Vite config (mirrors DixlaseLegal / DixlaseInquiry / etc).
 *
 * The plugin builds independently — `npx vite build` from this directory
 * produces resources/assets/{js,css,manifest.json}. Core's plugin asset
 * loader (load_plugin_assets) reads the manifest from
 * public/assets/plugins/DixlaseBlog/, which is a symlink to
 * resources/assets/ created by `php artisan dls:plugin:symlink add
 * DixlaseBlog`. Without that symlink the bundled JS is unreachable and
 * Alpine components like blogPostEditor / blogTagInput resolve as
 * undefined at runtime.
 *
 * Earlier draft used laravel-vite-plugin with relative paths: that flow
 * fits core's vite (where the dev server serves everything from the
 * project root) but does not produce the manifest.json the plugin
 * loader expects, so swap to plain Rollup-driven config matching the
 * rest of the plugin family.
 */

import { defineConfig } from 'vite';
import path from 'path';

export default defineConfig({
    build: {
        outDir: path.resolve(__dirname, 'resources/assets'),
        // Keep any pre-existing assets so a partial build does not
        // remove e.g. images that are committed alongside the JS/CSS.
        emptyOutDir: false,
        copyPublicDir: false,
        manifest: 'manifest.json',
        rollupOptions: {
            input: {
                app: path.resolve(__dirname, 'resources/src/js/app.js'),
                style: path.resolve(__dirname, 'resources/src/css/style.scss'),
            },
            output: {
                entryFileNames: 'js/[name].js',
                chunkFileNames: 'js/[name].js',
                assetFileNames: 'css/[name][extname]',
            },
        },
    },
    css: {
        preprocessorOptions: {
            scss: {
                api: 'modern-compiler',
            },
        },
    },
});
