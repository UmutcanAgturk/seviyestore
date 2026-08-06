<?php

declare(strict_types=1);

namespace Seviye\Commerce\Support;

use WC_Tax;

/**
 * "Ürün ürün vergilendirme" - a thin wrapper around WooCommerce's OWN tax
 * engine (WC_Tax), not a Seviye-owned tax domain/table. Research before
 * building this confirmed WooCommerce's tax calculation already runs
 * correctly on top of Seviye's custom pricing (WooCommerceCartHooks only
 * overrides the unit price fed into WC_Cart::calculate_totals(), which
 * still applies tax afterward per the product's own tax_class) - the gap
 * was purely that nothing in this platform ever exposed a per-product tax
 * class picker, so every product silently used the store's single
 * "Standart" WooCommerce tax setting. See docs/ARCHITECTURE.md.
 *
 * A "tax rate" here is presented as ONE named class with ONE flat
 * percentage (`tax_rate_country`/`tax_rate_state` left empty = applies to
 * every location, correct for a single-country/TR store) - WooCommerce's
 * own tax class can in principle carry several country/state-specific
 * rows, but that generality isn't needed here and would only complicate
 * the "isim + yüzde" UI this platform's admins actually want.
 *
 * Only reads/writes WooCommerce's OWN storage, deliberately without a new
 * scp_* table - same "Kural" as Products/Orders/Coupons (see
 * docs/ARCHITECTURE.md): a tax class is just a name in the
 * `woocommerce_tax_classes` option (WC_Tax::get_tax_classes()'s own
 * documented format - a newline-separated list of display names, the
 * slug is always sanitize_title() of the name), and its rate is a row in
 * WooCommerce's own tax rates table, read/written through WC_Tax's own
 * `_insert_tax_rate()`/`_update_tax_rate()`/`_delete_tax_rate()` - the
 * SAME methods WooCommerce's own core `wc/v3/taxes` REST API controller
 * calls internally (the leading underscore is a WC naming quirk, not real
 * PHP visibility). This wasn't verified against a live WooCommerce
 * install (no local WC source/internet access in this environment, same
 * constraint noted throughout docs/ARCHITECTURE.md) - {@see isSupported()}
 * guards every write path so an unexpected WC version fails with a clear
 * Turkish error instead of a fatal.
 *
 * "Standart" (WooCommerce's always-present default class, whose real
 * `tax_rate_class` value is the empty string) is exposed under the public
 * pseudo-slug `standard` instead of `''` - an empty string can never
 * appear as a REST route's `(?P<slug>...)` path segment, so the real WC
 * value is translated at the edges only ({@see toWooCommerceClass()},
 * {@see toPublicSlug()}); nothing else in this class ever sees `standard`
 * as a WC tax_rate_class value.
 */
final class TaxRateGateway
{
    private const STANDARD_PUBLIC_SLUG = 'standard';
    private const STANDARD_WC_CLASS = '';
    private const CLASSES_OPTION = 'woocommerce_tax_classes';

    /**
     * @return list<array{slug: string, name: string, percent: float, is_standard: bool, in_use: bool}>
     */
    public function list(): array
    {
        $classes = [[self::STANDARD_PUBLIC_SLUG, __('Standart', 'seviye-commerce')]];

        foreach ($this->customClassNames() as $name) {
            $classes[] = [sanitize_title($name), $name];
        }

        return array_map(
            fn (array $class): array => $this->present($class[0], $class[1]),
            $classes
        );
    }

    /**
     * @return array{slug: string, name: string, percent: float, is_standard: bool, in_use: bool}|null
     *     null when a class with this name already exists (slug collision).
     */
    public function create(string $name, float $percent): ?array
    {
        $name = trim($name);
        $slug = sanitize_title($name);

        if ($slug === self::STANDARD_PUBLIC_SLUG || $this->customClassNameFor($slug) !== null) {
            return null;
        }

        $names = $this->customClassNames();
        $names[] = $name;
        update_option(self::CLASSES_OPTION, implode("\n", $names));

        $this->setRate($this->toWooCommerceClass($slug), $name, $percent);

        return $this->present($slug, $name);
    }

    /**
     * @return array{slug: string, name: string, percent: float, is_standard: bool, in_use: bool}|null
     *     null when no class with this slug exists.
     */
    public function update(string $slug, float $percent): ?array
    {
        $name = $slug === self::STANDARD_PUBLIC_SLUG
            ? __('Standart', 'seviye-commerce')
            : $this->customClassNameFor($slug);

        if ($name === null) {
            return null;
        }

        $this->setRate($this->toWooCommerceClass($slug), $name, $percent);

        return $this->present($slug, $name);
    }

