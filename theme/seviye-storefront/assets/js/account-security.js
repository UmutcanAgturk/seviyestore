/**
 * "Hesap Güvenliği" (2FA) card - shared by templates/zone.php (/admin,
 * /sube) and templates/parent-dashboard.php (/) via the same partial
 * (templates/partials/account-security.php); this script binds to whichever
 * one is actually on the page via getElementById, so it only ever runs
 * once per request regardless of zone.
 *
 * Three states, one at a time: disabled (no secret yet) -> setup (secret
 * generated, awaiting a confirming code) -> enabled. There is no server
 * round-trip to re-check state between steps other than the initial load -
 * confirm()/disable() locally flip the visible state on success, matching
 * every other panel script's pattern in this theme.
 *
 * Expects two globals localized from PHP (see inc/assets.php):
 *   scpPanel     { restUrl, nonce }
 *   scpPanelText { ...translated UI strings }
 */
(function () {
    'use strict';

    var root = document.getElementById('scp-account-security-panel');

    if (!root || typeof scpPanel === 'undefined') {
        return;
    }

    // Captured once, synchronously, at script load - NOT re-read later
    // via the bare `scpPanel`/`scpPanelText` globals, which several
    // OTHER scripts on this same page (account-security.js,
    // privacy-requests-panel.js, support-tickets-panel.js - all
    // unconditionally enqueued on every admin/sube page) also localize
    // under the SAME global variable names. Whichever of those loads
    // LAST overwrites `window.scpPanel`/`window.scpPanelText` for the
    // whole page; code that reads the bare global later (inside an
    // apiFetch().then() callback, a click handler, ...) would silently
    // see THAT other script's narrower data instead of this page's own
    // - capturing a local reference immediately, before any later
    // script's localize tag runs, avoids that.
    var scpPanelData = scpPanel;
    var scpPanelTextData = typeof scpPanelText !== 'undefined' ? scpPanelText : {};

    var statusEl = root.querySelector('[data-scp-2fa-status]');
    var disabledBlock = root.querySelector('[data-scp-2fa-disabled]');
    var setupBlock = root.querySelector('[data-scp-2fa-setup]');
    var enabledBlock = root.querySelector('[data-scp-2fa-enabled]');
    var secretEl = root.querySelector('[data-scp-2fa-secret]');
    var uriLink = root.querySelector('[data-scp-2fa-uri]');
    var startButton = root.querySelector('[data-scp-2fa-start]');
    var confirmForm = root.querySelector('[data-scp-2fa-confirm-form]');
    var disableForm = root.querySelector('[data-scp-2fa-disable-form]');
    var passwordStatusEl = root.querySelector('[data-scp-password-status]');
    var passwordForm = root.querySelector('[data-scp-password-form]');

    function setStatus(message, isError) {
        statusEl.textContent = message || '';
        statusEl.classList.toggle('scp-status--error', Boolean(isError));
    }

    var apiFetch = scpApiFetch;

    function showState(state) {
        disabledBlock.hidden = state !== 'disabled';
        setupBlock.hidden = state !== 'setup';
        enabledBlock.hidden = state !== 'enabled';
    }

    function loadStatus() {
        apiFetch('security/2fa/status').then(function (result) {
            if (!result.ok) {
                setStatus(scpPanelTextData.loadError, true);
                return;
            }

            showState(result.data.enabled ? 'enabled' : 'disabled');
        });
    }

    startButton.addEventListener('click', function () {
        apiFetch('security/2fa/setup', { method: 'POST' }).then(function (result) {
            if (!result.ok) {
                setStatus(scpPanelTextData.saveError, true);
                return;
            }

            secretEl.textContent = result.data.secret;
            uriLink.href = result.data.otpauth_uri;
            setStatus('');
            showState('setup');
        });
    });

    confirmForm.addEventListener('submit', function (event) {
        event.preventDefault();

        apiFetch('security/2fa/confirm', {
            method: 'POST',
            body: JSON.stringify({ code: confirmForm.code.value.trim() })
        }).then(function (result) {
            if (!result.ok) {
                setStatus(scpPanelTextData.twoFactorInvalidCode, true);
                return;
            }

            confirmForm.reset();
            setStatus(scpPanelTextData.twoFactorEnabled);
            showState('enabled');
        });
    });

    disableForm.addEventListener('submit', function (event) {
        event.preventDefault();

        apiFetch('security/2fa/disable', {
            method: 'POST',
            body: JSON.stringify({ password: disableForm.password.value })
        }).then(function (result) {
            if (!result.ok) {
                setStatus(scpPanelTextData.twoFactorWrongPassword, true);
                return;
            }

            disableForm.reset();
            setStatus(scpPanelTextData.twoFactorDisabled);
            showState('disabled');
        });
    });

    if (passwordForm) {
        passwordForm.addEventListener('submit', function (event) {
            event.preventDefault();

            apiFetch('security/password', {
                method: 'PUT',
                body: JSON.stringify({
                    current_password: passwordForm.current_password.value,
                    new_password: passwordForm.new_password.value
                })
            }).then(function (result) {
                if (!result.ok) {
                    var reason = result.data && result.data.reason;
                    var message = reason === 'invalid_password'
                        ? scpPanelTextData.twoFactorWrongPassword
                        : (reason === 'weak_password' ? scpPanelTextData.passwordTooWeak : scpPanelTextData.saveError);
                    passwordStatusEl.textContent = message;
                    passwordStatusEl.classList.add('scp-status--error');
                    return;
                }

                passwordForm.reset();
                passwordStatusEl.classList.remove('scp-status--error');
                passwordStatusEl.textContent = scpPanelTextData.passwordChanged;

                if (typeof window.scpSuccessPulse === 'function') {
                    window.scpSuccessPulse(passwordStatusEl);
                }
            });
        });
    }

    loadStatus();
})();
