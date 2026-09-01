const { test, expect } = require('@playwright/test');

test.describe('Login', () => {
  test('page loads and body is visible', async ({ page }) => {
    await page.goto('/');
    await expect(page.locator('body')).toBeVisible();
  });
});
