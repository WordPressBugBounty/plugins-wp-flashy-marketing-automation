<?php

use Flashy\Helper;

class Flashy_Export extends WP_Background_Process {

	/**
	 * @var string
	 */
	protected $action = 'flashy_export';

	public $api;

	/**
	 * Task
	 *
	 * Override this method to perform any actions required on each
	 * queue item. Return the modified item for further processing
	 * in the next pass through. Or, return false to remove the
	 * item from the queue.
	 *
	 * @param mixed $item Queue item to iterate over
	 *
	 * @return mixed
	 */
	protected function task($item)
	{
        $path = apply_filters('flashy/get_info', 'path');

        require_once($path . 'core/Flashy.php');

        if(get_option("flashy_key") !== false && get_option("flashy_catalog") !== false)
		{
			$this->api = new Flashy(get_option("flashy_key"));

			$catalog = intval(get_option("flashy_catalog"));
		}
		else return false;

		$products = $this->getProducts();

        Helper::tryOrLog( function () use ($products, $catalog) {
            $this->api->import->products($products, $catalog);
        });


		return false;
	}

	public function getProducts($posts_per_page = -1, $page = 1)
	{
        $offset = ($page - 1) * $posts_per_page;

        $products_obj = new Flashy_Products_Feed();

        $products = $products_obj->get($posts_per_page, $offset);

		$clean_products = $this->cleanProducts($products);
        
		return ['products' => $clean_products, 'total' => $products['total'], 'current_page' => (int) $_GET['page']];
	}

	/**
	 * Clean duplicate products
	 * @param  array $products
	 * @return array
	 */
	public function cleanProducts($products)
	{
		$data = array();

		foreach ($products['products'] as $product)
		{
			if(!isset($data[$product['id']]))
			{
				$data[$product['id']] = [
					"id" => $product['id'],
					"title" => $product['title'],
					"link" => $product['link'],
					"image_link" => $product['feature_image'],
					"availability" => $product['availability'],
					"description" => trim(preg_replace('/\s+/', ' ', strip_tags($product['description']))),
					"price" => $product['price'],
					"sale_price" => $product['sale_price'],
					"condition" => $product['condition'],
					"product_type" => $product['product_type'],
					"quantity" => $product['quantity'],
                    "variant" => $product['variation_type'] !== "variation" ? 0 : 1,
                    "parent_id" => $product['parent_id'] !== $product['id'] ? $product['parent_id'] : 0,
                    "tags" => $product['tags'],
                    "sku" => $product['sku'],
                    "created_at" => $product['created_at'],
                    "updated_at" => $product['updated_at']
				];

                if( isset($product['variants']) )
                {
                    $data[$product['id']]['variants'] = $product['variants'];
                }

                if( isset($product['extra_images']) )
                {
                    $data[$product['id']]['extra_images'] = $product['extra_images'];
                }
			}
		}

		return $data;
	}

	public function getCustomers($customers_per_page, $page)
	{
	    $offset = ($page - 1) * $customers_per_page;

		$customers = [];

		$user_query = new WP_User_Query(array(
			'role' => 'customer',
			'number' => $customers_per_page,
			'offset' => $offset
		));

		$all_customers = $user_query->get_results();

		foreach ($all_customers as $customer)
		{
			$customers[] = array_filter(array(
				"email" => strtolower($customer->user_email),
				"first_name" => get_user_meta($customer->ID, 'billing_first_name', true),
				"last_name" => get_user_meta($customer->ID, 'billing_last_name', true),
				"city" => get_user_meta($customer->ID, 'billing_city', true),
				"country" => get_user_meta($customer->ID, 'billing_country', true),
				"state" => get_user_meta($customer->ID, 'billing_state', true),
				"phone" => get_user_meta($customer->ID, 'billing_phone', true),
			));
		}

        return ['customers' => $customers, 'total' => $user_query->get_total(), 'current_page' => count($customers)];
	}

    public function getGuests($guests_per_page, $page)
	{
        global $wpdb;

	    $offset = ($page - 1) * $guests_per_page;

		$guests = [];

        $guest_table = $wpdb->prefix . "wc_customer_lookup";

        $guest_order = $wpdb->prefix . "wc_order_stats";

        $count_rows = $wpdb->get_results("SELECT {$guest_table}.customer_id, {$guest_order}.order_id FROM {$guest_table} JOIN {$guest_order} ON {$guest_table}.customer_id = {$guest_order}.customer_id WHERE {$guest_table}.user_id IS NULL GROUP BY {$guest_table}.customer_id");

		$all_guests = $wpdb->get_results("SELECT {$guest_table}.customer_id, {$guest_order}.order_id FROM {$guest_table} JOIN {$guest_order} ON {$guest_table}.customer_id = {$guest_order}.customer_id WHERE {$guest_table}.user_id IS NULL GROUP BY {$guest_table}.customer_id LIMIT $offset,$guests_per_page");

        if ( $wpdb->last_error )
            return $wpdb->print_error();

		foreach ($all_guests as $guest)
		{
            $order = wc_get_order($guest->order_id);

			$guests[] = array_filter(array(
                "guest_id" => $guest->customer_id,
				"email" => strtolower($order->get_billing_email()),
				"first_name" => $order->get_billing_first_name(),
				"last_name" => $order->get_billing_last_name(),
				"city" => $order->get_billing_city(),
				"country" => $order->get_billing_country(),
				"state" => $order->get_billing_state(),
				"phone" => $order->get_billing_phone(),
			));
		}

        return ['guests' => $guests, 'total' => count($count_rows), 'current_page' => count($guests)];
	}

