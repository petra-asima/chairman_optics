<?php
// The online shop: every product customers can buy, with search,
// filters (category, brand, colour, made for, price), sorting and pages.
// All filters are in the address bar (GET), so a filtered page can be
// bookmarked or shared, and the Back button works as expected.
require_once __DIR__ . '/../core/core.php';
require_once __DIR__ . '/../controllers/ProductController.php';

$controller = new ProductController();
$filters    = $controller->cleanShopFilters($_GET);
$result     = $controller->getShopPage($filters);

$categories = $controller->getShopCategories();
$brands     = $controller->getShopBrands();
$colours    = $controller->getShopColours();

// Names for the chosen category and brand (for the heading and the chips)
$catName = '';
foreach ($categories as $cat) {
    if ((int) $cat['cat_id'] === $filters['cat']) {
        $catName = $cat['cat_name'];
    }
}
$brandName = '';
foreach ($brands as $brand) {
    if ((int) $brand['brand_id'] === $filters['brand']) {
        $brandName = $brand['brand_name'];
    }
}

/**
 * The address of this page with some filters changed.
 * Empty filters are left out, so the address stays short.
 * Example: $shopUrl(['page' => 2]) or $shopUrl(['brand' => 0]) to remove a filter.
 */
$shopUrl = function (array $changes = []) use ($filters): string {
    $query = array_merge($filters, $changes);
    if (!isset($changes['page'])) {
        $query['page'] = 1; // a new filter starts again at page 1
    }
    $query = array_filter($query, function ($value, $key) {
        if ($key === 'sort') {
            return $value !== 'newest';
        }
        if ($key === 'page') {
            return $value > 1;
        }
        return $value !== '' && $value !== 0 && $value !== null;
    }, ARRAY_FILTER_USE_BOTH);

    $base = url('views/all_products.php');
    return $query ? $base . '?' . http_build_query($query) : $base;
};

// The filters that are switched on, shown as removable "chips"
$chips = [];
if ($filters['q'] !== '')      { $chips[] = ['Search: "' . $filters['q'] . '"', ['q' => '']]; }
if ($catName !== '')           { $chips[] = [$catName, ['cat' => 0]]; }
if ($brandName !== '')         { $chips[] = [$brandName, ['brand' => 0]]; }
if ($filters['colour'] !== '') { $chips[] = [$filters['colour'], ['colour' => '']]; }
if ($filters['gender'] !== '') { $chips[] = ['For ' . $filters['gender'], ['gender' => '']]; }
if ($filters['min'] !== null)  { $chips[] = ['From ' . money($filters['min']), ['min' => null]]; }
if ($filters['max'] !== null)  { $chips[] = ['Up to ' . money($filters['max']), ['max' => null]]; }

$pageTitle = $catName !== '' ? $catName : 'Shop';
require __DIR__ . '/layout/header.php';
?>

<section class="shop-banner">
    <div class="container">
        <h1><?= e($pageTitle) ?></h1>
        <p>Frames, sunglasses, contact lenses and eye care products from <?= e(SITE_NAME) ?>.</p>
    </div>
</section>

