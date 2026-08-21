import { expect, test } from '@playwright/test';

test('smoke de navegación operativa', async ({ page }) => {
  await page.goto('/login');
  await page.getByLabel('Usuario').fill('admin');
  await page.getByLabel('Contraseña').fill('password');
  await page.getByRole('button', { name: 'Ingresar' }).click();

  await expect(page).toHaveURL(/dashboard/);
  await expect(page.locator('main')).toBeVisible();

  for (const path of ['/compras', '/unidades', '/campo']) {
    await page.goto(path);
    await expect(page.locator('main')).toBeVisible();
    await expect(page).not.toHaveURL(/login/);
  }
});
