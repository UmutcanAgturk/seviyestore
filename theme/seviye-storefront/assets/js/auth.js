/**
 * Login screen behaviour: view switching between login / forgot-password /
 * first-password / set-password, and talking to Seviye Security's
 * seviye/v1/auth/* REST endpoints. No framework/build step - this is a
 * single, small, self-contained screen.
 *
 * Expects two globals localized from PHP (see inc/assets.php):
 *   scpAuth     { restUrl, token }
 *   scpAuthText { ...translated UI strings }
 */
(function () {
    'use strict';

    var root = document.getElementById('scp-auth');

    if (!root || typeof scpAuth === 'undefined') {
        return;
    }

    var views = {};

    root.querySelectorAll('[data-scp-view]').forEach(function (el) {
        views[el.getAttribute('data-scp-view')] = el;
    });

    var statusEl = root.querySelector('[data-scp-status]');

    function showView(name) {
        Object.keys(views).forEach(function (key) {
            views[key].hidden = key !== name;
        });
        setStatus('');
    }

    function setStatus(message, isError) {
        if (!statusEl) {
            return;
        }
        statusEl.textContent = message;
        statusEl.classList.toggle('scp-auth-status--error', Boolean(isError));
    }

    root.querySelectorAll('[data-scp-switch]').forEach(function (link) {
        link.addEventListener('click', function (event) {
            event.preventDefault();
            showView(link.getAttribute('data-scp-switch'));
        });
    });

    function postJson(path, payload) {
        return fetch(scpAuth.restUrl + path, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            credentials: 'same-origin',
            body: JSON.stringify(payload)
        }).then(function (response) {
            return response.json().then(function (data) {
                return { ok: response.ok, status: response.status, data: data };
            });
        });
    }

    function errorMessage(view, result) {
        if (result.status === 429) {
            return scpAuthText.throttled;
        }
        if (view === 'login') {
            return scpAuthText.invalidCredentials;
        }
        if (view === 'set-password' && result.data && result.data.reason === 'weak_password') {
            return scpAuthText.weakPassword;
        }
        if (view === 'set-password') {
            return scpAuthText.invalidToken;
        }
        return scpAuthText.genericError;
    }

    /**
     * @param {string} view data-scp-view name
     * @param {string} path REST path relative to scpAuth.restUrl
     * @param {(form: HTMLFormElement) => (Object|null)} buildPayload returns null to abort submission
     * @param {(data: Object) => void} onSuccess
     */
    function bindForm(view, path, buildPayload, onSuccess) {
        var form = views[view];

        if (!form) {
            return;
        }

        form.addEventListener('submit', function (event) {
            event.preventDefault();

            var payload = buildPayload(form);

            if (payload === null) {
                return;
            }

            setStatus('');
            var submitButton = form.querySelector('button[type="submit"]');

            if (submitButton) {
                submitButton.disabled = true;
            }

            postJson(path, payload)
                .then(function (result) {
                    if (submitButton) {
                        submitButton.disabled = false;
                    }
                    if (!result.ok || !result.data.success) {
                        setStatus(errorMessage(view, result), true);
                        return;
                    }
                    onSuccess(result.data);
                })
                .catch(function () {
                    if (submitButton) {
                        submitButton.disabled = false;
                    }
                    setStatus(scpAuthText.networkError, true);
                });
        });
    }

    bindForm(
        'login',
        'login',
        function (form) {
            return {
                tc_no: form.tc_no.value.trim(),
                password: form.password.value,
                remember: form.remember ? form.remember.checked : false
            };
        },
        function (data) {
            window.location.href = data.redirect_url || '/';
        }
    );

    bindForm(
        'forgot-password',
        'forgot-password',
        function (form) {
            return { tc_no: form.tc_no.value.trim() };
        },
        function () {
            setStatus(scpAuthText.resetLinkSent);
        }
    );

    bindForm(
        'first-password',
        'first-password',
        function (form) {
            return { tc_no: form.tc_no.value.trim() };
        },
        function () {
            setStatus(scpAuthText.resetLinkSent);
        }
    );

    bindForm(
        'set-password',
        'set-password',
        function (form) {
            if (form.password.value !== form.password_confirm.value) {
                setStatus(scpAuthText.passwordMismatch, true);
                return null;
            }
            return { token: scpAuth.token, password: form.password.value };
        },
        function () {
            setStatus(scpAuthText.passwordSet);
            window.setTimeout(function () {
                window.location.href = '/';
            }, 1500);
        }
    );

    if (scpAuth.token) {
        showView('set-password');
    }
})();
