/**
 * Platform logo upload (Genel Merkez only, /admin "Görünüm" section) - see
 * plugin/seviye-core/src/Http/BrandingRestController.php. Uploads through
 * WordPress' own /wp/v2/media endpoint (scpUploadMedia(), see
 * assets/js/scp-api-fetch.js), then stores the resulting attachment id as
 * the platform's logo.
 *
 * Expects two globals localized from PHP (see inc/assets.php):
 *   scpPanel     { restUrl, wpRestRoot, nonce }
 *   scpPanelText { ...translated UI strings }
 */
(function () {
    'use strict';

    var root = document.getElementById('scp-branding-panel');

    if (!root || typeof scpPanel === 'undefined') {
        return;
    }

    var statusEl = root.querySelector('[data-scp-branding-status]');
    var preview = root.querySelector('[data-scp-branding-preview]');
    var input = root.querySelector('[data-scp-branding-input]');
    var removeButton = root.querySelector('[data-scp-remove-branding]');

    var apiFetch = scpApiFetch;

    function setStatus(message, isError) {
        statusEl.textContent = message || '';
        statusEl.classList.toggle('scp-status--error', Boolean(isError));
    }

    function applyLogo(logoUrl) {
        if (logoUrl) {
            preview.src = logoUrl;
            preview.hidden = false;
        } else {
            preview.hidden = true;
        }
    }

    apiFetch('core/branding').then(function (result) {
        if (result.ok) {
            applyLogo(result.data.logo_url);
        }
    });

    input.addEventListener('change', function () {
        var file = input.files[0];

        if (!file) {
            return;
        }

        setStatus(scpPanelText.uploadingImage);

        scpUploadMedia(file).then(function (uploadResult) {
            if (!uploadResult.ok) {
                setStatus(scpPanelText.imageUploadError, true);
                return;
            }

            apiFetch('core/branding', {
                method: 'PUT',
                body: JSON.stringify({ logo_attachment_id: uploadResult.data.id })
            }).then(function (result) {
                if (!result.ok) {
                    setStatus((result.data && result.data.message) || scpPanelText.saveError, true);
                    return;
                }

                setStatus(scpPanelText.brandingSaved);
                applyLogo(result.data.logo_url);
            });
        });
    });

    removeButton.addEventListener('click', function () {
        apiFetch('core/branding', {
            method: 'PUT',
            body: JSON.stringify({ logo_attachment_id: 0 })
        }).then(function (result) {
            if (!result.ok) {
                setStatus((result.data && result.data.message) || scpPanelText.saveError, true);
                return;
            }

            setStatus(scpPanelText.brandingRemoved);
            applyLogo(null);
            input.value = '';
        });
    });
})();
