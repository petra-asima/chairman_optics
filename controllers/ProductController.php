<?php
// Controller: checks product, brand, category, stock and lens data,
// applies the business rules, then calls the ProductClass model.
// Every change is written to the audit trail.
require_once __DIR__ . '/../classes/ProductClass.php';
require_once __DIR__ . '/../classes/AuditClass.php';

class ProductController
{
    // Options for the "Made for" dropdown on the product form
    public const PRODUCT_GENDERS = ['Men', 'Women', 'Unisex', 'Kids'];

    // Reasons staff can choose on the stock page. 'Sale' is not here
    // because sales take stock out automatically when an order is paid.
    public const MANUAL_STOCK_REASONS = ['Restock', 'Return', 'Adjustment'];

    // The two groups in co_lens_options (must match the ENUM)
    public const LENS_GROUPS = ['Lens type', 'Coating'];

    // Product image rules: up to 2 MB, and only these file types.
    // The key is the real type read from the file, the value is the
    // extension the file is saved with.
    private const MAX_IMAGE_BYTES = 2 * 1024 * 1024;
    private const IMAGE_TYPES = [
        'image/jpeg' => 'jpg',
        'image/png'  => 'png',
        'image/webp' => 'webp',
    ];
    private const IMAGE_FOLDER = 'images/products/';

    // A price such as 250 or 250.50 (up to 99,999,999.99)
    private const PRICE_PATTERN = '/^\d{1,8}(\.\d{1,2})?$/';

    private $product;
    private $audit;

    public function __construct()
    {
        $this->product = new ProductClass();
        $this->audit   = new AuditClass();
    }

    // =================================================================
    // Reading data for the views
    // =================================================================

    public function getCategories(): array
    {
        return $this->product->getAllCategories();
    }

    public function getBrands(): array
    {
        return $this->product->getAllBrands();
    }

    public function getProducts(string $search = '', int $catId = 0, bool $lowOnly = false): array
    {
        return $this->product->getProducts($search, $catId, $lowOnly);
    }

    /** @return array|false */
    public function getProductById(int $productId)
    {
        return $this->product->getProductById($productId);
    }

    public function getLowStock(): array
    {
        return $this->product->getLowStock();
    }

    public function getStockProducts(): array
    {
        return $this->product->getStockProducts();
    }

    public function getRecentMovements(int $limit = 50): array
    {
        return $this->product->getRecentMovements($limit);
    }

    public function getLensOptions(): array
    {
        return $this->product->getAllLensOptions();
    }

    // =================================================================
    // Online shop (customer pages)
    // =================================================================

    // Products on each page of the shop
    public const SHOP_PER_PAGE = 12;

    // Sort choices shown to customers (the SQL is in ProductClass)
    public const SHOP_SORT_LABELS = [
        'newest'     => 'Newest first',
        'price_low'  => 'Price: low to high',
        'price_high' => 'Price: high to low',
        'name'       => 'Name: A to Z',
    ];

    /**
     * Read and clean the shop filters from the address bar ($_GET).
     * Anything that is not a real choice is ignored, so a changed or
     * broken link just shows more products instead of an error.
     * @return array  q, cat, brand, colour, gender, min, max, sort, page
     */
    public function cleanShopFilters(array $get): array
    {
        $min = trim((string) ($get['min'] ?? ''));
        $max = trim((string) ($get['max'] ?? ''));
        $min = preg_match(self::PRICE_PATTERN, $min) ? (float) $min : null;
        $max = preg_match(self::PRICE_PATTERN, $max) ? (float) $max : null;

        // If the prices are the wrong way round, swap them
        if ($min !== null && $max !== null && $min > $max) {
            [$min, $max] = [$max, $min];
        }

        $colour = trim((string) ($get['colour'] ?? ''));
        if (!in_array($colour, $this->product->getShopColours(), true)) {
            $colour = '';
        }

        $gender = (string) ($get['gender'] ?? '');
        if (!in_array($gender, self::PRODUCT_GENDERS, true)) {
            $gender = '';
        }

        $sort = (string) ($get['sort'] ?? 'newest');
        if (!isset(self::SHOP_SORT_LABELS[$sort])) {
            $sort = 'newest';
        }

        return [
            'q'      => mb_substr(trim((string) ($get['q'] ?? '')), 0, 100),
            'cat'    => max(0, (int) ($get['cat'] ?? 0)),
            'brand'  => max(0, (int) ($get['brand'] ?? 0)),
            'colour' => $colour,
            'gender' => $gender,
            'min'    => $min,
            'max'    => $max,
            'sort'   => $sort,
            'page'   => max(1, (int) ($get['page'] ?? 1)),
        ];
    }

