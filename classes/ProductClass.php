<?php
// Model: all SQL for products and stock lives here.
// Tables used: co_categories, co_brands, co_products,
// co_stock_movements and co_lens_options.
//
// Note: the shoppn lab uses a separate class for brands and categories.
// This project keeps them together in one class because they are only
// ever used by the Products and Stock module. Each table has its own
// section below.
require_once __DIR__ . '/../core/db_class.php';

class ProductClass extends Database
{
    // =================================================================
    // CATEGORIES (co_categories)
    // =================================================================

    /**
     * Every category with how many products use it.
     * @return array
     */
    public function getAllCategories(): array
    {
        $result = $this->conn->query(
            'SELECT c.cat_id, c.cat_name, COUNT(p.product_id) AS product_count
             FROM co_categories c
             LEFT JOIN co_products p ON p.product_cat = c.cat_id
             GROUP BY c.cat_id, c.cat_name
             ORDER BY c.cat_name'
        );
        return $result->fetch_all(MYSQLI_ASSOC);
    }

    /** @return array|false */
    public function getCategoryById(int $catId)
    {
        $stmt = $this->conn->prepare('SELECT cat_id, cat_name FROM co_categories WHERE cat_id = ?');
        $stmt->bind_param('i', $catId);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        return $row ?: false;
    }

    /**
     * Is this category name already used by another category?
     * $ignoreId lets a category keep its own name when it is renamed.
     */
    public function categoryNameExists(string $name, int $ignoreId = 0): bool
    {
        $stmt = $this->conn->prepare('SELECT cat_id FROM co_categories WHERE cat_name = ? AND cat_id <> ?');
        $stmt->bind_param('si', $name, $ignoreId);
        $stmt->execute();
        $stmt->store_result();
        $exists = $stmt->num_rows > 0;
        $stmt->close();
        return $exists;
    }

    /** @return int|false  the new cat_id, or false if the insert failed */
    public function addCategory(string $name)
    {
        try {
            $stmt = $this->conn->prepare('INSERT INTO co_categories (cat_name) VALUES (?)');
            $stmt->bind_param('s', $name);
            $stmt->execute();
            $newId = $stmt->insert_id;
            $stmt->close();
            return $newId;
        } catch (mysqli_sql_exception $ex) {
            log_error('addCategory failed: ' . $ex->getMessage());
            return false;
        }
    }

    public function updateCategory(int $catId, string $name): bool
    {
        try {
            $stmt = $this->conn->prepare('UPDATE co_categories SET cat_name = ? WHERE cat_id = ?');
            $stmt->bind_param('si', $name, $catId);
            $ok = $stmt->execute();
            $stmt->close();
            return $ok;
        } catch (mysqli_sql_exception $ex) {
            log_error('updateCategory failed: ' . $ex->getMessage());
            return false;
        }
    }

    /** Only call this after checking no products use the category. */
    public function deleteCategory(int $catId): bool
    {
        try {
            $stmt = $this->conn->prepare('DELETE FROM co_categories WHERE cat_id = ?');
            $stmt->bind_param('i', $catId);
            $ok = $stmt->execute();
            $stmt->close();
            return $ok;
        } catch (mysqli_sql_exception $ex) {
            log_error('deleteCategory failed: ' . $ex->getMessage());
            return false;
        }
    }

    /** How many products are in this category? */
    public function countProductsInCategory(int $catId): int
    {
        $stmt = $this->conn->prepare('SELECT COUNT(*) AS total FROM co_products WHERE product_cat = ?');
        $stmt->bind_param('i', $catId);
        $stmt->execute();
        $total = (int) $stmt->get_result()->fetch_assoc()['total'];
        $stmt->close();
        return $total;
    }


    // =================================================================
    // BRANDS (co_brands)
    // =================================================================

