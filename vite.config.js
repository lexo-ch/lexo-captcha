import { defineConfig } from 'vite';

export default defineConfig({
    build: {
        outDir: 'dist',
        rollupOptions: {
            input: {
                admin: 'resources/admin.js',
                front: 'resources/front.js',
            },
            output: {
                dir: 'dist/',
                entryFileNames: '[name].js',
                chunkFileNames: '[name].js',
                assetFileNames: '[name].[ext]',
            }
        }
    }
});