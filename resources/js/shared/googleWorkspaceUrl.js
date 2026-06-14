/**
 * Parse Google Docs / Sheets URLs — mirror App\Support\GoogleWorkspaceUrl (PHP).
 *
 * @param {string} url
 * @returns {{ type: 'document'|'spreadsheet', id: string, view_url: string, embed_url: string, default_title: string }|null}
 */
export function parseGoogleWorkspaceUrl(url) {
    let raw = (url ?? '').trim();
    if (!raw) return null;

    if (!/^https?:\/\//i.test(raw)) {
        raw = `https://${raw}`;
    }

    try {
        const u = new URL(raw);
        const host = u.hostname.toLowerCase();
        if (host !== 'docs.google.com' && host !== 'www.docs.google.com') {
            return null;
        }

        const docMatch = u.pathname.match(/\/document\/d\/([a-zA-Z0-9_-]+)/);
        if (docMatch) {
            const id = docMatch[1];
            return {
                type: 'document',
                id,
                view_url: `https://docs.google.com/document/d/${id}/edit`,
                embed_url: `https://docs.google.com/document/d/${id}/preview?rm=minimal`,
                default_title: 'Google Docs',
            };
        }

        const sheetMatch = u.pathname.match(/\/spreadsheets\/d\/([a-zA-Z0-9_-]+)/);
        if (sheetMatch) {
            const id = sheetMatch[1];
            return {
                type: 'spreadsheet',
                id,
                view_url: `https://docs.google.com/spreadsheets/d/${id}/edit`,
                embed_url: `https://docs.google.com/spreadsheets/d/${id}/preview?rm=minimal`,
                default_title: 'Google Sheets',
            };
        }
    } catch {
        /* ignore */
    }

    return null;
}

export function isGoogleWorkspaceUrl(url) {
    return parseGoogleWorkspaceUrl(url) !== null;
}