    /**
     * Every brand with how many products use it.
     * @return array
     */
    public function getAllBrands(): array
    {
        $result = $this->conn->query(
            'SELECT b.brand_id, b.brand_name, COUNT(p.product_id) AS product_count
             FROM co_brands b
             LEFT JOIN co_products p ON p.product_brand = b.brand_id
             GROUP BY b.brand_id, b.brand_name
             ORDER BY b.brand_name'
        );
        return $result->fetch_all(MYSQLI_ASSOC);
    }

    /** @return array|false */
    public function getBrandById(int $brandId)
    {
        $stmt = $this->conn->prepare('SELECT brand_id, brand_name FROM co_brands WHERE brand_id = ?');
        $stmt->bind_param('i', $brandId);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        return $row ?: false;
    }

    /** Is this brand name already used by another brand? */
    public function brandNameExists(string $name, int $ignoreId = 0): bool
    {
        $stmt = $this->conn->prepare('SELECT brand_id FROM co_brands WHERE brand_name = ? AND brand_id <> ?');
        $stmt->bind_param('si', $name, $ignoreId);
        $stmt->execute();
        $stmt->store_result();
        $exists = $stmt->num_rows > 0;
        $stmt->close();
        return $exists;
    }

    /** @return int|false  the new brand_id, or false if the insert failed */
    public function addBrand(string $name)
    {
        try {
            $stmt = $this->conn->prepare('INSERT INTO co_brands (brand_name) VALUES (?)');
            $stmt->bind_param('s', $name);
            $stmt->execute();
            $newId = $stmt->insert_id;
            $stmt->close();
            return $newId;
        } catch (mysqli_sql_exception $ex) {
            log_error('addBrand failed: ' . $ex->getMessage());
            return false;
        }
    }

    public function updateBrand(int $brandId, string $name): bool
    {
        try {
            $stmt = $this->conn->prepare('UPDATE co_brands SET brand_name = ? WHERE brand_id = ?');
            $stmt->bind_param('si', $name, $brandId);
            $ok = $stmt->execute();
            $stmt->close();
            return $ok;
        } catch (mysqli_sql_exception $ex) {
            log_error('updateBrand failed: ' . $ex->getMessage());
            return false;
        }
    }

    /** Only call this after checking no products use the brand. */
    public function deleteBrand(int $brandId): bool
    {
        try {
            $stmt = $this->conn->prepare('DELETE FROM co_brands WHERE brand_id = ?');
            $stmt->bind_param('i', $brandId);
            $ok = $stmt->execute();
            $stmt->close();
            return $ok;
        } catch (mysqli_sql_exception $ex) {
            log_error('deleteBrand failed: ' . $ex->getMessage());
            return false;
        }
    }

    /** How many products use this brand? */
    public function countProductsInBrand(int $brandId): int
    {
        $stmt = $this->conn->prepare('SELECT COUNT(*) AS total FROM co_products WHERE product_brand = ?');
        $stmt->bind_param('i', $brandId);
        $stmt->execute();
        $total = (int) $stmt->get_result()->fetch_assoc()['total'];
        $stmt->close();
        return $total;
    }


    // =================================================================
    // PRODUCTS (co_products)
    // =================================================================

    /**
     * Products for the staff list, with brand and category names.
     * Every filter is optional.
     * @param string $search   matches the title or keywords
     * @param int    $catId    0 = all categories
     * @param bool   $lowOnly  true = only products at or below their reorder level
     * @return array
     */
    public function getProducts(string $search = '', int $catId = 0, bool $lowOnly = false): array
    {
        $sql = 'SELECT p.*, c.cat_name, b.brand_name
                FROM co_products p
                JOIN co_categories c ON c.cat_id = p.product_cat
                LEFT JOIN co_brands b ON b.brand_id = p.product_brand
                WHERE 1 = 1';

        // Build the WHERE part one filter at a time.
        // Values still go through bind_param, never straight into the SQL.
        $types  = '';
        $params = [];

        if ($search !== '') {
            $sql     .= ' AND (p.product_title LIKE ? OR p.product_keywords LIKE ?)';
            $like     = '%' . $search . '%';
            $types   .= 'ss';
            $params[] = $like;
            $params[] = $like;
        }
        if ($catId > 0) {
            $sql     .= ' AND p.product_cat = ?';
            $types   .= 'i';
            $params[] = $catId;
        }
        if ($lowOnly) {
            $sql .= ' AND p.is_service = 0 AND p.product_stock <= p.reorder_level';
        }

        $sql .= ' ORDER BY p.product_title';

        $stmt = $this->conn->prepare($sql);
        if ($types !== '') {
            $stmt->bind_param($types, ...$params);
        }
        $stmt->execute();
        $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        return $rows;
    }

