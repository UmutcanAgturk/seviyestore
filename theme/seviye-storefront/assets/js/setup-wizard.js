/**
 * Drives the "Seviye Kurulum" admin page's install steps one at a time -
 * each step is its OWN AJAX round-trip (see inc/plugin-installer.php's
 * docblock for why: a single blocking "install everything" request risks a
 * PHP execution timeout the moment WooCommerce has to be downloaded from
 * wordpress.org). A step that fails stops the run entirely, since every
 * later step assumes the earlier ones already succeeded (Security needs
 * Core active, Commerce needs Branches/Students/Pricing active, ...).
 *
 * Expects two globals localized from PHP (see inc/plugin-installer.php):
 *   scpSetup     { ajaxUrl, nonce, steps }
 *   scpSetupText { ...translated UI strings }
 */
(function () {
    'use strict';

    if (typeof scpSetup === 'undefined') {
        return;
    }

    var startButton = document.getElementById('scp-setup-start');
    var stepsList = document.getElementById('scp-setup-steps');
    var credentialsBox = document.getElementById('scp-setup-credentials');

    if (!startButton || !stepsList) {
        return;
    }

    function statusEl(stepId) {
        var item = stepsList.querySelector('[data-step="' + stepId + '"]');
        return item ? item.querySelector('[data-status]') : null;
    }

    function setStepStatus(stepId, status, text) {
        var el = statusEl(stepId);

        if (!el) {
            return;
        }

        el.setAttribute('data-status', status);
        el.textContent = text;
    }

    function runStep(stepId) {
        setStepStatus(stepId, 'running', scpSetupText.running);

        var body = new URLSearchParams();
        body.set('action', 'scp_setup_step');
        body.set('nonce', scpSetup.nonce);
        body.set('step', stepId);

        return fetch(scpSetup.ajaxUrl, {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: body.toString()
        })
            .then(function (response) {
                return response.json();
            })
            .then(function (payload) {
                if (!payload.success) {
                    var message = (payload.data && payload.data.message) || scpSetupText.genericError;
                    setStepStatus(stepId, 'failed', scpSetupText.failed + ': ' + message);
                    return false;
                }

                setStepStatus(stepId, 'done', scpSetupText.done);

                if (payload.data && payload.data.credentials) {
                    showCredentials(payload.data.credentials);
                }

                return true;
            })
            .catch(function () {
                setStepStatus(stepId, 'failed', scpSetupText.failed + ': ' + scpSetupText.genericError);
                return false;
            });
    }

    function showCredentials(credentials) {
        if (!credentialsBox) {
            return;
        }

        var tcNoEl = credentialsBox.querySelector('[data-scp-credential="tc_no"]');
        var passwordEl = credentialsBox.querySelector('[data-scp-credential="password"]');

        if (tcNoEl) {
            tcNoEl.textContent = credentials.tc_no;
        }

        if (passwordEl) {
            passwordEl.textContent = credentials.password;
        }

        credentialsBox.hidden = false;
    }

    function runAllSteps(steps) {
        var index = 0;

        function next() {
            if (index >= steps.length) {
                startButton.textContent = scpSetupText.allDone;
                return;
            }

            var stepId = steps[index];
            index += 1;

            runStep(stepId).then(function (ok) {
                if (ok) {
                    next();
                }
            });
        }

        next();
    }

    startButton.addEventListener('click', function () {
        startButton.disabled = true;
        runAllSteps(scpSetup.steps);
    });
})();
