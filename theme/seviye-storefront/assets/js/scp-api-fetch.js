/**
 * Shared authenticated REST helper for every panel script (see
 * inc/assets.php - enqueued as a dependency of each one, replacing what
 * used to be an identical copy of this same function duplicated in all 11
 * panel scripts).
 *
 * Also the single place that recognizes a stale/invalid X-WP-Nonce
 * (WordPress' own "Çerez denetlenemedi" / rest_cookie_invalid_nonce
 * error): the nonce is baked into the page's HTML at load time
 * (wp_localize_script), so once it stops matching the current session -
 * most commonly because a page-caching plugin served this HTML to a
 * different/later session, or the tab was left open long enough for the
 * nonce's validity window to lapse - no amount of retrying in JS fixes it;
 * only a fresh page load (which bakes in a new nonce) does. Rather than
 * showing WordPress' own generic message, every panel now shows a message
 * that tells the admin what to actually do about it.
 *
 * Expects the global `scpPanel = { restUrl, nonce, ... }` localized on
 * every script that uses this (see inc/assets.php).
 */
function scpApiFetch(path, options) {
    options = options || {};
    options.headers = Object.assign(
        { 'Content-Type': 'application/json', 'X-WP-Nonce': scpPanel.nonce },
        options.headers || {}
    );
    options.credentials = 'same-origin';

    return fetch(scpPanel.restUrl + path, options).then(function (response) {
        return response.json().then(function (data) {
            if (response.status === 401 && data && data.code === 'rest_cookie_invalid_nonce') {
                data.message = (typeof scpPanelText !== 'undefined' && scpPanelText.sessionExpired)
                    || 'Oturum bilgisi güncel değil. Lütfen sayfayı yenileyip tekrar deneyin.';
            }

            return { ok: response.ok, status: response.status, data: data };
        });
    });
}