    /** One product with its brand and category names. @return array|false */
    public function getProductById(int $productId)
    {
        $stmt = $this->conn->prepare(
            'SELECT p.*, c.cat_name, b.brand_name
             FROM co_products p
             JOIN co_categories c ON c.cat_id = p.product_cat
             LEFT JOIN co_brands b ON b.brand_id = p.product_brand
             WHERE p.product_id = ?'
        );
        $stmt->bind_param('i', $productId);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        return $row ?: false;
    }

    /**
     * Add a product. Stock always starts at 0. The opening stock is
     * added afterwards with adjustStock(), so it is recorded as a
     * stock movement like every other change.
     * @param array $p  keys match the co_products column names
     * @return int|false  the new product_id, or false if the insert failed
     */
    public function addProduct(array $p)
    {
        try {
            $stmt = $this->conn->prepare(
                'INSERT INTO co_products
                    (product_cat, product_brand, product_title, product_price, product_desc,
                     product_image, product_keywords, product_colour, product_gender,
                     reorder_level, is_service, needs_lenses, is_active)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
            );
            // product_brand, product_image and the text columns may be NULL.
            // bind_param sends a PHP null as SQL NULL.
            $stmt->bind_param(
                'iisdsssssiiii',
                $p['product_cat'], $p['product_brand'], $p['product_title'], $p['product_price'],
                $p['product_desc'], $p['product_image'], $p['product_keywords'], $p['product_colour'],
                $p['product_gender'], $p['reorder_level'], $p['is_service'], $p['needs_lenses'],
                $p['is_active']
            );
            $stmt->execute();
            $newId = $stmt->insert_id;
            $stmt->close();
            return $newId;
        } catch (mysqli_sql_exception $ex) {
            log_error('addProduct failed: ' . $ex->getMessage());
            return false;
        }
    }

    /**
     * Update a product's details. Stock is NOT changed here on purpose:
     * stock only changes through adjustStock(), so every change is recorded.
     */
    public function updateProduct(int $productId, array $p): bool
    {
        try {
            $stmt = $this->conn->prepare(
                'UPDATE co_products
                 SET product_cat = ?, product_brand = ?, product_title = ?, product_price = ?,
                     product_desc = ?, product_image = ?, product_keywords = ?, product_colour = ?,
                     product_gender = ?, reorder_level = ?, is_service = ?, needs_lenses = ?,
                     is_active = ?
                 WHERE product_id = ?'
            );
            $stmt->bind_param(
                'iisdsssssiiiii',
                $p['product_cat'], $p['product_brand'], $p['product_title'], $p['product_price'],
                $p['product_desc'], $p['product_image'], $p['product_keywords'], $p['product_colour'],
                $p['product_gender'], $p['reorder_level'], $p['is_service'], $p['needs_lenses'],
                $p['is_active'], $productId
            );
            $ok = $stmt->execute();
            $stmt->close();
            return $ok;
        } catch (mysqli_sql_exception $ex) {
            log_error('updateProduct failed: ' . $ex->getMessage());
            return false;
        }
    }