<div class="container shop-layout">

    <!-- ---------- Filters ---------- -->
    <aside class="shop-filters">
        <details class="filter-box" open>
            <summary>
                <span class="material-symbols-outlined" aria-hidden="true">tune</span> Filter products
            </summary>

            <form method="GET" action="<?= url('views/all_products.php') ?>" id="shop-filters">
                <div class="form-group">
                    <label for="q">Search</label>
                    <input type="search" id="q" name="q" value="<?= e($filters['q']) ?>"
                           maxlength="100" placeholder="Name, brand or keyword">
                </div>

                <div class="form-group">
                    <label for="cat">Type</label>
                    <select id="cat" name="cat" class="js-auto-submit">
                        <option value="">All types</option>
                        <?php foreach ($categories as $cat): ?>
                            <option value="<?= (int) $cat['cat_id'] ?>" <?= $filters['cat'] === (int) $cat['cat_id'] ? 'selected' : '' ?>>
                                <?= e($cat['cat_name']) ?> (<?= (int) $cat['product_count'] ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label for="brand">Brand</label>
                    <select id="brand" name="brand" class="js-auto-submit">
                        <option value="">All brands</option>
                        <?php foreach ($brands as $brand): ?>
                            <option value="<?= (int) $brand['brand_id'] ?>" <?= $filters['brand'] === (int) $brand['brand_id'] ? 'selected' : '' ?>>
                                <?= e($brand['brand_name']) ?> (<?= (int) $brand['product_count'] ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <?php if (!empty($colours)): ?>
                    <div class="form-group">
                        <label for="colour">Colour</label>
                        <select id="colour" name="colour" class="js-auto-submit">
                            <option value="">Any colour</option>
                            <?php foreach ($colours as $colour): ?>
                                <option value="<?= e($colour) ?>" <?= $filters['colour'] === $colour ? 'selected' : '' ?>>
                                    <?= e($colour) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                <?php endif; ?>

                <div class="form-group">
                    <label for="gender">Made for</label>
                    <select id="gender" name="gender" class="js-auto-submit">
                        <option value="">Everyone</option>
                        <?php foreach (ProductController::PRODUCT_GENDERS as $gender): ?>
                            <option value="<?= e($gender) ?>" <?= $filters['gender'] === $gender ? 'selected' : '' ?>>
                                <?= e($gender) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <fieldset class="form-group price-range">
                    <legend>Price (GH₵)</legend>
                    <div class="price-inputs">
                        <label for="min" class="visually-hidden">Lowest price</label>
                        <input type="number" id="min" name="min" min="0" step="1" placeholder="From"
                               value="<?= $filters['min'] !== null ? e($filters['min']) : '' ?>">
                        <span aria-hidden="true">&ndash;</span>
                        <label for="max" class="visually-hidden">Highest price</label>
                        <input type="number" id="max" name="max" min="0" step="1" placeholder="To"
                               value="<?= $filters['max'] !== null ? e($filters['max']) : '' ?>">
                    </div>
                </fieldset>

                <!-- Keep the chosen sort order when filters change -->
                <input type="hidden" name="sort" value="<?= e($filters['sort']) ?>">

                <button type="submit" class="btn btn-primary btn-block">Show Products</button>
                <?php if (!empty($chips)): ?>
                    <a href="<?= url('views/all_products.php') ?>" class="filter-clear">Clear all filters</a>
                <?php endif; ?>
            </form>
        </details>
    </aside>

    <!-- ---------- Results ---------- -->
    <section class="shop-results" aria-labelledby="results-heading">
        <div class="shop-toolbar">
            <h2 id="results-heading" class="shop-count">
                <?= $result['total'] ?> product<?= $result['total'] === 1 ? '' : 's' ?>
            </h2>

            <!-- Sorting is a separate small form so it works without JavaScript too -->
            <form method="GET" action="<?= url('views/all_products.php') ?>" class="shop-sort">
                <?php foreach (['q', 'cat', 'brand', 'colour', 'gender', 'min', 'max'] as $key): ?>
                    <?php if ($filters[$key] !== '' && $filters[$key] !== 0 && $filters[$key] !== null): ?>
                        <input type="hidden" name="<?= $key ?>" value="<?= e($filters[$key]) ?>">
                    <?php endif; ?>
                <?php endforeach; ?>
                <label for="sort">Sort by</label>
                <select id="sort" name="sort" class="js-auto-submit">
                    <?php foreach (ProductController::SHOP_SORT_LABELS as $value => $label): ?>
                        <option value="<?= e($value) ?>" <?= $filters['sort'] === $value ? 'selected' : '' ?>><?= e($label) ?></option>
                    <?php endforeach; ?>
                </select>
                <noscript><button type="submit" class="btn btn-light">Sort</button></noscript>
            </form>
        </div>

        <?php if (!empty($chips)): ?>
            <ul class="filter-chips" aria-label="Filters in use">
                <?php foreach ($chips as [$label, $remove]): ?>
                    <li>
                        <a href="<?= e($shopUrl($remove)) ?>" class="filter-chip">
                            <?= e($label) ?>
                            <span class="material-symbols-outlined" aria-hidden="true">close</span>
                            <span class="visually-hidden">(remove this filter)</span>
                        </a>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>

        <?php if (empty($result['products'])): ?>
            <div class="shop-empty">
                <span class="material-symbols-outlined" aria-hidden="true">search_off</span>
                <?php if (!empty($chips)): ?>
                    <h3>No products match these filters</h3>
                    <p>Try removing a filter, or <a href="<?= url('views/all_products.php') ?>">see all products</a>.</p>
                <?php else: ?>
                    <h3>New products are on the way</h3>
                    <p>Our online shop is being stocked. Please check back soon, or visit us at <?= e(SITE_ADDRESS) ?>.</p>
                <?php endif; ?>
            </div>
        <?php else: ?>
            <?php
            $gridProducts = $result['products'];
            require __DIR__ . '/layout/product_grid.php';
            ?>

            <?php if ($result['pages'] > 1): ?>
                <nav class="pagination" aria-label="Pages">
                    <?php if ($result['page'] > 1): ?>
                        <a href="<?= e($shopUrl(['page' => $result['page'] - 1])) ?>" rel="prev">&larr; Previous</a>
                    <?php endif; ?>

                    <?php for ($i = 1; $i <= $result['pages']; $i++): ?>
                        <?php if ($i === $result['page']): ?>
                            <span class="current" aria-current="page"><?= $i ?></span>
                        <?php else: ?>
                            <a href="<?= e($shopUrl(['page' => $i])) ?>"><?= $i ?></a>
                        <?php endif; ?>
                    <?php endfor; ?>

                    <?php if ($result['page'] < $result['pages']): ?>
                        <a href="<?= e($shopUrl(['page' => $result['page'] + 1])) ?>" rel="next">Next &rarr;</a>
                    <?php endif; ?>
                </nav>
            <?php endif; ?>
        <?php endif; ?>
    </section>
</div>

<script src="<?= url('js/filters.js') ?>"></script>
<?php require __DIR__ . '/layout/footer.php'; ?>