    /**
     * The "Standart" class can never be deleted (WooCommerce always has
     * it), and a class still assigned to at least one product is refused
     * too - deleting it out from under an in-use product would silently
     * drop that product back to whatever the store's default happens to
     * be, exactly the kind of "wrong tax, nobody notices" failure this
     * feature exists to prevent.
     */
    public function delete(string $slug): bool
    {
        if ($slug === self::STANDARD_PUBLIC_SLUG || $this->customClassNameFor($slug) === null) {
            return false;
        }

        if ($this->isInUse($slug)) {
            return false;
        }

        $wcClass = $this->toWooCommerceClass($slug);

        foreach ($this->ratesFor($wcClass) as $rateId => $rate) {
            WC_Tax::_delete_tax_rate((int) $rateId);
        }

        $remaining = array_values(array_filter(
            $this->customClassNames(),
            static fn (string $name): bool => sanitize_title($name) !== $slug
        ));
        update_option(self::CLASSES_OPTION, implode("\n", $remaining));

        return true;
    }

    /**
     * Guards every write path (create/update/delete) - see this class'
     * own docblock on why the underscore-prefixed WC_Tax methods aren't
     * 100% certain across every WooCommerce version.
     */
    public function isSupported(): bool
    {
        return method_exists(WC_Tax::class, '_insert_tax_rate')
            && method_exists(WC_Tax::class, '_update_tax_rate')
            && method_exists(WC_Tax::class, '_delete_tax_rate');
    }

    public function toWooCommerceClass(string $publicSlug): string
    {
        return $publicSlug === self::STANDARD_PUBLIC_SLUG ? self::STANDARD_WC_CLASS : $publicSlug;
    }

    public function toPublicSlug(string $wcClass): string
    {
        return $wcClass === self::STANDARD_WC_CLASS ? self::STANDARD_PUBLIC_SLUG : $wcClass;
    }

    /**
     * The flat percentage currently configured for a product's raw WC tax
     * class (0.0 when the class has no rate row at all yet) - used by
     * ProductsRestController::serialize() so the theme can show "KDV: %20"
     * next to a product without a second REST round trip.
     */
    public function percentForWooCommerceClass(string $wcClass): float
    {
        $rates = $this->ratesFor($wcClass);
        $first = reset($rates);

        return $first !== false ? (float) $first->rate : 0.0;
    }

    private function isInUse(string $publicSlug): bool
    {
        $wcClass = $this->toWooCommerceClass($publicSlug);

        $posts = get_posts([
            'post_type' => ['product', 'product_variation'],
            'post_status' => 'any',
            'meta_key' => '_tax_class',
            'meta_value' => $wcClass,
            'posts_per_page' => 1,
            'fields' => 'ids',
        ]);

        return $posts !== [];
    }

    private function setRate(string $wcClass, string $name, float $percent): void
    {
        $payload = [
            'tax_rate_country' => '',
            'tax_rate_state' => '',
            'tax_rate' => number_format($percent, 4, '.', ''),
            'tax_rate_name' => $name,
            'tax_rate_priority' => 1,
            'tax_rate_compound' => 0,
            'tax_rate_shipping' => 1,
            'tax_rate_order' => 0,
            'tax_rate_class' => $wcClass,
        ];

        $existing = $this->ratesFor($wcClass);
        $existingId = array_key_first($existing);

        if ($existingId !== null) {
            WC_Tax::_update_tax_rate((int) $existingId, $payload);

            return;
        }

        WC_Tax::_insert_tax_rate($payload);
    }

    /**
     * @return array<int, object{rate: string}>
     */
    private function ratesFor(string $wcClass): array
    {
        return WC_Tax::get_rates_for_tax_class($wcClass);
    }

    private function present(string $slug, string $name): array
    {
        $wcClass = $this->toWooCommerceClass($slug);

        return [
            'slug' => $slug,
            'name' => $name,
            'percent' => $this->percentForWooCommerceClass($wcClass),
            'is_standard' => $slug === self::STANDARD_PUBLIC_SLUG,
            'in_use' => $slug === self::STANDARD_PUBLIC_SLUG ? true : $this->isInUse($slug),
        ];
    }

    /**
     * @return list<string> trimmed, non-empty custom class NAMES (not
     *     slugs) - WC_Tax::get_tax_classes()'s own documented format.
     */
    private function customClassNames(): array
    {
        return array_values(array_filter(array_map(
            'trim',
            explode("\n", (string) get_option(self::CLASSES_OPTION, ''))
        ), static fn (string $name): bool => $name !== ''));
    }

    private function customClassNameFor(string $slug): ?string
    {
        foreach ($this->customClassNames() as $name) {
            if (sanitize_title($name) === $slug) {
                return $name;
            }
        }

        return null;
    }
}