    /** Show (1) or hide (0) a product in the shop. */
    public function setProductActive(int $productId, int $isActive): bool
    {
        $stmt = $this->conn->prepare('UPDATE co_products SET is_active = ? WHERE product_id = ?');
        $stmt->bind_param('ii', $isActive, $productId);
        $ok = $stmt->execute();
        $stmt->close();
        return $ok;
    }

    /** Has this product ever been ordered? Ordered products must never be deleted. */
    public function productHasOrders(int $productId): bool
    {
        $stmt = $this->conn->prepare('SELECT COUNT(*) AS total FROM co_orderdetails WHERE product_id = ?');
        $stmt->bind_param('i', $productId);
        $stmt->execute();
        $total = (int) $stmt->get_result()->fetch_assoc()['total'];
        $stmt->close();
        return $total > 0;
    }

    /**
     * Delete a product that has never been ordered.
     * Its stock movements are deleted too, in one transaction, because
     * the foreign key would otherwise stop the delete. (The audit log
     * still keeps a record that the product existed and was deleted.)
     */
    public function deleteProduct(int $productId): bool
    {
        $this->conn->begin_transaction();
        try {
            $stmt = $this->conn->prepare('DELETE FROM co_stock_movements WHERE product_id = ?');
            $stmt->bind_param('i', $productId);
            $stmt->execute();
            $stmt->close();

            $stmt = $this->conn->prepare('DELETE FROM co_products WHERE product_id = ?');
            $stmt->bind_param('i', $productId);
            $stmt->execute();
            $stmt->close();

            $this->conn->commit();
            return true;
        } catch (mysqli_sql_exception $ex) {
            $this->conn->rollback();
            log_error('deleteProduct failed: ' . $ex->getMessage());
            return false;
        }
    }


    // =================================================================
    // STOCK (co_products.product_stock and co_stock_movements)
    // =================================================================

    /**
     * Change a product's stock and record why, as one transaction.
     * Either both the stock and the movement are saved, or neither is.
     * @param int $change  positive = stock in, negative = stock out
     * @return bool  false if the product is missing, the stock would go
     *               below zero, or the database failed
     */
    public function adjustStock(int $productId, int $change, string $reason, ?int $staffId,
                                ?string $note = null, ?int $orderId = null): bool
    {
        $this->conn->begin_transaction();
        try {
            // FOR UPDATE locks the row, so two staff changing the same
            // product at the same moment cannot overwrite each other.
            $stmt = $this->conn->prepare(
                'SELECT product_stock FROM co_products WHERE product_id = ? FOR UPDATE'
            );
            $stmt->bind_param('i', $productId);
            $stmt->execute();
            $row = $stmt->get_result()->fetch_assoc();
            $stmt->close();

            if (!$row || (int) $row['product_stock'] + $change < 0) {
                $this->conn->rollback();
                return false;
            }

            $stmt = $this->conn->prepare(
                'UPDATE co_products SET product_stock = product_stock + ? WHERE product_id = ?'
            );
            $stmt->bind_param('ii', $change, $productId);
            $stmt->execute();
            $stmt->close();

            $stmt = $this->conn->prepare(
                'INSERT INTO co_stock_movements (product_id, change_qty, reason, order_id, staff_id, note)
                 VALUES (?, ?, ?, ?, ?, ?)'
            );
            $stmt->bind_param('iisiis', $productId, $change, $reason, $orderId, $staffId, $note);
            $stmt->execute();
            $stmt->close();

            $this->conn->commit();
            return true;
        } catch (mysqli_sql_exception $ex) {
            $this->conn->rollback();
            log_error('adjustStock failed: ' . $ex->getMessage());
            return false;
        }
    }

