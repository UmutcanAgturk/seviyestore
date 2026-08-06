<?php

declare(strict_types=1);

namespace Seviye\Commerce\Http;

use Seviye\Branches\Contracts\BranchLookupInterface;
use Seviye\Branches\Contracts\BranchMembershipInterface;
use Seviye\Commerce\Domain\ProductBranchStatus;
use Seviye\Commerce\Rbac\ProductCapability;
use Seviye\Commerce\Repository\ProductBranchVisibilityRepositoryInterface;
use Seviye\Commerce\Support\ProductGradeLevels;
use Seviye\Commerce\Support\ProductOwnership;
use Seviye\Commerce\Support\TaxRateGateway;
use Seviye\Core\Http\AbstractRestController;
use Seviye\Core\Http\RestApiRegistrar;
use WC_Post_Types;
use WC_Product;
use WC_Product_Attribute;
use WC_Product_Simple;
use WC_Product_Variable;
use WC_Product_Variation;
use WP_REST_Request;
use WP_REST_Response;
use WP_Term;

/**
 * seviye/v1/commerce/products/*. Products stay entirely WooCommerce's own
 * (wp_posts/WC_Product) - this controller is a thin wrapper so Şube
 * Müdürü/Genel Merkez, who hold none of WordPress' native
 * edit_products/publish_products capabilities, can manage a SHARED catalog
 * without wp-admin. Full edit/delete is Genel Merkez/Bölge Müdürü ALWAYS,
 * plus a Şube Müdürü for a product they THEMSELVES created (see
 * ProductOwnership) - never another branch's product, and never a
 * Genel Merkez-created one. A Şube Müdürü may still toggle any product's
 * active/passive status for their OWN branch only (see setOwnBranchStatus()),
 * regardless of who created it.
 *
 * "Ürün varyantları (beden/renk)" - a product may optionally be created
 * with `sizes`/`colors` (comma-separated), producing a real WooCommerce
 * WC_Product_Variable with global `pa_beden`/`pa_renk` attributes and one
 * WC_Product_Variation per combination, each with its own stock quantity -
 * NOT a Seviye-owned variant schema. The shop-facing variation picker is
 * WooCommerce's own default single-product template, unmodified; this
 * controller's job ends at producing data WooCommerce already knows how to
 * render. Existing variation stock/price is edited afterward through
 * variations()/updateVariations() (HQ only, same gating as
 * canManageProductFully()) - converting an EXISTING simple product to
 * variable, or vice versa, is intentionally not supported here (a much
 * riskier operation against a product that may already have real orders).
 */
final class ProductsRestController extends AbstractRestController
{
    public function __construct(
        private readonly ProductBranchVisibilityRepositoryInterface $visibility,
        private readonly BranchMembershipInterface $branchMemberships,
        private readonly BranchLookupInterface $branches,
        private readonly ProductOwnership $ownership,
        private readonly ProductGradeLevels $gradeLevels,
        private readonly TaxRateGateway $taxRates
    ) {
    }