    /**
     * Everything the shop page needs: one page of products and the page numbers.
     * @return array  products, total, page, pages
     */
    public function getShopPage(array $filters): array
    {
        $total = $this->product->countShopProducts($filters);
        $pages = max(1, (int) ceil($total / self::SHOP_PER_PAGE));
        $page  = min($filters['page'], $pages); // never past the last page

        $products = $this->product->getShopProducts(
            $filters, $filters['sort'], self::SHOP_PER_PAGE, ($page - 1) * self::SHOP_PER_PAGE
        );

        return ['products' => $products, 'total' => $total, 'page' => $page, 'pages' => $pages];
    }

    /** @return array|false  a product customers may see, or false */
    public function getShopProduct(int $productId)
    {
        return $this->product->getShopProductById($productId);
    }

    public function getRelatedProducts(int $catId, int $excludeId): array
    {
        return $this->product->getRelatedProducts($catId, $excludeId, 4);
    }

    public function getShopCategories(): array
    {
        return $this->product->getShopCategories();
    }

    public function getShopBrands(): array
    {
        return $this->product->getShopBrands();
    }

    public function getShopColours(): array
    {
        return $this->product->getShopColours();
    }

    // =================================================================
    // Categories
    // Every method returns ['success' => bool, 'message' => text].
    // =================================================================

    public function addCategory(string $name, int $staffId): array
    {
        $error = $this->checkName($name, 'category');
        if ($error !== '') {
            return ['success' => false, 'message' => $error];
        }
        if ($this->product->categoryNameExists($name)) {
            return ['success' => false, 'message' => 'A category called "' . $name . '" already exists.'];
        }

        $newId = $this->product->addCategory($name);
        if ($newId === false) {
            return ['success' => false, 'message' => "We couldn't add the category. Please try again."];
        }

        $this->audit->log($staffId, 'CREATE', 'Added category ' . $name, 'co_categories', $newId);
        return ['success' => true, 'message' => 'Category "' . $name . '" added.'];
    }

    public function updateCategory(int $catId, string $name, int $staffId): array
    {
        $old = $this->product->getCategoryById($catId);
        if (!$old) {
            return ['success' => false, 'message' => 'That category no longer exists.'];
        }

        $error = $this->checkName($name, 'category');
        if ($error !== '') {
            return ['success' => false, 'message' => $error];
        }
        if ($this->product->categoryNameExists($name, $catId)) {
            return ['success' => false, 'message' => 'A category called "' . $name . '" already exists.'];
        }

        if (!$this->product->updateCategory($catId, $name)) {
            return ['success' => false, 'message' => "We couldn't rename the category. Please try again."];
        }

        $this->audit->log($staffId, 'UPDATE', 'Renamed category ' . $old['cat_name'] . ' to ' . $name,
                          'co_categories', $catId);
        return ['success' => true, 'message' => 'Category renamed to "' . $name . '".'];
    }

    public function deleteCategory(int $catId, int $staffId): array
    {
        $old = $this->product->getCategoryById($catId);
        if (!$old) {
            return ['success' => false, 'message' => 'That category no longer exists.'];
        }

        // Business rule: a category still in use cannot be deleted,
        // otherwise those products would have no category.
        $count = $this->product->countProductsInCategory($catId);
        if ($count > 0) {
            return ['success' => false,
                    'message' => 'This category has ' . $count . ' product(s). Move them to another category first.'];
        }

        if (!$this->product->deleteCategory($catId)) {
            return ['success' => false, 'message' => "We couldn't delete the category. Please try again."];
        }

        $this->audit->log($staffId, 'DELETE', 'Deleted category ' . $old['cat_name'], 'co_categories', $catId);
        return ['success' => true, 'message' => 'Category "' . $old['cat_name'] . '" deleted.'];
    }

    // =================================================================
    // Brands (same rules as categories)
    // =================================================================

