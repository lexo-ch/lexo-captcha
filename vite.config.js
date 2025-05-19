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
			},
			treeshake: false, // This is the key setting: disable tree-shaking to preserve unexported variables
		},
		target: 'esnext',
		minify: 'terser',
		terserOptions: {
			mangle: false, // Disable name mangling
			keep_fnames: true, // Keep function names
			compress: {
				keep_infinity: true,
				drop_console: false,
				keep_classnames: true, // Preserve class names
				keep_fargs: true, // Keep function arguments
				pure_getters: false, // Don't remove unused getters
				unused: false, // Don't remove unused variables and functions
				dead_code: false, // Don't remove unreachable code
			},
			format: {
				comments: false,
			},
		},
	},
});