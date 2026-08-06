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

    // "Yükleniyor göstergesi" - every panel script's REST call funnels
    // through this one function, so hooking window.scpBeginNetworkActivity()/
    // scpEndNetworkActivity() (scp-ui-kit.js) HERE shows a single page-level
    // progress bar for as long as ANY request is in flight, without each
    // panel script having to wire up its own button/spinner state. Guarded
    // with typeof checks since script load order between this file and
    // scp-ui-kit.js isn't guaranteed (both enqueued with empty dependency
    // arrays - see inc/assets.php) - harmless no-op if scp-ui-kit.js hasn't
    // defined them yet, which in practice never happens since scpApiFetch()
    // is only ever called from an event handler, well after every enqueued
    // script has already run.
    if (typeof scpBeginNetworkActivity === 'function') {
        scpBeginNetworkActivity();
    }

    function endActivity(value) {
        if (typeof scpEndNetworkActivity === 'function') {
            scpEndNetworkActivity();
        }

        return value;
    }

    function endActivityAndRethrow(error) {
        if (typeof scpEndNetworkActivity === 'function') {
            scpEndNetworkActivity();
        }

        throw error;
    }

    return fetch(scpPanel.restUrl + path, options).then(function (response) {
        return response.json().then(function (data) {
            if (response.status === 401 && data && data.code === 'rest_cookie_invalid_nonce') {
                data.message = (typeof scpPanelText !== 'undefined' && scpPanelText.sessionExpired)
                    || 'Oturum bilgisi güncel değil. Lütfen sayfayı yenileyip tekrar deneyin.';
            }

            return { ok: response.ok, status: response.status, data: data };
        });
    }).then(endActivity, endActivityAndRethrow);
}

/**
 * Uploads a file through WordPress' own core /wp/v2/media REST endpoint -
 * not scpApiFetch(), which always forces a JSON Content-Type header; a
 * multipart/form-data upload needs the browser to set that header itself
 * (with the correct boundary), so it must not be overridden here. Requires
 * scpPanel.wpRestRoot (the site's REST root, distinct from scpPanel.restUrl
 * which is scoped to seviye/v1/) and the `upload_files` capability - see
 * Seviye\Commerce\CommerceModule / Seviye\Core\Support\CoreServiceProvider,
 * which grant it to the roles that need to upload a product photo or the
 * platform logo.
 */
function scpUploadMedia(file) {
    var formData = new FormData();
    formData.append('file', file);

    if (typeof scpBeginNetworkActivity === 'function') {
        scpBeginNetworkActivity();
    }

    return fetch(scpPanel.wpRestRoot + 'wp/v2/media', {
        method: 'POST',
        headers: { 'X-WP-Nonce': scpPanel.nonce },
        credentials: 'same-origin',
        body: formData
    }).then(function (response) {
        return response.json().then(function (data) {
            return { ok: response.ok, status: response.status, data: data };
        });
    }).then(function (value) {
        if (typeof scpEndNetworkActivity === 'function') {
            scpEndNetworkActivity();
        }

        return value;
    }, function (error) {
        if (typeof scpEndNetworkActivity === 'function') {
            scpEndNetworkActivity();
        }

        throw error;
    });
}