    public function addBrand(string $name, int $staffId): array
    {
        $error = $this->checkName($name, 'brand');
        if ($error !== '') {
            return ['success' => false, 'message' => $error];
        }
        if ($this->product->brandNameExists($name)) {
            return ['success' => false, 'message' => 'A brand called "' . $name . '" already exists.'];
        }

        $newId = $this->product->addBrand($name);
        if ($newId === false) {
            return ['success' => false, 'message' => "We couldn't add the brand. Please try again."];
        }

        $this->audit->log($staffId, 'CREATE', 'Added brand ' . $name, 'co_brands', $newId);
        return ['success' => true, 'message' => 'Brand "' . $name . '" added.'];
    }

    public function updateBrand(int $brandId, string $name, int $staffId): array
    {
        $old = $this->product->getBrandById($brandId);
        if (!$old) {
            return ['success' => false, 'message' => 'That brand no longer exists.'];
        }

        $error = $this->checkName($name, 'brand');
        if ($error !== '') {
            return ['success' => false, 'message' => $error];
        }
        if ($this->product->brandNameExists($name, $brandId)) {
            return ['success' => false, 'message' => 'A brand called "' . $name . '" already exists.'];
        }

        if (!$this->product->updateBrand($brandId, $name)) {
            return ['success' => false, 'message' => "We couldn't rename the brand. Please try again."];
        }

        $this->audit->log($staffId, 'UPDATE', 'Renamed brand ' . $old['brand_name'] . ' to ' . $name,
                          'co_brands', $brandId);
        return ['success' => true, 'message' => 'Brand renamed to "' . $name . '".'];
    }

    public function deleteBrand(int $brandId, int $staffId): array
    {
        $old = $this->product->getBrandById($brandId);
        if (!$old) {
            return ['success' => false, 'message' => 'That brand no longer exists.'];
        }

        // The database would quietly remove the brand from its products
        // (ON DELETE SET NULL), so we stop it here and explain instead.
        $count = $this->product->countProductsInBrand($brandId);
        if ($count > 0) {
            return ['success' => false,
                    'message' => 'This brand has ' . $count . ' product(s). Change their brand first.'];
        }

        if (!$this->product->deleteBrand($brandId)) {
            return ['success' => false, 'message' => "We couldn't delete the brand. Please try again."];
        }

        $this->audit->log($staffId, 'DELETE', 'Deleted brand ' . $old['brand_name'], 'co_brands', $brandId);
        return ['success' => true, 'message' => 'Brand "' . $old['brand_name'] . '" deleted.'];
    }

    // =================================================================
    // Products
    // =================================================================

    /**
     * Check every product field.
     * @param array $data   the raw values from the form
     * @param bool  $isNew  true when adding (the opening stock is checked too)
     * @return array  field name => error message (empty when all is fine)
     */
    public function validateProduct(array $data, bool $isNew): array
    {
        $errors = [];

        if (!$this->product->getCategoryById((int) $data['product_cat'])) {
            $errors['product_cat'] = 'Please choose a category.';
        }
        // Brand is optional (services have no brand), but must be real if chosen
        if ((int) $data['product_brand'] > 0 && !$this->product->getBrandById((int) $data['product_brand'])) {
            $errors['product_brand'] = 'Please choose a brand from the list.';
        }
        if (strlen($data['product_title']) < 2 || strlen($data['product_title']) > 200) {
            $errors['product_title'] = 'Enter a product name of 2 to 200 characters.';
        }
        if (!preg_match(self::PRICE_PATTERN, $data['product_price'])) {
            $errors['product_price'] = 'Enter a price in cedis, for example 250 or 250.50.';
        }
        if (strlen($data['product_desc']) > 2000) {
            $errors['product_desc'] = 'The description can be up to 2000 characters.';
        }
        if (strlen($data['product_keywords']) > 255) {
            $errors['product_keywords'] = 'Keywords can be up to 255 characters.';
        }
        if (strlen($data['product_colour']) > 50) {
            $errors['product_colour'] = 'The colour can be up to 50 characters.';
        }
        if ($data['product_gender'] !== '' && !in_array($data['product_gender'], self::PRODUCT_GENDERS, true)) {
            $errors['product_gender'] = 'Please choose an option from the list.';
        }

        // Stock numbers only matter for things that are kept in stock
        if ($data['is_service'] !== '1') {
            if ($this->wholeNumber($data['reorder_level']) === null) {
                $errors['reorder_level'] = 'Enter a whole number of 0 or more.';
            }
            if ($isNew && $this->wholeNumber($data['opening_stock']) === null) {
                $errors['opening_stock'] = 'Enter a whole number of 0 or more.';
            }
        }

        return $errors;
    }

