/**
 * "Mağaza Vitrini" management for /admin - HQ-only (scp_manage_shop_showcase).
 * See plugin/seviye-commerce/src/Http/ShopShowcaseRestController.php - GET
 * returns the whole showcase (heading/subheading/image), PUT REPLACES it
 * whole (no per-field endpoint, so every PUT below resends all three
 * fields together, tracking `currentImageAttachmentId` locally between
 * calls). Image upload reuses branding-panel.js's exact /wp/v2/media flow
 * (scpUploadMedia()).
 *
 * Expects two globals localized from PHP (see inc/assets.php):
 *   scpPanel     { restUrl, wpRestRoot, nonce }
 *   scpPanelText { ...translated UI strings }
 */
(function () {
    'use strict';

    var root = document.getElementById('scp-shop-showcase-panel');

    if (!root || typeof scpPanel === 'undefined') {
        return;
    }

    var scpPanelTextData = typeof scpPanelText !== 'undefined' ? scpPanelText : {};

    var statusEl = root.querySelector('[data-scp-shop-showcase-status]');
    var form = root.querySelector('[data-scp-shop-showcase-form]');
    var preview = root.querySelector('[data-scp-shop-showcase-preview]');
    var imageInput = root.querySelector('[data-scp-shop-showcase-image-input]');
    var removeImageButton = root.querySelector('[data-scp-remove-shop-showcase-image]');

    var apiFetch = scpApiFetch;
    var currentImageAttachmentId = null;

    function setStatus(message, isError) {
        statusEl.textContent = message || '';
        statusEl.classList.toggle('scp-status--error', Boolean(isError));
    }

    function applyPreview(imageUrl) {
        if (imageUrl) {
            preview.src = imageUrl;
            preview.hidden = false;
        } else {
            preview.hidden = true;
        }
    }

    function applyShowcase(showcase) {
        form.heading.value = showcase.heading || '';
        form.subheading.value = showcase.subheading || '';
        currentImageAttachmentId = showcase.image_attachment_id || null;
        applyPreview(showcase.image_url);
    }

    function saveShowcase(imageAttachmentId) {
        return apiFetch('commerce/shop-showcase', {
            method: 'PUT',
            body: JSON.stringify({
                heading: form.heading.value,
                subheading: form.subheading.value,
                image_attachment_id: imageAttachmentId
            })
        });
    }

    apiFetch('commerce/shop-showcase').then(function (result) {
        if (result.ok) {
            applyShowcase(result.data);
        }
    });

    imageInput.addEventListener('change', function () {
        var file = imageInput.files[0];

        if (!file) {
            return;
        }

        setStatus(scpPanelTextData.uploadingImage);

        scpUploadMedia(file).then(function (uploadResult) {
            if (!uploadResult.ok) {
                setStatus(scpPanelTextData.imageUploadError, true);
                return;
            }

            saveShowcase(uploadResult.data.id).then(function (result) {
                if (!result.ok) {
                    setStatus((result.data && result.data.message) || scpPanelTextData.saveError, true);
                    return;
                }

                setStatus(scpPanelTextData.shopShowcaseSaved);
                applyShowcase(result.data);
            });
        });
    });

    removeImageButton.addEventListener('click', function () {
        saveShowcase(0).then(function (result) {
            if (!result.ok) {
                setStatus((result.data && result.data.message) || scpPanelTextData.saveError, true);
                return;
            }

            setStatus(scpPanelTextData.shopShowcaseSaved);
            applyShowcase(result.data);
            imageInput.value = '';
        });
    });

    form.addEventListener('submit', function (event) {
        event.preventDefault();

        saveShowcase(currentImageAttachmentId || 0).then(function (result) {
            if (!result.ok) {
                setStatus((result.data && result.data.message) || scpPanelTextData.saveError, true);
                return;
            }

            setStatus(scpPanelTextData.shopShowcaseSaved);
            applyShowcase(result.data);
        });
    });
})();
