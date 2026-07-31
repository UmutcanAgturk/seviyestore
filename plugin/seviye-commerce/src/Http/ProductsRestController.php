<?php

declare(strict_types=1);

namespace Seviye\Commerce\Http;

use Seviye\Branches\Contracts\BranchLookupInterface;
use Seviye\Branches\Contracts\BranchMembershipInterface;
use Seviye\Commerce\Domain\ProductBranchStatus;
use Seviye\Commerce\Rbac\ProductCapability;
use Seviye\Commerce\Repository\ProductBranchVisibilityRepositoryInterface;
use Seviye\Core\Http\AbstractRestController;
use Seviye\Core\Http\RestApiRegistrar;
use WC_Product;
use WC_Product_Simple;
use WP_REST_Request;
use WP_REST_Response;

/**
 * seviye/v1/commerce/products/*. Products stay entirely WooCommerce's own
 * (wp_posts/WC_Product) - this controller is a thin wrapper so Şube
 * Müdürü/Genel Merkez, who hold none of WordPress' native
 * edit_products/publish_products capabilities, can manage a SHARED catalog
 * without wp-admin. Full edit/delete is Genel Merkez/Bölge Müdürü only
 * (mirrors PricingRestController's GENERAL-scope gating); a Şube Müdürü may
 * CREATE into the shared catalog and toggle a product's active/passive
 * status for their OWN branch only - never another branch's, never the
 * product's own name/price/etc.
 */
final class ProductsRestController extends AbstractRestController
{
    public function __construct(
        private readonly ProductBranchVisibilityRepositoryInterface $visibility,
        private readonly BranchMembershipInterface $branchMemberships,
        private readonly BranchLookupInterface $branches
    ) {
    }

    public function registerRoutes(): void
    {
        register_rest_route(RestApiRegistrar::NAMESPACE, '/commerce/products', [
            [
                'methods' => 'GET',
                'callback' => [$this, 'index'],
                'permission_callback' => $this->requireCapability(ProductCapability::MANAGE_PRODUCTS->value),
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
                'permission_callback' => $this->requireCapability(ProductCapability::MANAGE_PRODUCTS->value),
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

        if ($name === '' || $price < 0) {
            return new WP_REST_Response(
                ['message' => __('Ürün adı ve geçerli bir fiyat gerekli.', 'seviye-commerce')],
                422
            );
        }

        $product = new WC_Product_Simple();
        $this->applyWritableFields($product, $request);
        $product->set_status('publish');
        $productId = $product->save();
        $this->applyCategory($productId, $request);

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

    public function canManageProductFully(): bool
    {
        if (!current_user_can(ProductCapability::MANAGE_PRODUCTS->value)) {
            return false;
        }

        // Full edit/delete is Genel Merkez/Bölge Müdürü only - a Şube
        // Müdürü's power over the shared catalog is limited to creating
        // into it and toggling their own branch's visibility (see
        // canToggleBranchStatus()), never rewriting another branch-created
        // product's name/price/etc.
        return $this->currentUserBranchId() === null;
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

    private function applyWritableFields(WC_Product $product, WP_REST_Request $request): void
    {
        $product->set_name(trim((string) $request->get_param('name')));
        $product->set_description((string) ($request->get_param('description') ?? ''));
        $product->set_regular_price((string) (float) $request->get_param('price'));

        $imageId = (int) $request->get_param('image_id');

        if ($imageId > 0) {
            $product->set_image_id($imageId);
        }

        $manageStock = (bool) $request->get_param('manage_stock');
        $product->set_manage_stock($manageStock);

        if ($manageStock) {
            $product->set_stock_quantity((int) $request->get_param('stock_quantity'));
        }
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

        return [
            'id' => $product->get_id(),
            'name' => $product->get_name(),
            'description' => $product->get_description(),
            'price' => (float) $product->get_regular_price(),
            'image_id' => $imageId ?: null,
            'image_url' => $imageId ? wp_get_attachment_image_url($imageId, 'thumbnail') : null,
            'manage_stock' => $product->get_manage_stock(),
            'stock_quantity' => $product->get_manage_stock() ? $product->get_stock_quantity() : null,
            'category' => $this->firstCategoryName($product),
            'own_branch_active' => $ownBranchId !== null
                ? $this->visibility->isActiveForBranch($product->get_id(), $ownBranchId)
                : null,
        ];
    }

    private function firstCategoryName(WC_Product $product): ?string
    {
        $terms = get_the_terms($product->get_id(), 'product_cat');

        if (!is_array($terms) || $terms === []) {
            return null;
        }

        $first = reset($terms);

        return $first instanceof \WP_Term ? $first->name : null;
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
            'category' => ['required' => false, 'type' => 'string'],
        ];
    }
}