    /**
     * Add a product, its image and its opening stock.
     * @param array $file  the $_FILES entry for the image
     * @return array ['success' => true, 'message' => ...] or ['success' => false, 'errors' => [...]]
     */
    public function addProduct(array $data, array $file, int $staffId): array
    {
        $errors = $this->validateProduct($data, true);
        if (!empty($errors)) {
            return ['success' => false, 'errors' => $errors];
        }

        // Save the image (if one was chosen) before the database row
        $image = $this->saveImage($file);
        if (isset($image['error'])) {
            return ['success' => false, 'errors' => ['product_image' => $image['error']]];
        }

        $row = $this->buildProductRow($data, $image['path']);
        $newId = $this->product->addProduct($row);

        if ($newId === false) {
            $this->deleteImage($image['path']); // do not leave an unused file behind
            return ['success' => false, 'errors' => ['form' => "We couldn't save the product. Please try again."]];
        }

        $this->audit->log($staffId, 'CREATE', 'Added product ' . $row['product_title'], 'co_products', $newId);

        // Opening stock goes through adjustStock() so it appears in the stock history
        $opening = $row['is_service'] ? 0 : (int) $this->wholeNumber($data['opening_stock']);
        if ($opening > 0) {
            $this->product->adjustStock($newId, $opening, 'Restock', $staffId, 'Opening stock');
        }

        return ['success' => true, 'message' => 'Product "' . $row['product_title'] . '" added.'];
    }

    /**
     * Update a product's details, and replace its image if a new one was chosen.
     */
    public function updateProduct(int $productId, array $data, array $file, int $staffId): array
    {
        $old = $this->product->getProductById($productId);
        if (!$old) {
            return ['success' => false, 'errors' => ['form' => 'That product no longer exists.']];
        }

        $errors = $this->validateProduct($data, false);
        if (!empty($errors)) {
            return ['success' => false, 'errors' => $errors];
        }

        $image = $this->saveImage($file);
        if (isset($image['error'])) {
            return ['success' => false, 'errors' => ['product_image' => $image['error']]];
        }

        // Keep the old image unless a new one was uploaded
        $imagePath = $image['path'] ?? $old['product_image'];
        $row = $this->buildProductRow($data, $imagePath);

        if (!$this->product->updateProduct($productId, $row)) {
            $this->deleteImage($image['path']);
            return ['success' => false, 'errors' => ['form' => "We couldn't save the changes. Please try again."]];
        }

        // The new image is saved, so the old file is no longer needed
        if ($image['path'] !== null) {
            $this->deleteImage($old['product_image']);
        }

        $this->audit->log($staffId, 'UPDATE', 'Updated product ' . $row['product_title'], 'co_products', $productId);
        return ['success' => true, 'message' => 'Product "' . $row['product_title'] . '" updated.'];
    }

    public function deleteProduct(int $productId, int $staffId): array
    {
        $old = $this->product->getProductById($productId);
        if (!$old) {
            return ['success' => false, 'message' => 'That product no longer exists.'];
        }

        // Business rule: a product that appears on an order is part of
        // the sales records and invoices, so it can only be hidden.
        if ($this->product->productHasOrders($productId)) {
            return ['success' => false,
                    'message' => 'This product is on past orders, so it cannot be deleted. '
                               . 'Edit it and untick "Show in the shop" to hide it instead.'];
        }

        if (!$this->product->deleteProduct($productId)) {
            return ['success' => false, 'message' => "We couldn't delete the product. Please try again."];
        }

        $this->deleteImage($old['product_image']);
        $this->audit->log($staffId, 'DELETE', 'Deleted product ' . $old['product_title'], 'co_products', $productId);
        return ['success' => true, 'message' => 'Product "' . $old['product_title'] . '" deleted.'];
    }

    // =================================================================
    // Stock
    // =================================================================