    public function registerRoutes(): void
    {
        register_rest_route(RestApiRegistrar::NAMESPACE, '/commerce/products', [
            [
                'methods' => 'GET',
                'callback' => [$this, 'index'],
                'permission_callback' => [$this, 'canViewProducts'],
            ],
            [
                'methods' => 'POST',
                'callback' => [$this, 'store'],
                'permission_callback' => $this->requireCapability(ProductCapability::MANAGE_PRODUCTS->value),
                'args' => $this->writableArgs(),
            ],
        ]);

        register_rest_route(RestApiRegistrar::NAMESPACE, '/commerce/products/(?P<id>\d+)', [
            [
                'methods' => 'GET',
                'callback' => [$this, 'show'],
                'permission_callback' => [$this, 'canViewProducts'],
            ],
            [
                'methods' => 'PUT',
                'callback' => [$this, 'update'],
                'permission_callback' => [$this, 'canManageProductFully'],
                'args' => $this->writableArgs(),
            ],
            [
                'methods' => 'DELETE',
                'callback' => [$this, 'destroy'],
                'permission_callback' => [$this, 'canManageProductFully'],
            ],
        ]);

        register_rest_route(RestApiRegistrar::NAMESPACE, '/commerce/products/(?P<id>\d+)/branches', [
            'methods' => 'GET',
            'callback' => [$this, 'branchStatuses'],
            'permission_callback' => $this->requireCapability(ProductCapability::MANAGE_PRODUCTS->value),
        ]);

        register_rest_route(
            RestApiRegistrar::NAMESPACE,
            '/commerce/products/(?P<id>\d+)/branches/(?P<branch_id>\d+)',
            [
                'methods' => 'PUT',
                'callback' => [$this, 'setBranchStatus'],
                'permission_callback' => [$this, 'canToggleBranchStatus'],
                'args' => [
                    'status' => ['required' => true, 'type' => 'string'],
                ],
            ]
        );

        // Convenience for a Şube Müdürü's own toggle button: they never
        // need to know their own numeric branch id (server-side scoping
        // already never exposes it to them elsewhere either) - this
        // resolves it internally and 403s for HQ (who has no "own" branch
        // and must use the explicit branch_id route above instead).
        register_rest_route(RestApiRegistrar::NAMESPACE, '/commerce/products/(?P<id>\d+)/branches/own', [
            'methods' => 'PUT',
            'callback' => [$this, 'setOwnBranchStatus'],
            'permission_callback' => [$this, 'canToggleOwnBranchStatus'],
            'args' => [
                'status' => ['required' => true, 'type' => 'string'],
            ],
        ]);

        register_rest_route(RestApiRegistrar::NAMESPACE, '/commerce/products/(?P<id>\d+)/variations', [
            [
                'methods' => 'GET',
                'callback' => [$this, 'variations'],
                'permission_callback' => [$this, 'canViewProducts'],
            ],
            [
                'methods' => 'PUT',
                'callback' => [$this, 'updateVariations'],
                'permission_callback' => [$this, 'canManageProductFully'],
                'args' => [
                    'variations' => ['required' => true, 'type' => 'array'],
                ],
            ],
        ]);
    }

    public function canViewProducts(): bool
    {
        return current_user_can(ProductCapability::MANAGE_PRODUCTS->value)
            || current_user_can(ProductCapability::VIEW_PRODUCTS->value);
    }

    public function index(): WP_REST_Response
    {
        $products = wc_get_products(['limit' => -1, 'status' => ['publish', 'draft']]);

        return new WP_REST_Response(array_map($this->serialize(...), $products));
    }

    public function show(WP_REST_Request $request): WP_REST_Response
    {
        $product = wc_get_product((int) $request->get_param('id'));

        if (!$product instanceof WC_Product) {
            return new WP_REST_Response(['message' => __('Ürün bulunamadı.', 'seviye-commerce')], 404);
        }

        return new WP_REST_Response($this->serialize($product));
    }

    public function store(WP_REST_Request $request): WP_REST_Response
    {
        $name = trim((string) $request->get_param('name'));
        $price = (float) $request->get_param('price');
        $sizes = $this->parseCsvList($request->get_param('sizes'));
        $colors = $this->parseCsvList($request->get_param('colors'));
        $hasVariants = $sizes !== [] || $colors !== [];

        if ($name === '' || $price < 0) {
            return new WP_REST_Response(
                ['message' => __('Ürün adı ve geçerli bir fiyat gerekli.', 'seviye-commerce')],
                422
            );
        }

        $product = $hasVariants ? new WC_Product_Variable() : new WC_Product_Simple();
        $this->applyWritableFields($product, $request);
        $product->set_status('publish');
        $productId = $product->save();
        $this->applyCategory($productId, $request);
        $this->ownership->setOwnerBranchId($productId, $this->currentUserBranchId());
        $this->applyGradeLevels($productId, $request);

        if ($hasVariants) {
            $termsByKey = $this->applyVariants(wc_get_product($productId), $sizes, $colors);
            $this->generateVariations(wc_get_product($productId), $termsByKey, $price);
        }

        return new WP_REST_Response($this->serialize(wc_get_product($productId)), 201);
    }

