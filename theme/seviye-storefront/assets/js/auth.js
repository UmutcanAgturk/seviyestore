/**
 * Login screen behaviour: view switching between login / 2fa /
 * forgot-password / require-password-change / set-password, and talking to
 * Seviye Security's seviye/v1/auth/* REST endpoints. No framework/build
 * step - this is a single, small, self-contained screen.
 *
 * The 2fa view only ever appears as a redirect from a successful `login`
 * call whose response carries `requires_2fa: true` - a password match
 * alone never sets the auth cookie for an account with 2FA enabled (see
 * Seviye\Security\Http\AuthRestController::login()). Its pending_token
 * hidden field is populated from that response, not typed by the user.
 *
 * The require-password-change view is the same kind of server-driven
 * redirect, not something a user navigates to directly: both `login` and
 * `2fa`'s success responses carry `must_change_password` (see
 * AuthRestController::finishLogin()) - true means the account holder is
 * currently on a password someone ELSE chose for them (see
 * MustChangePasswordGatewayInterface) and must replace it, right here,
 * before finishSession() below ever follows `redirect_url`. That same
 * response also carries `password_change_token` in that case - the view
 * posts it to the SAME `set-password` endpoint the token-based
 * "Şifremi Unuttum" flow uses (see pendingPasswordChangeToken below), not
 * a separate one. There is no self-serve "İlk Şifre Oluştur" flow anymore -
 * every account is created with a real password already (see
 * UserListPage/StudentsRestController), this gate is what used to be that
 * flow's job.
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
        if (view === '2fa' && result.data && result.data.reason === 'invalid_token') {
            return scpAuthText.twoFactorSessionExpired;
        }
        if (view === '2fa') {
            return scpAuthText.invalidCode;
        }
        if (view === 'set-password' && result.data && result.data.reason === 'weak_password') {
            return scpAuthText.weakPassword;
        }
        if (view === 'set-password') {
            return scpAuthText.invalidToken;
        }
        if (view === 'require-password-change' && result.data && result.data.reason === 'weak_password') {
            return scpAuthText.weakPassword;
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

    // Populated by finishSession() below when must_change_password is true
    // - the require-password-change form has no T.C. Kimlik No/token field
    // of its own to read this from (see AuthRestController::finishLogin()'s
    // own docblock on why this reuses set-password's token mechanism
    // instead of a session-gated endpoint).
    var pendingPasswordChangeToken = null;

    /**
     * Shared by `login` and `2fa`'s success handlers: a session now exists
     * either way (the auth cookie was set server-side in both cases - see
     * AuthRestController::finishLogin()), so both funnel through the same
     * must_change_password check before ever following redirect_url.
     */
    function finishSession(data) {
        if (data.must_change_password) {
            pendingPasswordChangeToken = data.password_change_token;
            showView('require-password-change');
            return;
        }

        window.location.href = data.redirect_url || '/';
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
            if (data.requires_2fa) {
                var pendingField = views['2fa'] && views['2fa'].pending_token;

                if (pendingField) {
                    pendingField.value = data.pending_token;
                }

                showView('2fa');
                return;
            }

            finishSession(data);
        }
    );

    bindForm(
        '2fa',
        'login/2fa',
        function (form) {
            return {
                pending_token: form.pending_token.value,
                code: form.code.value.trim()
            };
        },
        finishSession
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

    /**
     * Same success behaviour as the token-based `set-password` view below
     * (message, then redirect to `/`) - the account IS already logged in
     * here (the auth cookie was set back in finishSession()'s login/2fa
     * call), so `/` immediately bounces to the right landing page via
     * inc/access-gate.php's role-zone enforcement rather than showing the
     * login screen again.
     */
    bindForm(
        'require-password-change',
        'set-password',
        function (form) {
            if (form.password.value !== form.password_confirm.value) {
                setStatus(scpAuthText.passwordMismatch, true);
                return null;
            }
            return { token: pendingPasswordChangeToken, password: form.password.value };
        },
        function () {
            setStatus(scpAuthText.passwordChanged);
            window.setTimeout(function () {
                window.location.href = '/';
            }, 1500);
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
