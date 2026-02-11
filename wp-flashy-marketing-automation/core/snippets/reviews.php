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

	$days = 60 * 60 * 24 * 7;

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
 * Inject aggregateRating into WooCommerce's existing Product structured data.
 * This avoids duplicate Product JSON-LD when WooCommerce (or an SEO plugin) already outputs one.
 */
function flashy_inject_review_into_wc_structured_data($markup, $product)
{
    if (flashy_settings('reviews_snippet') !== 'yes') {
        return $markup;
    }

    $reviews = flashy_get_product_reviews($product->get_id());

    if (!$reviews || empty($reviews['total_reviews']) || $reviews['total_reviews'] < 1) {
        return $markup;
    }

    $markup['aggregateRating'] = [
        '@type' => 'AggregateRating',
        'ratingValue' => number_format($reviews['avg_rating'], 1),
        'reviewCount' => $reviews['total_reviews'],
    ];

    $GLOBALS['flashy_review_snippet_injected'] = true;

    return $markup;
}

add_filter('woocommerce_structured_data_product', 'flashy_inject_review_into_wc_structured_data', 10, 2);

/**
 * Fallback: Output standalone Product JSON-LD only if WooCommerce's structured data
 * is not active (e.g., disabled by an SEO plugin). Runs on wp_footer to ensure
 * the WooCommerce filter had a chance to fire first.
 */
function flashy_product_review_snippet_fallback()
{
    if (!empty($GLOBALS['flashy_review_snippet_injected'])) {
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