    public function update(WP_REST_Request $request): WP_REST_Response
    {
        $product = wc_get_product((int) $request->get_param('id'));

        if (!$product instanceof WC_Product) {
            return new WP_REST_Response(['message' => __('Ürün bulunamadı.', 'seviye-commerce')], 404);
        }

        $name = trim((string) $request->get_param('name'));
        $price = (float) $request->get_param('price');

        if ($name === '' || $price < 0) {
            return new WP_REST_Response(
                ['message' => __('Ürün adı ve geçerli bir fiyat gerekli.', 'seviye-commerce')],
                422
            );
        }

        $this->applyWritableFields($product, $request);
        $product->save();
        $this->applyCategory($product->get_id(), $request);
        $this->applyGradeLevels($product->get_id(), $request);

        return new WP_REST_Response($this->serialize(wc_get_product($product->get_id())));
    }

    public function destroy(WP_REST_Request $request): WP_REST_Response
    {
        $product = wc_get_product((int) $request->get_param('id'));

        if (!$product instanceof WC_Product) {
            return new WP_REST_Response(['message' => __('Ürün bulunamadı.', 'seviye-commerce')], 404);
        }

        $product->delete(true);

        return new WP_REST_Response(['success' => true]);
    }

    /**
     * Full per-branch map - reading which branches have hidden a product is
     * not sensitive, so unlike setBranchStatus() this is not restricted to
     * a Şube Müdürü's own branch.
     */
    public function branchStatuses(WP_REST_Request $request): WP_REST_Response
    {
        $productId = (int) $request->get_param('id');
        $statuses = $this->visibility->statusesForProduct($productId);

        $serialized = [];

        foreach ($statuses as $branchId => $status) {
            $serialized[] = ['branch_id' => $branchId, 'status' => $status->value];
        }

        return new WP_REST_Response($serialized);
    }

    public function setBranchStatus(WP_REST_Request $request): WP_REST_Response
    {
        $status = ProductBranchStatus::tryFrom((string) $request->get_param('status'));

        if ($status === null) {
            return new WP_REST_Response(['message' => __('Geçersiz durum.', 'seviye-commerce')], 422);
        }

        $productId = (int) $request->get_param('id');
        $branchId = (int) $request->get_param('branch_id');

        $this->visibility->setStatus($productId, $branchId, $status);

        return new WP_REST_Response(['product_id' => $productId, 'branch_id' => $branchId, 'status' => $status->value]);
    }

    public function setOwnBranchStatus(WP_REST_Request $request): WP_REST_Response
    {
        $status = ProductBranchStatus::tryFrom((string) $request->get_param('status'));

        if ($status === null) {
            return new WP_REST_Response(['message' => __('Geçersiz durum.', 'seviye-commerce')], 422);
        }

        $productId = (int) $request->get_param('id');
        $branchId = (int) $this->currentUserBranchId();

        $this->visibility->setStatus($productId, $branchId, $status);

        return new WP_REST_Response(['product_id' => $productId, 'branch_id' => $branchId, 'status' => $status->value]);
    }

    public function canToggleOwnBranchStatus(): bool
    {
        return current_user_can(ProductCapability::MANAGE_PRODUCTS->value) && $this->currentUserBranchId() !== null;
    }

    public function canManageProductFully(WP_REST_Request $request): bool
    {
        if (!current_user_can(ProductCapability::MANAGE_PRODUCTS->value)) {
            return false;
        }

        $ownBranchId = $this->currentUserBranchId();

        if ($ownBranchId === null) {
            // Genel Merkez/Bölge Müdürü - full edit/delete/variations on
            // every product, regardless of who created it.
            return true;
        }

        // A Şube Müdürü may fully edit/delete a product only when THEY
        // created it - never a Genel Merkez product, never another
        // branch's. Their power over anything else stays limited to
        // toggling their own branch's active/passive status (see
        // canToggleBranchStatus()/setOwnBranchStatus()).
        $productId = (int) $request->get_param('id');

        return $this->ownership->ownerBranchId($productId) === $ownBranchId;
    }