    /**
     * Low-stock alert list: active, non-service products at or below
     * their reorder level. (Written as a query because the school
     * server does not allow CREATE VIEW.)
     * @return array
     */
    public function getLowStock(): array
    {
        $result = $this->conn->query(
            'SELECT p.product_id, p.product_title, p.product_stock, p.reorder_level,
                    c.cat_name, b.brand_name
             FROM co_products p
             JOIN co_categories c ON c.cat_id = p.product_cat
             LEFT JOIN co_brands b ON b.brand_id = p.product_brand
             WHERE p.is_service = 0 AND p.is_active = 1
               AND p.product_stock <= p.reorder_level
             ORDER BY p.product_stock, p.product_title'
        );
        return $result->fetch_all(MYSQLI_ASSOC);
    }

    /**
     * Products that hold stock (not services), for the stock form dropdown.
     * @return array
     */
    public function getStockProducts(): array
    {
        $result = $this->conn->query(
            'SELECT product_id, product_title, product_stock
             FROM co_products
             WHERE is_service = 0
             ORDER BY product_title'
        );
        return $result->fetch_all(MYSQLI_ASSOC);
    }

    /**
     * The latest stock changes, newest first, with product and staff names.
     * @return array
     */
    public function getRecentMovements(int $limit = 50): array
    {
        $stmt = $this->conn->prepare(
            'SELECT m.movement_id, m.change_qty, m.reason, m.note, m.created_at, m.order_id,
                    p.product_title, s.staff_fname, s.staff_lname
             FROM co_stock_movements m
             JOIN co_products p ON p.product_id = m.product_id
             LEFT JOIN co_staff s ON s.staff_id = m.staff_id
             ORDER BY m.created_at DESC, m.movement_id DESC
             LIMIT ?'
        );
        $stmt->bind_param('i', $limit);
        $stmt->execute();
        $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        return $rows;
    }


    // =================================================================
    // ONLINE SHOP (what customers see)
    // The shop only shows products that are switched on (is_active = 1)
    // and are not services, such as an eye exam fee.
    // =================================================================

    // The sort orders customers can choose. ORDER BY cannot use a "?"
    // placeholder, so the SQL comes from this fixed list and never from
    // what the visitor typed.
    private const SHOP_SORTS = [
        'newest'     => 'p.date_added DESC, p.product_id DESC',
        'price_low'  => 'p.product_price ASC, p.product_title ASC',
        'price_high' => 'p.product_price DESC, p.product_title ASC',
        'name'       => 'p.product_title ASC',
    ];

    /**
     * Build the WHERE part of a shop query from the filters.
     * Every value goes into $params for bind_param, never into the SQL.
     * @param array  $f       cleaned filters: q, cat, brand, colour, gender, min, max
     * @param string $types   filled in with the bind_param letters
     * @param array  $params  filled in with the values
     */
    private function shopWhere(array $f, string &$types, array &$params): string
    {
        $sql = ' WHERE p.is_active = 1 AND p.is_service = 0';

        if ($f['q'] !== '') {
            // Escape % and _ so they are searched for as normal characters
            $like = '%' . addcslashes($f['q'], '%_\\') . '%';
            $sql .= ' AND (p.product_title LIKE ? OR p.product_keywords LIKE ? OR b.brand_name LIKE ?)';
            $types .= 'sss';
            array_push($params, $like, $like, $like);
        }
        if ($f['cat'] > 0) {
            $sql .= ' AND p.product_cat = ?';
            $types .= 'i';
            $params[] = $f['cat'];
        }
        if ($f['brand'] > 0) {
            $sql .= ' AND p.product_brand = ?';
            $types .= 'i';
            $params[] = $f['brand'];
        }
        if ($f['colour'] !== '') {
            $sql .= ' AND p.product_colour = ?';
            $types .= 's';
            $params[] = $f['colour'];
        }
        if ($f['gender'] !== '') {
            if ($f['gender'] === 'Men' || $f['gender'] === 'Women') {
                // Unisex products suit adults of any gender, so they appear here too
                $sql .= " AND (p.product_gender = ? OR p.product_gender = 'Unisex')";
            } else {
                $sql .= ' AND p.product_gender = ?';
            }
            $types .= 's';
            $params[] = $f['gender'];
        }
        if ($f['min'] !== null) {
            $sql .= ' AND p.product_price >= ?';
            $types .= 'd';
            $params[] = $f['min'];
        }
        if ($f['max'] !== null) {
            $sql .= ' AND p.product_price <= ?';
            $types .= 'd';
            $params[] = $f['max'];
        }
        return $sql;
    }