    /**
     * Record a manual stock change from the stock page.
     * @param array $data  product_id, reason, qty, direction ('in' or 'out'), note
     */
    public function adjustStock(array $data, int $staffId): array
    {
        $productId = (int) $data['product_id'];
        $product   = $this->product->getProductById($productId);

        if (!$product || (int) $product['is_service'] === 1) {
            return ['success' => false, 'message' => 'Please choose a product that is kept in stock.'];
        }
        if (!in_array($data['reason'], self::MANUAL_STOCK_REASONS, true)) {
            return ['success' => false, 'message' => 'Please choose a reason.'];
        }

        $qty = $this->wholeNumber($data['qty']);
        if ($qty === null || $qty < 1) {
            return ['success' => false, 'message' => 'Enter a quantity of 1 or more.'];
        }
        if (strlen($data['note']) > 255) {
            return ['success' => false, 'message' => 'The note can be up to 255 characters.'];
        }

        // Restocks and returns always add stock. Only an adjustment
        // (for example a broken or lost item) can take stock out.
        $change = $qty;
        if ($data['reason'] === 'Adjustment' && $data['direction'] === 'out') {
            $change = -$qty;
        }

        if ((int) $product['product_stock'] + $change < 0) {
            return ['success' => false,
                    'message' => 'Only ' . $product['product_stock'] . ' in stock, so you cannot remove ' . $qty . '.'];
        }

        $note = $data['note'] === '' ? null : $data['note'];
        if (!$this->product->adjustStock($productId, $change, $data['reason'], $staffId, $note)) {
            return ['success' => false, 'message' => "We couldn't update the stock. Please try again."];
        }

        $sign = $change > 0 ? '+' : '';
        $this->audit->log($staffId, 'STOCK',
                          $data['reason'] . ' ' . $sign . $change . ' for ' . $product['product_title'],
                          'co_products', $productId);

        $newStock = (int) $product['product_stock'] + $change;
        return ['success' => true,
                'message' => 'Stock updated. ' . $product['product_title'] . ' now has ' . $newStock . ' in stock.'];
    }

    // =================================================================
    // Lens options
    // =================================================================

    /** Check the lens option fields. @return string  an error message, or '' */
    private function checkLens(array $data, int $ignoreId = 0): string
    {
        if (strlen($data['lens_name']) < 2 || strlen($data['lens_name']) > 100) {
            return 'Enter a lens name of 2 to 100 characters.';
        }
        if (!in_array($data['option_group'], self::LENS_GROUPS, true)) {
            return 'Choose Lens type or Coating.';
        }
        if (!preg_match(self::PRICE_PATTERN, $data['lens_price'])) {
            return 'Enter a price in cedis, for example 150 or 150.00.';
        }
        if (strlen($data['lens_desc']) > 255) {
            return 'The description can be up to 255 characters.';
        }
        if ($this->product->lensNameExists($data['lens_name'], $ignoreId)) {
            return 'A lens option called "' . $data['lens_name'] . '" already exists.';
        }
        return '';
    }

    public function addLensOption(array $data, int $staffId): array
    {
        $error = $this->checkLens($data);
        if ($error !== '') {
            return ['success' => false, 'message' => $error];
        }

        $desc  = $data['lens_desc'] === '' ? null : $data['lens_desc'];
        $newId = $this->product->addLensOption($data['lens_name'], $data['option_group'],
                                               (float) $data['lens_price'], $desc);
        if ($newId === false) {
            return ['success' => false, 'message' => "We couldn't add the lens option. Please try again."];
        }

        $this->audit->log($staffId, 'CREATE', 'Added lens option ' . $data['lens_name'], 'co_lens_options', $newId);
        return ['success' => true, 'message' => 'Lens option "' . $data['lens_name'] . '" added.'];
    }

    public function updateLensOption(int $lensId, array $data, int $staffId): array
    {
        $old = $this->product->getLensById($lensId);
        if (!$old) {
            return ['success' => false, 'message' => 'That lens option no longer exists.'];
        }

        $error = $this->checkLens($data, $lensId);
        if ($error !== '') {
            return ['success' => false, 'message' => $error];
        }

        $desc = $data['lens_desc'] === '' ? null : $data['lens_desc'];
        $ok   = $this->product->updateLensOption($lensId, $data['lens_name'], $data['option_group'],
                                                 (float) $data['lens_price'], $desc,
                                                 $data['is_active'] === '1' ? 1 : 0);
        if (!$ok) {
            return ['success' => false, 'message' => "We couldn't save the lens option. Please try again."];
        }

        $this->audit->log($staffId, 'UPDATE',
                          'Updated lens option ' . $data['lens_name'] . ' (price ' . $old['lens_price']
                          . ' to ' . $data['lens_price'] . ')',
                          'co_lens_options', $lensId);
        return ['success' => true, 'message' => 'Lens option "' . $data['lens_name'] . '" saved.'];
    }

