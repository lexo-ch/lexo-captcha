import {defineConfig} from 'vite';

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
			treeshake: false,
		},
		target: 'esnext',
		minify: 'terser',
		terserOptions: {
			mangle: false,
			keep_fnames: true,
			compress: {
				keep_infinity: true,
				drop_console: false,
				keep_classnames: true,
				keep_fargs: true,
				unused: false,
				dead_code: false,
			},
			format: {
				comments: false,
			},
		},
	},
	watch: {
		include: ['resources/**'],
		exclude: ['node_modules/**', 'dist/**', 'vendor/**'],
	},
});
