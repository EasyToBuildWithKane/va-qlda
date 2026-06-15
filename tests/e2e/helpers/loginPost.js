/**
 * POST /tech/login with CSRF (after visiting /tech/login in the same browser context).
 */
export async function postLogin(page, { username, password }) {
    await page.goto('/tech/login');
    const cookies = await page.context().cookies();
    const xsrfCookie = cookies.find((c) => c.name === 'XSRF-TOKEN');
    const csrfMeta = await page.locator('meta[name="csrf-token"]').getAttribute('content');
    const xsrf = xsrfCookie?.value
        ? decodeURIComponent(xsrfCookie.value)
        : (csrfMeta ?? '');

    return page.request.post('/tech/login', {
        headers: {
            'X-XSRF-TOKEN': xsrf,
            Accept: 'text/html,application/xhtml+xml',
        },
        form: { username, password },
        maxRedirects: 0,
    });
}
