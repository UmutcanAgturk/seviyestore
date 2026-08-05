<?php

/**
 * Shared "sınıf seviyesi" (grade level) vocabulary, used by BOTH the
 * student form's Sınıf dropdown (templates/students-admin.php) and the
 * product form's "görünür olacağı sınıflar" visibility filter
 * (templates/product-edit.php) - "Yeni öğrenci eklemede Sınıfı el ile
 * yazmak yerine bir menü olup anasınıfından 12. sınıfa ya da mezun da
 * yazmalı... yeni ürün eklerken de ürünlerin hangi sınıf ya da
 * sınıflardaki öğrencilere görüneceğini belirten bir filtre koy".
 *
 * The stored value is the label itself (e.g. "5. Sınıf"), not a numeric
 * code - so a product's grade_levels list and a student's class_name can
 * be compared with a plain in_array() string match, with no code/label
 * translation layer anywhere (REST payloads, CSV exports, e-mail
 * templates all already print class_name as raw text).
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

/**
 * @return list<string>
 */
function scp_grade_level_options(): array
{
    $options = [__('Anasınıfı', 'seviye-storefront')];

    for ($grade = 1; $grade <= 12; $grade++) {
        $options[] = sprintf(
            /* translators: %d: grade number, 1-12 */
            __('%d. Sınıf', 'seviye-storefront'),
            $grade
        );
    }

    $options[] = __('Mezun', 'seviye-storefront');

    return $options;
}
