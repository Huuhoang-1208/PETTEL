import { expect, test } from '@playwright/test';

test('guest discovery calls the real Laravel listings endpoint', async ({ page }) => {
  const response = page.waitForResponse((item) => item.url().includes('/api/listings') && item.request().method() === 'GET');
  await page.goto('./');
  await expect(page.getByRole('heading', { name: /Thêm an tâm cho mỗi ngày xa nhà/i })).toBeVisible();
  expect((await response).status()).toBe(200);
  await expect(page.getByRole('heading', { name: 'Dịch vụ đang công khai' })).toBeVisible();
});

test('guest is redirected away from the Owner pet management screen', async ({ page }) => {
  await page.goto('./pets');
  await expect(page).toHaveURL(/\/owner\/login\?redirect=/);
  await expect(page.getByRole('heading', { name: 'Đăng nhập PETTEL' })).toBeVisible();
});

test('public listing detail is opened from a real Laravel listing record', async ({ page, request }) => {
  const response = await request.get(`${process.env.PETTEL_E2E_API_BASE_URL ?? 'http://localhost:8080'}/api/listings`);
  expect(response.status()).toBe(200);
  const payload = await response.json();
  const listing = payload.data?.[0];
  test.skip(!listing, 'The local backend has no public listing available for this read-only E2E.');

  await page.goto(`./listings/${listing.id}`);
  await expect(page.getByRole('heading', { name: listing.title })).toBeVisible();
  await expect(page.getByRole('link', { name: 'Chọn thời gian đặt' })).toBeVisible();
});

test('owner can sign in and read only their own pet collection from Laravel', async ({ page }) => {
  const email = process.env.PETTEL_E2E_OWNER_EMAIL;
  const password = process.env.PETTEL_E2E_OWNER_PASSWORD;
  test.skip(!email || !password, 'Set dedicated local test-account credentials to run authenticated Owner E2E.');

  await page.goto('./login');
  await page.getByLabel('Email').fill(email);
  await page.getByLabel('Mật khẩu').fill(password);
  await page.getByRole('button', { name: 'Đăng nhập' }).click();
  await expect(page.getByRole('link', { name: 'Thú cưng' })).toBeVisible();
  const petsResponse = page.waitForResponse((item) => item.url().includes('/api/pets') && item.request().method() === 'GET');
  await page.getByRole('link', { name: 'Thú cưng' }).click();
  expect((await petsResponse).status()).toBe(200);
  await expect(page.getByRole('heading', { name: 'Thú cưng của tôi' })).toBeVisible();
});
