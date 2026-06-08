import { defineConfig } from 'vite';
import jengo from '@jengo/vite';
import tailwindcss from '@tailwindcss/vite'

export default defineConfig({
    plugins: [
        jengo(),
        tailwindcss(),
    ]
});
