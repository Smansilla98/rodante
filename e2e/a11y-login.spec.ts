import AxeBuilder from '@axe-core/playwright';
import { expect, test } from '@playwright/test';

test.describe('WCAG axe', () => {
  for (const path of ['/login', '/olvide-contrasena']) {
    test(`${path} sin violaciones AA críticas`, async ({ page }) => {
      await page.goto(path);
      const results = await new AxeBuilder({ page })
        .withTags(['wcag2a', 'wcag2aa', 'wcag22aa'])
        .analyze();
      expect(results.violations, JSON.stringify(results.violations, null, 2)).toEqual([]);
    });
  }
});
