import { readFileSync } from 'node:fs';
import { resolve } from 'node:path';
import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import tailwindcss from '@tailwindcss/vite';
import react from '@vitejs/plugin-react';

const packageJson = JSON.parse(readFileSync(resolve(__dirname, 'package.json'), 'utf-8'));
const appVersion = packageJson.version ?? '0.0.0';

export default defineConfig({
    define: {
        'import.meta.env.VITE_APP_VERSION': JSON.stringify(appVersion),
    },
    plugins: [
        react(),
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.js'],
            refresh: false,
        }),
        tailwindcss(),
    ],
    server: {
        host: '::',
        strictPort: true,
        hmr: {
            host: '[::1]',
            protocol: 'ws',
            port: 5173,
            clientPort: 5173,
        },
        allowedHosts: ['.localhost'],
        watch: {
            ignored: ['**/storage/framework/views/**'],
        },
    },
});
