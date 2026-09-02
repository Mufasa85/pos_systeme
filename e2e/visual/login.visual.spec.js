const { test, expect } = require('@playwright/test');

test('login page visual snapshot', async ({ page }) => {
  await page.goto('/');
  await expect(page).toHaveScreenshot('login.png', { maxDiffPixelRatio: 0.05 });
});