    public function canToggleBranchStatus(WP_REST_Request $request): bool
    {
        if (!current_user_can(ProductCapability::MANAGE_PRODUCTS->value)) {
            return false;
        }

        $branchId = (int) $request->get_param('branch_id');

        if (!$this->branches->exists($branchId)) {
            return false;
        }

        $ownBranchId = $this->currentUserBranchId();

        // HQ (no membership row) may toggle any branch; a Şube Müdürü may
        // only toggle their OWN branch - never another branch's visibility.
        return $ownBranchId === null || $ownBranchId === $branchId;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function variations(WP_REST_Request $request): WP_REST_Response
    {
        $product = wc_get_product((int) $request->get_param('id'));

        if (!$product instanceof WC_Product_Variable) {
            return new WP_REST_Response([]);
        }

        $variations = array_map(
            fn (int $variationId): array => $this->serializeVariation(wc_get_product($variationId)),
            $product->get_children()
        );

        return new WP_REST_Response(array_values(array_filter($variations)));
    }

    /**
     * Only ever writes to a variation that is actually this product's own
     * child (`in_array($variationId, $childIds, true)`) - the request
     * supplies variation ids, and nothing stops a caller from naming a
     * variation belonging to a DIFFERENT product otherwise.
     */
    public function updateVariations(WP_REST_Request $request): WP_REST_Response
    {
        $product = wc_get_product((int) $request->get_param('id'));

        if (!$product instanceof WC_Product_Variable) {
            return new WP_REST_Response(['message' => __('Bu ürün varyantlı değil.', 'seviye-commerce')], 422);
        }

        $childIds = $product->get_children();
        $rows = (array) $request->get_param('variations');

        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }

            $variationId = (int) ($row['id'] ?? 0);

            if (!in_array($variationId, $childIds, true)) {
                continue;
            }

            $variation = wc_get_product($variationId);

            if (!$variation instanceof WC_Product_Variation) {
                continue;
            }

            if (array_key_exists('stock_quantity', $row)) {
                $variation->set_manage_stock(true);
                $variation->set_stock_quantity((int) $row['stock_quantity']);
            }

            if (array_key_exists('price', $row) && $row['price'] !== '' && $row['price'] !== null) {
                $variation->set_regular_price((string) (float) $row['price']);
            }

            $variation->save();
        }

        WC_Product_Variable::sync($product->get_id());

