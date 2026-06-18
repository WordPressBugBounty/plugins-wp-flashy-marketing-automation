<?php

use Flashy\Helper;

/**
 * Get product reviews data with 3-day caching.
 *
 * @param int $product_id
 * @return array|null Array with 'total_reviews' and 'avg_rating', or null on failure.
 */
function flashy_get_product_reviews($product_id)
{
    $cached_data = get_post_meta($product_id, '_flashy_reviews_data', true);
    $cached_time = get_post_meta($product_id, '_flashy_reviews_updated', true);

	$days = 60 * 60 * 24 * 3;

    if (is_array($cached_data) && $cached_time && (time() - $cached_time) < $days) {
        return $cached_data;
    }

    $review_data = Helper::tryOrLog(function () use ($product_id) {
        $response = flashy()->api->products->reviews($product_id);

        if ($response && $response->success()) {
            $data = $response->getData();

            return [
                'total_reviews' => isset($data['total']) ? (int) $data['total'] : 0,
                'avg_rating'    => isset($data['stats']['avg']) ? (float) $data['stats']['avg'] : 0,
            ];
        }

        return null;
    });

    if (is_array($review_data)) {
        update_post_meta($product_id, '_flashy_reviews_data', $review_data);
        update_post_meta($product_id, '_flashy_reviews_updated', time());

        return $review_data;
    }

    // Return stale cache if API failed
    if (is_array($cached_data)) {
        return $cached_data;
    }

    return null;
}

/**
 * Build the aggregateRating node from cached review data for a product.
 *
 * @param WC_Product|int $product
 * @return array|null aggregateRating array, or null when there is nothing to inject.
 */
function flashy_build_aggregate_rating($product)
{
    if (flashy_settings('reviews_snippet') !== 'yes') {
        return null;
    }

    $product_id = is_object($product) ? $product->get_id() : (int) $product;

    if (!$product_id) {
        return null;
    }

    $reviews = flashy_get_product_reviews($product_id);

    if (!$reviews || empty($reviews['total_reviews']) || $reviews['total_reviews'] < 1) {
        return null;
    }

    return [
        '@type' => 'AggregateRating',
        'ratingValue' => number_format($reviews['avg_rating'], 1),
        'reviewCount' => $reviews['total_reviews'],
    ];
}

/**
 * Inject aggregateRating into WooCommerce's existing Product structured data.
 * This avoids duplicate Product JSON-LD when WooCommerce already outputs one.
 *
 * Note: we intentionally do NOT mark the snippet as "injected" here. This filter
 * fires when WooCommerce *generates* the markup, but an SEO plugin (e.g. Rank Math)
 * may later unhook WooCommerce's wp_footer output, so the markup is never printed.
 * The fallback guard checks for actual output instead — see
 * flashy_product_schema_handled_elsewhere().
 */
function flashy_inject_review_into_wc_structured_data($markup, $product)
{
    $rating = flashy_build_aggregate_rating($product);

    if ($rating) {
        $markup['aggregateRating'] = $rating;
    }

    return $markup;
}

add_filter('woocommerce_structured_data_product', 'flashy_inject_review_into_wc_structured_data', 10, 2);

/**
 * Inject aggregateRating into Rank Math's Product schema.
 *
 * When Rank Math is active it replaces WooCommerce's structured data with its own
 * Product schema, so we must add our rating to that entity. Setting the injected
 * flag here is safe because this filter only runs while Rank Math is actually
 * outputting the schema.
 *
 * @param array $entity Rank Math Product schema entity.
 * @return array
 */
function flashy_inject_review_into_rankmath_product($entity)
{
    if (!is_array($entity)) {
        return $entity;
    }

    $product = function_exists('wc_get_product') ? wc_get_product(get_the_ID()) : null;

    if (!$product) {
        return $entity;
    }

    $rating = flashy_build_aggregate_rating($product);

    if ($rating) {
        $entity['aggregateRating'] = $rating;
        $GLOBALS['flashy_review_snippet_injected'] = true;
    }

    return $entity;
}

add_filter('rank_math/snippet/rich_snippet_product_entity', 'flashy_inject_review_into_rankmath_product', 99, 1);

/**
 * Determine whether a Product JSON-LD carrying our rating will actually be printed
 * by someone else, so the fallback should stay quiet to avoid duplicates.
 *
 * @return bool
 */