    // =================================================================
    // Helpers used only inside this controller
    // =================================================================

    /** Check a brand or category name. @return string  an error message, or '' */
    private function checkName(string $name, string $label): string
    {
        if (strlen($name) < 2 || strlen($name) > 100) {
            return 'Enter a ' . $label . ' name of 2 to 100 characters.';
        }
        return '';
    }

    /** Turn text into a whole number of 0 or more, or null if it is not one. */
    private function wholeNumber(string $value): ?int
    {
        $number = filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 0, 'max_range' => 99999]]);
        return $number === false ? null : $number;
    }

    /**
     * Turn the checked form data into the array the model expects.
     * Empty optional text becomes NULL in the database.
     */
    private function buildProductRow(array $data, ?string $imagePath): array
    {
        $isService = $data['is_service'] === '1' ? 1 : 0;

        return [
            'product_cat'      => (int) $data['product_cat'],
            'product_brand'    => (int) $data['product_brand'] > 0 ? (int) $data['product_brand'] : null,
            'product_title'    => $data['product_title'],
            'product_price'    => (float) $data['product_price'],
            'product_desc'     => $data['product_desc'] === '' ? null : $data['product_desc'],
            'product_image'    => $imagePath,
            'product_keywords' => $data['product_keywords'] === '' ? null : $data['product_keywords'],
            'product_colour'   => $data['product_colour'] === '' ? null : $data['product_colour'],
            'product_gender'   => $data['product_gender'] === '' ? null : $data['product_gender'],
            // Services have no stock, so no reorder level and no lenses
            'reorder_level'    => $isService ? 0 : (int) $this->wholeNumber($data['reorder_level']),
            'is_service'       => $isService,
            'needs_lenses'     => (!$isService && $data['needs_lenses'] === '1') ? 1 : 0,
            'is_active'        => $data['is_active'] === '1' ? 1 : 0,
        ];
    }

    /**
     * Check and save an uploaded product image.
     * @return array ['path' => 'images/products/...'] on success,
     *               ['path' => null] when no file was chosen,
     *               or ['error' => message]
     */
    private function saveImage(array $file): array
    {
        $error = $file['error'] ?? UPLOAD_ERR_NO_FILE;

        if ($error === UPLOAD_ERR_NO_FILE) {
            return ['path' => null];
        }
        if ($error === UPLOAD_ERR_INI_SIZE || $error === UPLOAD_ERR_FORM_SIZE) {
            return ['error' => 'The image is too large. The limit is 2 MB.'];
        }
        if ($error !== UPLOAD_ERR_OK || !is_uploaded_file($file['tmp_name'])) {
            return ['error' => 'The image did not upload. Please try again.'];
        }
        if ($file['size'] > self::MAX_IMAGE_BYTES) {
            return ['error' => 'The image is too large. The limit is 2 MB.'];
        }

        // Read the real file type from the file itself. The type the
        // browser sends, and the file name, can both be faked.
        $finfo    = new finfo(FILEINFO_MIME_TYPE);
        $mimeType = $finfo->file($file['tmp_name']);
        if (!isset(self::IMAGE_TYPES[$mimeType])) {
            return ['error' => 'Please upload a JPG, PNG or WEBP image.'];
        }

        // A new random name, so files never overwrite each other and the
        // uploader cannot choose the name (or sneak in a .php file).
        $fileName = 'prod_' . bin2hex(random_bytes(8)) . '.' . self::IMAGE_TYPES[$mimeType];
        $target   = __DIR__ . '/../' . self::IMAGE_FOLDER . $fileName;

        if (!move_uploaded_file($file['tmp_name'], $target)) {
            log_error('Could not save product image to ' . $target . '. Check the folder permissions.');
            return ['error' => "We couldn't save the image. Please tell the Admin."];
        }

        return ['path' => self::IMAGE_FOLDER . $fileName];
    }

    /** Remove an old product image file, if there is one. */
    private function deleteImage(?string $path): void
    {
        // Only ever delete files inside images/products/
        if ($path === null || strpos($path, self::IMAGE_FOLDER) !== 0) {
            return;
        }
        $fullPath = __DIR__ . '/../' . self::IMAGE_FOLDER . basename($path);
        if (is_file($fullPath)) {
            unlink($fullPath);
        }
    }
}