        return $this->variations($request);
    }

    private function applyWritableFields(WC_Product $product, WP_REST_Request $request): void
    {
        $product->set_name(trim((string) $request->get_param('name')));
        $product->set_description((string) ($request->get_param('description') ?? ''));

        $imageId = (int) $request->get_param('image_id');

        if ($imageId > 0) {
            $product->set_image_id($imageId);
        }

        // "Ürün ürün vergilendirme" - unlike price/stock below, tax class
        // is a real WC_Product field on a VARIABLE product too (its
        // variations inherit it by default), so this runs before the
        // variable-product early return, not after. The theme sends one of
        // the PUBLIC slugs TaxRateGateway::list() returned ('standard' or a
        // custom slug); toWooCommerceClass() maps 'standard' back to
        // WooCommerce's real empty-string tax_rate_class. Omitted entirely
        // (a request from before this field existed, or a client that
        // never touches it) defaults to 'standard' rather than leaving the
        // product's existing tax_class untouched - applyWritableFields()
        // already fully overwrites every other writable field the same way.
        $taxClass = (string) ($request->get_param('tax_class') ?? 'standard');
        $product->set_tax_class($this->taxRates->toWooCommerceClass($taxClass));

        if ($product instanceof WC_Product_Variable) {
            // Variable products carry no price/stock of their own - each
            // generated variation (see generateVariations()) owns those
            // instead.
            return;
        }

        $product->set_regular_price((string) (float) $request->get_param('price'));

        $manageStock = (bool) $request->get_param('manage_stock');
        $product->set_manage_stock($manageStock);

        if ($manageStock) {
            $product->set_stock_quantity((int) $request->get_param('stock_quantity'));

            $lowStockAmount = $request->get_param('low_stock_amount');
            $product->set_low_stock_amount(
                $lowStockAmount !== null && $lowStockAmount !== '' ? (int) $lowStockAmount : ''
            );
        }
    }

    /**
     * @param list<string> $sizes
     * @param list<string> $colors
     * @return array<string, list<string>> attribute slug ('beden'/'renk') ->
     *     the term slugs assigned, for generateVariations()'s cartesian
     *     product
     */
    private function applyVariants(WC_Product_Variable $product, array $sizes, array $colors): array
    {
        $attributes = [];
        $termsByKey = [];

        if ($sizes !== []) {
            [$attribute, $termSlugs] = $this->buildVariationAttribute(
                $product->get_id(),
                'beden',
                __('Beden', 'seviye-commerce'),
                $sizes,
                0
            );
            $attributes[] = $attribute;
            $termsByKey['beden'] = $termSlugs;
        }

        if ($colors !== []) {
            [$attribute, $termSlugs] = $this->buildVariationAttribute(
                $product->get_id(),
                'renk',
                __('Renk', 'seviye-commerce'),
                $colors,
                1
            );
            $attributes[] = $attribute;
            $termsByKey['renk'] = $termSlugs;
        }

        $product->set_attributes($attributes);
        $product->save();

        return $termsByKey;
    }

    /**
     * @param list<string> $values
     * @return array{0: WC_Product_Attribute, 1: list<string>}
     */
    private function buildVariationAttribute(
        int $productId,
        string $slug,
        string $label,
        array $values,
        int $position
    ): array {
        $taxonomy = $this->ensureAttributeTaxonomy($slug, $label);
        $termIds = [];
        $termSlugs = [];

        foreach ($values as $value) {
            $term = $this->ensureTerm($taxonomy, $value);
            $termIds[] = $term->term_id;
            $termSlugs[] = $term->slug;
        }

        wp_set_object_terms($productId, $termSlugs, $taxonomy);

        $attribute = new WC_Product_Attribute();
        $attribute->set_id(wc_attribute_taxonomy_id_by_name($slug));
        $attribute->set_name($taxonomy);
        $attribute->set_options($termIds);
        $attribute->set_position($position);
        $attribute->set_visible(true);
        $attribute->set_variation(true);

        return [$attribute, $termSlugs];
    }

    /**
     * Creates the `pa_beden`/`pa_renk` global attribute taxonomy on first
     * use (mirrors applyCategory()'s "create the term if it doesn't exist
     * yet" convenience for product_cat). A taxonomy created via
     * wc_create_attribute() is not yet registered within THIS same
     * request - WooCommerce only re-registers attribute taxonomies on
     * `init` - so WC_Post_Types::register_taxonomies() is called
     * immediately after, the same fix WooCommerce's own admin Ajax
     * attribute-creation handler applies.
     */
    private function ensureAttributeTaxonomy(string $slug, string $label): string
    {
        $taxonomy = wc_attribute_taxonomy_name($slug);

        if (wc_attribute_taxonomy_id_by_name($slug) === 0) {
            wc_create_attribute([
                'name' => $label,
                'slug' => $slug,
                'type' => 'select',
                'order_by' => 'menu_order',
                'has_archives' => false,
            ]);

            delete_transient('wc_attribute_taxonomies');
            WC_Post_Types::register_taxonomies();
        }

        return $taxonomy;
    }

    private function ensureTerm(string $taxonomy, string $name): WP_Term
    {
        $existing = get_term_by('name', $name, $taxonomy);

        if ($existing instanceof WP_Term) {
            return $existing;
        }

        $created = wp_insert_term($name, $taxonomy);

        if (!is_wp_error($created)) {
            return get_term((int) $created['term_id'], $taxonomy);
        }

        // wp_insert_term() failing usually means a slug collision (a
        // different-cased/accented name slugifying the same way) rather
        // than the name genuinely being new - fall back to whatever term
        // already owns that slug.
        $bySlug = get_term_by('slug', sanitize_title($name), $taxonomy);

        if ($bySlug instanceof WP_Term) {
            return $bySlug;
        }

        return $this->ensureTerm($taxonomy, $name . '-' . wp_generate_password(4, false));
    }

    /**
     * @param array<string, list<string>> $termsByKey
     */
    private function generateVariations(WC_Product_Variable $product, array $termsByKey, float $price): void
    {
        foreach ($this->cartesianProduct($termsByKey) as $combination) {
            $variation = new WC_Product_Variation();
            $variation->set_parent_id($product->get_id());
            $variation->set_attributes($combination);
            $variation->set_regular_price((string) $price);
            $variation->set_manage_stock(true);
            $variation->set_stock_quantity(0);
            $variation->set_status('publish');
            $variation->save();
        }

        WC_Product_Variable::sync($product->get_id());
    }

    /**
     * @param array<string, list<string>> $termsByKey attribute slug ->
     *     term slugs
     * @return list<array<string, string>> each entry is a
     *     [taxonomy => term_slug] map ready for
     *     WC_Product_Variation::set_attributes()
     */
    private function cartesianProduct(array $termsByKey): array
    {
        $combinations = [[]];

        foreach ($termsByKey as $key => $slugs) {
            $taxonomy = wc_attribute_taxonomy_name($key);
            $next = [];

            foreach ($combinations as $combination) {
                foreach ($slugs as $slug) {
                    $next[] = $combination + [$taxonomy => $slug];
                }
            }

            $combinations = $next;
        }

        return $combinations;
    }

    /**
     * @return list<string>
     */
    private function parseCsvList(mixed $raw): array
    {
        if (!is_string($raw) || trim($raw) === '') {
            return [];
        }

        return array_values(array_filter(array_map('trim', explode(',', $raw))));
    }

    /**
     * Applied AFTER save(): a brand-new product has no post id yet while
     * applyWritableFields() runs (WC_Product_Simple::get_id() is still 0
     * before the first save()), and wp_set_object_terms() against post id 0
     * would silently no-op. Creates the `product_cat` term on first use -
     * lets a Şube Müdürü type a category name freely (e.g. "Kırtasiye")
     * without a separate "create category" step.
     */
    private function applyCategory(int $productId, WP_REST_Request $request): void
    {
        $category = trim((string) $request->get_param('category'));

        if ($category === '') {
            return;
        }

        $termId = $this->resolveCategoryTermId($category);

        if ($termId !== null) {
            wp_set_object_terms($productId, [$termId], 'product_cat');
        }
    }

    /**
     * Unlike applyCategory() above, an explicit EMPTY array is meaningful
     * here (it clears a product back to "visible to every grade level" -
     * see ProductGradeLevels) - only a genuinely ABSENT param (a partial
     * update that never mentioned grade_levels at all) leaves the existing
     * value untouched.
     */
    private function applyGradeLevels(int $productId, WP_REST_Request $request): void
    {
        $gradeLevels = $request->get_param('grade_levels');

        if ($gradeLevels === null) {
            return;
        }

        $this->gradeLevels->setGradeLevelsFor($productId, array_map('strval', (array) $gradeLevels));
    }

    private function resolveCategoryTermId(string $name): ?int
    {
        $existing = term_exists($name, 'product_cat');

        if (is_array($existing)) {
            return (int) $existing['term_id'];
        }

        $created = wp_insert_term($name, 'product_cat');

        return is_wp_error($created) ? null : (int) $created['term_id'];
    }

    private function currentUserBranchId(): ?int
    {
        return $this->branchMemberships->branchIdForUser(get_current_user_id());
    }

    /**
     * @return array<string, mixed>
     */
    private function serialize(WC_Product $product): array
    {
        $imageId = $product->get_image_id();
        $ownBranchId = $this->currentUserBranchId();
        $isVariable = $product instanceof WC_Product_Variable;
        $ownerBranchId = $this->ownership->ownerBranchId($product->get_id());
        $ownerBranch = $ownerBranchId !== null ? $this->branches->find($ownerBranchId) : null;

        return [
            'id' => $product->get_id(),
            'name' => $product->get_name(),
            'description' => $product->get_description(),
            'type' => $product->get_type(),
            'price' => $isVariable ? null : (float) $product->get_regular_price(),
            'price_range' => $isVariable ? $this->variationPriceRange($product) : null,
            'image_id' => $imageId ?: null,
            'image_url' => $imageId ? wp_get_attachment_image_url($imageId, 'thumbnail') : null,
            'manage_stock' => $isVariable ? null : $product->get_manage_stock(),
            'stock_quantity' => !$isVariable && $product->get_manage_stock() ? $product->get_stock_quantity() : null,
            'low_stock_amount' => !$isVariable && $product->get_manage_stock()
                ? $this->nullableLowStockAmount($product)
                : null,
            'category' => $this->firstCategoryName($product),
            // "Ürün ürün vergilendirme" - the PUBLIC slug (see
            // TaxRateGateway) this product's tax_class maps to, plus the
            // currently configured percent for that class so the theme can
            // show "KDV: %20" without a second request.
            'tax_class' => $this->taxRates->toPublicSlug($product->get_tax_class()),
            'tax_rate_percent' => $this->taxRates->percentForWooCommerceClass($product->get_tax_class()),
            'own_branch_active' => $ownBranchId !== null
                ? $this->visibility->isActiveForBranch($product->get_id(), $ownBranchId)
                : null,
            // "Genel Merkez" (null) or the creating branch - see
            // ProductOwnership. can_manage tells the theme's Ürünler paneli
            // whether THIS user may open the full edit/delete structure for
            // THIS specific product, without reimplementing the ownership
            // rule client-side.
            'owner_branch_id' => $ownerBranchId,
            'owner_branch_name' => $ownerBranch?->name,
            'can_manage' => current_user_can(ProductCapability::MANAGE_PRODUCTS->value)
                && ($ownBranchId === null || $ownerBranchId === $ownBranchId),
            // Empty list = visible to every grade level (see
            // ProductGradeLevels) - the theme's product form renders this
            // as "hepsi" (nothing checked) rather than a restriction.
            'grade_levels' => $this->gradeLevels->gradeLevelsFor($product->get_id()),
        ];
    }

    /**
     * WC_Product::get_low_stock_amount() returns '' (not null) when unset -
     * meaning "use the site-wide default threshold", not "zero".
     */
    private function nullableLowStockAmount(WC_Product $product): ?int
    {
        $amount = $product->get_low_stock_amount();

        return $amount === '' ? null : (int) $amount;
    }

    /**
     * @return array{min: float, max: float}|null
     */
    private function variationPriceRange(WC_Product_Variable $product): ?array
    {
        $prices = array_map('floatval', $product->get_variation_prices()['price'] ?? []);

        if ($prices === []) {
            return null;
        }

        return ['min' => min($prices), 'max' => max($prices)];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function serializeVariation(mixed $variation): ?array
    {
        if (!$variation instanceof WC_Product_Variation) {
            return null;
        }

        return [
            'id' => $variation->get_id(),
            'label' => $this->variationLabel($variation),
            'price' => (float) $variation->get_regular_price(),
            'stock_quantity' => $variation->get_manage_stock() ? $variation->get_stock_quantity() : null,
        ];
    }

    private function variationLabel(WC_Product_Variation $variation): string
    {
        $parts = [];

        foreach ($variation->get_variation_attributes() as $attributeKey => $termSlug) {
            $taxonomy = str_replace('attribute_', '', $attributeKey);
            $term = $termSlug !== '' ? get_term_by('slug', (string) $termSlug, $taxonomy) : null;
            $parts[] = $term instanceof WP_Term ? $term->name : ucfirst((string) $termSlug);
        }

        return implode(' / ', array_filter($parts));
    }

    private function firstCategoryName(WC_Product $product): ?string
    {
        $terms = get_the_terms($product->get_id(), 'product_cat');

        if (!is_array($terms) || $terms === []) {
            return null;
        }

        $first = reset($terms);

        return $first instanceof WP_Term ? $first->name : null;
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function writableArgs(): array
    {
        return [
            'name' => ['required' => true, 'type' => 'string'],
            'description' => ['required' => false, 'type' => 'string'],
            'price' => ['required' => true, 'type' => 'number'],
            'image_id' => ['required' => false, 'type' => 'integer'],
            'manage_stock' => ['required' => false, 'type' => 'boolean'],
            'stock_quantity' => ['required' => false, 'type' => 'integer'],
            'low_stock_amount' => ['required' => false, 'type' => 'integer'],
            'category' => ['required' => false, 'type' => 'string'],
            'tax_class' => ['required' => false, 'type' => 'string'],
            'sizes' => ['required' => false, 'type' => 'string'],
            'colors' => ['required' => false, 'type' => 'string'],
            'grade_levels' => [
                'required' => false,
                'type' => 'array',
                'items' => ['type' => 'string'],
            ],
        ];
    }
}