    /**
     * One page of shop products that match the filters.
     * @return array
     */
    public function getShopProducts(array $filters, string $sort, int $limit, int $offset): array
    {
        $types  = '';
        $params = [];
        $where  = $this->shopWhere($filters, $types, $params);
        $order  = self::SHOP_SORTS[$sort] ?? self::SHOP_SORTS['newest'];

        $sql = 'SELECT p.product_id, p.product_title, p.product_price, p.product_image,
                       p.product_colour, p.product_stock, p.needs_lenses,
                       c.cat_name, b.brand_name
                FROM co_products p
                JOIN co_categories c ON c.cat_id = p.product_cat
                LEFT JOIN co_brands b ON b.brand_id = p.product_brand'
             . $where
             . ' ORDER BY ' . $order
             . ' LIMIT ? OFFSET ?';

        $types .= 'ii';
        $params[] = $limit;
        $params[] = $offset;

        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        return $rows;
    }

    /** How many shop products match the filters (for the page numbers). */
    public function countShopProducts(array $filters): int
    {
        $types  = '';
        $params = [];
        $where  = $this->shopWhere($filters, $types, $params);

        $stmt = $this->conn->prepare(
            'SELECT COUNT(*) AS total
             FROM co_products p
             LEFT JOIN co_brands b ON b.brand_id = p.product_brand' . $where
        );
        if ($types !== '') {
            $stmt->bind_param($types, ...$params);
        }
        $stmt->execute();
        $total = (int) $stmt->get_result()->fetch_assoc()['total'];
        $stmt->close();
        return $total;
    }

    /**
     * One product for the customer product page. Hidden products and
     * services are not returned, so their pages cannot be opened.
     * @return array|false
     */
    public function getShopProductById(int $productId)
    {
        $stmt = $this->conn->prepare(
            'SELECT p.*, c.cat_name, b.brand_name
             FROM co_products p
             JOIN co_categories c ON c.cat_id = p.product_cat
             LEFT JOIN co_brands b ON b.brand_id = p.product_brand
             WHERE p.product_id = ? AND p.is_active = 1 AND p.is_service = 0'
        );
        $stmt->bind_param('i', $productId);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        return $row ?: false;
    }

    /** Other shop products in the same category, for "You may also like". */
    public function getRelatedProducts(int $catId, int $excludeId, int $limit = 4): array
    {
        $stmt = $this->conn->prepare(
            'SELECT p.product_id, p.product_title, p.product_price, p.product_image,
                    p.product_colour, p.product_stock, p.needs_lenses,
                    c.cat_name, b.brand_name
             FROM co_products p
             JOIN co_categories c ON c.cat_id = p.product_cat
             LEFT JOIN co_brands b ON b.brand_id = p.product_brand
             WHERE p.product_cat = ? AND p.product_id <> ?
               AND p.is_active = 1 AND p.is_service = 0
             ORDER BY p.date_added DESC
             LIMIT ?'
        );
        $stmt->bind_param('iii', $catId, $excludeId, $limit);
        $stmt->execute();
        $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        return $rows;
    }

    /**
     * Categories that have at least one shop product, for the filters
     * and the Shop menu. Empty categories are left out.
     */
    public function getShopCategories(): array
    {
        $result = $this->conn->query(
            'SELECT c.cat_id, c.cat_name, COUNT(*) AS product_count
             FROM co_categories c
             JOIN co_products p ON p.product_cat = c.cat_id
             WHERE p.is_active = 1 AND p.is_service = 0
             GROUP BY c.cat_id, c.cat_name
             ORDER BY c.cat_name'
        );
        return $result->fetch_all(MYSQLI_ASSOC);
    }

