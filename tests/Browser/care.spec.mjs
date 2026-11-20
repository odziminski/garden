import { expect, test } from '@playwright/test';

async function signIn(page) {
    await page.goto('/login');
    await page.getByLabel('Email address').fill('demo@example.test');
    await page.getByLabel('Password', { exact: true }).fill('bloomify-demo');
    await page.getByRole('button', { name: 'Log in' }).click();
    await expect(page).toHaveURL(/\/today$/);
}

test('the daily workflow works from login through undo and first-plant setup', async ({ page }) => {
    await signIn(page);
    await page.getByRole('button', { name: 'Mark Demo fern as watered' }).click();
    await expect(page.getByText('Care recorded.')).toBeVisible();
    await page.getByRole('button', { name: 'Undo care entry' }).click();
    await expect(page.getByText('Care entry undone.')).toBeVisible();
    await expect(page.getByRole('button', { name: 'Mark Demo fern as watered' })).toBeVisible();

    await page.goto('/add-plant');
    await page.getByLabel('Plant name').fill('Browser cactus');
    await expect(page.getByText('Soil check reminder (optional)', { exact: true })).toBeVisible();
    await page.getByRole('button', { name: 'Add plant' }).click();
    await expect(page.getByRole('link', { name: 'Browser cactus', exact: true })).toBeVisible();
});

test('a room check can end with moist soil and leave watering history empty', async ({ page }) => {
    await signIn(page);
    await page.goto('/add-plant');
    await page.getByLabel('Plant name').fill('Browser mint');
    await page.getByText('Location and care notes', { exact: true }).click();
    await page.getByLabel('Room or location').fill('Browser kitchen');
    await page.getByText('Soil check reminder (optional)', { exact: true }).click();
    await page.getByLabel('Repeat every (days)').fill('5');
    await page.getByRole('button', { name: 'Add plant' }).click();

    await page.goto('/today');
    await page.getByLabel('Room', { exact: true }).selectOption('Browser kitchen');
    await page.getByRole('button', { name: 'Show plants' }).click();
    await expect(page.getByRole('heading', { name: 'Browser kitchen' })).toBeVisible();
    await page.getByRole('button', { name: 'Still moist: Browser mint' }).click();
    await expect(page.getByText('Check recorded.', { exact: true })).toBeVisible();
    await page.getByRole('link', { name: 'Browser mint', exact: true }).click();
    await expect(page.getByText('No care recorded yet.', { exact: true })).toBeVisible();
    await expect(page.getByText('Still moist', { exact: true }).first()).toBeVisible();
});

test('the mobile Today page and install metadata load', async ({ page, request }) => {
    await page.setViewportSize({ width: 390, height: 844 });
    await signIn(page);
    await expect(page.getByRole('heading', { name: 'Today' })).toBeVisible();
    expect(await page.evaluate(() => document.documentElement.scrollWidth)).toBeLessThanOrEqual(390);
    const manifest = await request.get('/manifest.webmanifest');
    expect(manifest.ok()).toBeTruthy();
    expect((await manifest.json()).display).toBe('standalone');
    expect((await request.get('/care-notifications.js')).ok()).toBeTruthy();
});

test('browser reminders can be enabled and disabled from the profile', async ({ page, context }) => {
    await context.grantPermissions(['notifications']);
    await page.addInitScript(() => {
        let registered = null;
        Notification.requestPermission = async () => 'granted';
        PushManager.prototype.getSubscription = async () => registered;
        PushManager.prototype.subscribe = async () => {
            const endpoint = 'https://fcm.googleapis.com/fcm/send/browser-test';
            registered = {
                endpoint,
                toJSON: () => ({ endpoint, keys: { p256dh: 'B'.repeat(87), auth: 'A'.repeat(22) } }),
                unsubscribe: async () => {
                    registered = null;
                    return true;
                },
            };
            return registered;
        };
    });
    await signIn(page);
    await page.goto('/users');
    await page.getByRole('button', { name: 'Enable on this device' }).click();
    await expect(page.getByText('Reminders enabled on this device.')).toBeVisible();
    await page.getByRole('button', { name: 'Disable on this device' }).click();
    await expect(page.getByText('Reminders disabled on this device.')).toBeVisible();
});
