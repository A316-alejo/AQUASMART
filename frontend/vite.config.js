import { defineConfig } from 'vite';
import react from '@vitejs/plugin-react';

// Laravel corre con `php artisan serve` en :8000. El proxy hace que el navegador vea un solo origen
// (localhost:5173), así la cookie de sesión y el XSRF-TOKEN funcionan sin configurar CORS.
export default defineConfig({
  plugins: [react()],
  server: {
    port: 5173,
    proxy: { '/api': 'http://localhost:8000' },
  },
});