function flashy_product_schema_handled_elsewhere()
{
    // Rank Math (or another plugin exposing the same filter) already received our rating.
    if (!empty($GLOBALS['flashy_review_snippet_injected'])) {
        return true;
    }

    // WooCommerce's own footer output is still hooked, so it will print the Product
    // JSON-LD (with the rating we injected via woocommerce_structured_data_product).
    if (function_exists('WC') && isset(WC()->structured_data)
        && has_action('wp_footer', array(WC()->structured_data, 'output_structured_data'))) {
        return true;
    }

    return false;
}

/**
 * Fallback: Output standalone Product JSON-LD only if no other plugin is going to
 * print a Product schema carrying our rating (e.g., an SEO plugin disabled
 * WooCommerce's structured data and exposes no schema of its own). Runs late on
 * wp_footer so the WooCommerce/Rank Math hooks have already had their chance.
 */
function flashy_product_review_snippet_fallback()
{
    if (flashy_product_schema_handled_elsewhere()) {
        return;
    }

    if (!function_exists('is_product') || !is_product()) {
        return;
    }

    if (flashy_settings('reviews_snippet') !== 'yes') {
        return;
    }

    $product = wc_get_product(get_the_ID());

    if (!$product) {
        return;
    }

    $reviews = flashy_get_product_reviews($product->get_id());

    if (!$reviews || empty($reviews['total_reviews']) || $reviews['total_reviews'] < 1) {
        return;
    }

    $image_id  = $product->get_image_id();
    $image_url = $image_id ? wp_get_attachment_url($image_id) : '';

    $structured_data = [
        '@context' => 'https://schema.org',
        '@type' => 'Product',
        'name' => $product->get_name(),
        'image' => $image_url,
        'description' => $product->get_short_description(),
        'sku' => $product->get_sku(),
        'aggregateRating' => [
            '@type' => 'AggregateRating',
            'ratingValue' => number_format($reviews['avg_rating'], 1),
            'reviewCount' => $reviews['total_reviews'],
        ],
    ];

    echo '<script type="application/ld+json">' . wp_json_encode($structured_data, JSON_UNESCAPED_SLASHES) . '</script>' . "\n";
}

add_action('wp_footer', 'flashy_product_review_snippet_fallback', 30);

/**
 * Display star rating and review count on category/shop pages.
 */
function flashy_category_reviews_display()
{
    if (flashy_settings('reviews_category') !== 'yes') {
        return;
    }

    $product_id = get_the_ID();

    if (!$product_id) {
        return;
    }

    $reviews = flashy_get_product_reviews($product_id);

    if (!$reviews) {
        return;
    }

    $full_stars  = (int) round($reviews['avg_rating']);
    $empty_stars = 5 - $full_stars;

    $stars_html = str_repeat('<span class="flashy-star flashy-star-full">&#9733;</span>', $full_stars);
    $stars_html .= str_repeat('<span class="flashy-star flashy-star-empty">&#9734;</span>', $empty_stars);

    echo '<div class="flashy-reviews-category">';
    echo '<style>.flashy-reviews-category{font-size:14px;line-height:1.4;margin:4px 0;}.flashy-star{color:#f0c14b;font-size:16px;}.flashy-star-empty{color:#ccc;}.flashy-star-half{background:linear-gradient(90deg,#f0c14b 50%,#ccc 50%);-webkit-background-clip:text;-webkit-text-fill-color:transparent;}.flashy-reviews-count{color:#666;font-size:13px;margin-left:4px;}</style>';
    echo '<span class="flashy-stars">' . $stars_html . '</span>';
    echo '<span class="flashy-reviews-count">(' . esc_html($reviews['total_reviews']) . ')</span>';
    echo '</div>';
}

// Register the category reviews display hook based on saved setting
$flashy_reviews_position = flashy_settings('reviews_category_position') ?: 'woocommerce_after_shop_loop_item_title';

$flashy_reviews_priority = 15;

if ($flashy_reviews_position === 'woocommerce_after_shop_loop_item_title') {
    $flashy_reviews_priority = 7;
}

add_action($flashy_reviews_position, 'flashy_category_reviews_display', $flashy_reviews_priority);
