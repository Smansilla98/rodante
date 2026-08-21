import { defineConfig } from '@playwright/test';

export default defineConfig({
  testDir: './e2e',
  timeout: 30_000,
  use: {
    baseURL: 'http://localhost:8093',
    trace: 'on-first-retry',
  },
  reporter: 'list',
});