	public function getOrders($orders_per_page, $page)
	{
        $offset = ($page - 1) * $orders_per_page;

		$args = array(
			'status' => ['wc-completed', 'wc-processing'],
			'limit' => $orders_per_page,
			'return' => 'ids',
			'paginate' => true,
			'offset' => $offset
		);

        $total_orders = wc_get_orders([
            'status' => ['wc-completed', 'wc-processing'],
            'return' => 'ids',
            'paginate' => true,
        ]);

        $orders = wc_get_orders($args);

        $orders_data = [];

        foreach ($orders->orders as $order_id) {
            $order = wc_get_order($order_id);
            $order_data = $order->get_data();
            $items = $order->get_items();
            $content_ids = [];
            $data = [];

            foreach ($items as $item)
            {
                $content_ids[] = $item['product_id'];
            }
            if($order_data['date_created'])
            {
                $data = [
                    'order_id'		=> strval($order_id),
                    'email'			=> strtolower($order_data['billing']['email']),
                    'value'			=> $order->get_subtotal(),
                    'currency' 		=> $order_data['currency'],
                    'content_ids'	=> implode(",", $content_ids),
                    'date'			=> $order_data['date_created']->getTimestamp(),
                ];
            }

            $orders_data[] = $data;
        }

		return ['orders' => $orders_data, 'total' => $total_orders->total, 'current_page' => count($orders_data)];
	}

    /**
     * Create coupon by merged $args and $default arrays
     * @param  array $args
     */
    public function createCoupon($args = array())
    {
        //if wc points and rewards is installed, remove filter get shop
        if( is_plugin_active('woocommerce-points-and-rewards/woocommerce-points-and-rewards.php') )
        {
            add_filter( 'woocommerce_get_shop_coupon_data', '__return_false', 101 );
        }

        $prefix = ( isset($args['prefix']) ) ? $args['prefix'] : "";

        $code = $this->getUniqueCode($prefix);

        $default = array(
            'coupon_code' => $code, // coupon string title.
            'discount_type' => 'fixed_cart', // type: fixed_cart, percent, fixed_product, percent_product.
            'amount' => 0, //amount ?? string
            'usage_limit' => 1, // total usage ?? string
            'usage_limit_per_user' => 1, // total single user usage ?? string
            'expiry_date' => date('Y-m-d', strtotime('+371 days')), // date type example -> '25.05.21'
            'free_shipping' => false, // bool
            'product_ids' => null, // array of products id's

            // Only exists in WP, for now we won't use them.
            'individual_use' => false, // bool
            'exclude_product_ids' => '', // array of products id's
            'minimum_amount' => '', // min sum of cart price
            'maximum_amount' => '', // max sum of cart price
            'product_categories' => '', // array of categories id's
            'exclude_product_categories' => '', // array of categories id's
            'exclude_sale_items' => true, // bool,
            'description' => ''
        );

        $merged = array_merge( $default, $args );

        $coupon = new WC_Coupon();

        // Add meta to coupon by ID
        if( isset($merged['personalization']) ){
            $merged['coupon_code'] = $merged['personalization'] . '_' . $merged['coupon_code'];
        }
        $coupon->set_code( $merged['coupon_code'] );
        $coupon->set_discount_type( $merged['discount_type'] );
        $coupon->set_amount( $merged['amount'] );
        $coupon->set_date_expires( $merged['expiry_date'] );
        $coupon->set_usage_limit( $merged['usage_limit'] );
        $coupon->set_usage_limit_per_user( $merged['usage_limit_per_user'] );
        $coupon->set_individual_use( $merged['individual_use'] );
        $coupon->set_product_ids( $merged['product_ids'] );
        $coupon->set_excluded_product_ids( $merged['exclude_product_ids'] );
        $coupon->set_free_shipping( $merged['free_shipping'] );
        $coupon->set_minimum_amount( $merged['minimum_amount'] );
        $coupon->set_maximum_amount( $merged['maximum_amount'] );
        $coupon->set_excluded_product_categories( $merged['exclude_product_categories'] );
        $coupon->set_product_categories( $merged['product_categories'] );
        $coupon->set_exclude_sale_items( $merged['exclude_sale_items'] );
        $coupon->set_description( $merged['description'] );

        // Create, publish and save coupon (data)
        $coupon->save();

        return array(
            "data" => $merged['coupon_code'],
            "success" => true
        );
    }

    public function getProductCategories()
	{
		$cats = get_terms('product_cat', array(
            'orderby' => 'name',
            'order' => 'ASC',
            'hide_empty' => false,
        ));

		$response = [];

		foreach( $cats as $cat )
		{
			$response[] = [
				"value" => $cat->term_id,
				"title" => $cat->name,
			];
		}

        return $response;
    }

	/**
	 * Complete
	 *
	 * Override if applicable, but ensure that the below actions are
	 * performed, or, call parent::complete().
	 */
	protected function complete() {
		parent::complete();
	}

    private function getUniqueCode($prefix)
    {
        $code = $prefix . wp_generate_password(6, false);

        if( !empty(wc_get_coupon_id_by_code($code)) )
        {
            return $this->getUniqueCode($prefix);
        }

        return $code;
    }
}
