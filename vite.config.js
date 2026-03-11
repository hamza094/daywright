import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import vue from '@vitejs/plugin-vue2';
import path from 'path';

export default defineConfig({
  plugins: [
    laravel({
      input: ['resources/sass/app.scss', 'resources/js/app.js'],
      refresh: true,
    }),
    vue(),
  ],
  resolve: {
    alias: {
      '@': path.resolve(__dirname, '/resources/js'),
      '@fonts': path.resolve(__dirname, 'resources/fonts'),
      vue: 'vue/dist/vue.esm.js', // Required for Vue 2
      '~bootstrap': path.resolve(__dirname, 'node_modules/bootstrap'),
    },
  },
  build: {
    manifest: true,
    rollupOptions: {
      input: 'resources/js/app.js',
    },
  },
});
