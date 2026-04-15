import { defineConfig } from 'vite';
import react from '@vitejs/plugin-react';
import path from 'path';

export default defineConfig({
    plugins: [ react() ],
    root: 'assets/builder',
    build: {
        outDir: 'dist',
        emptyOutDir: true,
        manifest: true,
        rollupOptions: {
            input: path.resolve( __dirname, 'assets/builder/src/index.jsx' ),
        },
    },
    server: {
        port: 5173,
        strictPort: true,
    },
});