    /** Brands that have at least one shop product. */
    public function getShopBrands(): array
    {
        $result = $this->conn->query(
            'SELECT b.brand_id, b.brand_name, COUNT(*) AS product_count
             FROM co_brands b
             JOIN co_products p ON p.product_brand = b.brand_id
             WHERE p.is_active = 1 AND p.is_service = 0
             GROUP BY b.brand_id, b.brand_name
             ORDER BY b.brand_name'
        );
        return $result->fetch_all(MYSQLI_ASSOC);
    }

    /** Every colour used by a shop product, for the colour filter. */
    public function getShopColours(): array
    {
        $result = $this->conn->query(
            "SELECT DISTINCT product_colour
             FROM co_products
             WHERE is_active = 1 AND is_service = 0
               AND product_colour IS NOT NULL AND product_colour <> ''
             ORDER BY product_colour"
        );
        return array_column($result->fetch_all(MYSQLI_ASSOC), 'product_colour');
    }


    // =================================================================
    // LENS OPTIONS (co_lens_options)
    // Lens types and coatings chosen when ordering prescription glasses.
    // There is no delete: old orders and carts point to these rows, so
    // an option that is no longer offered is switched off instead.
    // =================================================================

    /** @return array */
    public function getAllLensOptions(): array
    {
        $result = $this->conn->query(
            'SELECT lens_id, lens_name, option_group, lens_price, lens_desc, is_active
             FROM co_lens_options
             ORDER BY option_group, lens_name'
        );
        return $result->fetch_all(MYSQLI_ASSOC);
    }

    /** @return array|false */
    public function getLensById(int $lensId)
    {
        $stmt = $this->conn->prepare('SELECT * FROM co_lens_options WHERE lens_id = ?');
        $stmt->bind_param('i', $lensId);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        return $row ?: false;
    }

    /** Is this lens name already used by another option? */
    public function lensNameExists(string $name, int $ignoreId = 0): bool
    {
        $stmt = $this->conn->prepare('SELECT lens_id FROM co_lens_options WHERE lens_name = ? AND lens_id <> ?');
        $stmt->bind_param('si', $name, $ignoreId);
        $stmt->execute();
        $stmt->store_result();
        $exists = $stmt->num_rows > 0;
        $stmt->close();
        return $exists;
    }

    /** @return int|false  the new lens_id, or false if the insert failed */
    public function addLensOption(string $name, string $group, float $price, ?string $desc)
    {
        try {
            $stmt = $this->conn->prepare(
                'INSERT INTO co_lens_options (lens_name, option_group, lens_price, lens_desc)
                 VALUES (?, ?, ?, ?)'
            );
            $stmt->bind_param('ssds', $name, $group, $price, $desc);
            $stmt->execute();
            $newId = $stmt->insert_id;
            $stmt->close();
            return $newId;
        } catch (mysqli_sql_exception $ex) {
            log_error('addLensOption failed: ' . $ex->getMessage());
            return false;
        }
    }

    public function updateLensOption(int $lensId, string $name, string $group, float $price,
                                     ?string $desc, int $isActive): bool
    {
        try {
            $stmt = $this->conn->prepare(
                'UPDATE co_lens_options
                 SET lens_name = ?, option_group = ?, lens_price = ?, lens_desc = ?, is_active = ?
                 WHERE lens_id = ?'
            );
            $stmt->bind_param('ssdsii', $name, $group, $price, $desc, $isActive, $lensId);
            $ok = $stmt->execute();
            $stmt->close();
            return $ok;
        } catch (mysqli_sql_exception $ex) {
            log_error('updateLensOption failed: ' . $ex->getMessage());
            return false;
        }
    }
}
