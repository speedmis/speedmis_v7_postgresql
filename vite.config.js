import { defineConfig } from 'vite'
import react from '@vitejs/plugin-react'

export default defineConfig({
  // base: './' — 동적 import 된 chunk 가 main 모듈 위치 기준 상대경로 로드.
  //   localhost(/) 든 서브경로(/v7/) 든 자동 해석.
  base: './',
  plugins: [react()],
  build: {
    outDir: 'public/build',
    manifest: true,
    rollupOptions: {
      input: 'src/main.jsx',
    },
  },
  server: {
    port: 5173,
    proxy: {
      '/api.php': 'http://localhost:8087',
      '/index.php': 'http://localhost:8087',
    },
  },
})
