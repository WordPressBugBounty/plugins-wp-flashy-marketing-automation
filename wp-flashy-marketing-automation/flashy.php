<?php
/*
Plugin Name: Wp Flashy Marketing Automation
Plugin URI: https://flashy.app
Description: Wordpress plugin for flashy.app to sync products, orders and customers and track events.
Version: 2.0.8
Author: Flashy
Author URI: https://flashy.app
License: GPL
Copyright: flashy.app
*/

use Flashy\Helper;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

if( !class_exists('wp_flashy') ):

class wp_flashy
{
	private $wpdb;

	private $flashy_carts;

	// vars
	var $settings;

	var $api;

	var $export;

	var $actions = [];
	
	var $hooks = [];

	/*
	*  Constructor
	*
	*  This function will construct all the necessary actions, filters and functions for the flashy plugin to work
	*
	*  @type	function
	*  @date	03/12/2015
	*  @since	1.0.0
	*
	*  @param	N/A
	*  @return	N/A
	*/

	function __construct()
	{
		global $wpdb;

        $this->wpdb = $wpdb;

		$this->flashy_carts = $this->wpdb->prefix . 'flashy_carts';

		// helpers
		add_filter('flashy/helpers/get_path', array($this, 'helpers_get_path'), 1, 1);
		add_filter('flashy/helpers/get_dir', array($this, 'helpers_get_dir'), 1, 1);

		// vars
		$this->settings = array(
			'path'				=> apply_filters('flashy/helpers/get_path', __FILE__),
			'dir'				=> apply_filters('flashy/helpers/get_dir', __FILE__),
			'hook'				=> basename( dirname( __FILE__ ) ) . '/' . basename( __FILE__ ),
			'version'			=> '1.0.0',
		);

		// actions
		add_action('init', array($this, 'init'), 1);

		// filters
		add_filter('flashy/get_info', array($this, 'get_info'), 1, 1);

		// includes
		$this->include_before_theme();

		add_action('after_setup_theme', array($this, 'include_after_theme'), 1);

		add_action('woocommerce_order_status_changed', array($this, 'order_status_changed'), 10, 4);

        add_action('handle_bulk_actions-edit-shop_order', array($this, 'handle_order_bulk_changes'), 10, 3);
	}

	/*
	*  helpers_get_path
	*
	*  This function will calculate the path to a file
	*
	*  @type	function
	*  @date	03/12/2015
	*  @since	1.0.0
	*
	*  @param	$file (file) a reference to the file
	*  @return	(string)
	*/

    function helpers_get_path( $file )
    {
        return trailingslashit(dirname($file));
    }

    /*
	*  helpers_get_dir
	*
	*  This function will calculate the directory (URL) to a file
	*
	*  @type	function
	*  @date	03/12/2015
	*  @since	1.0.0
	*
	*  @param	$file (file) a reference to the file
	*  @return	(string)
	*/

    function helpers_get_dir( $file )
    {
        $dir = trailingslashit(dirname($file));
        $count = 0;


        // sanitize for Win32 installs
        $dir = str_replace('\\' ,'/', $dir);


        // if file is in plugins folder
        $wp_plugin_dir = str_replace('\\' ,'/', WP_PLUGIN_DIR);
        $dir = str_replace($wp_plugin_dir, plugins_url(), $dir, $count);


        if( $count < 1 )
        {
	        // if file is in wp-content folder
	        $wp_content_dir = str_replace('\\' ,'/', WP_CONTENT_DIR);
	        $dir = str_replace($wp_content_dir, content_url(), $dir, $count);
        }


        if( $count < 1 )
        {
	        // if file is in ??? folder
	        $wp_dir = str_replace('\\' ,'/', ABSPATH);
	        $dir = str_replace($wp_dir, site_url('/'), $dir);
        }


        return $dir;
    }

	/*
	*  get_info
	*
	*  This function will return a setting from the settings array
	*
	*  @type	function
	*  @date	03/12/2015
	*  @since	1.0.0
	*
	*  @param	$i (string) the setting to get
	*  @return	(mixed)
	*/

	function get_info($i)
	{
		// vars
		$return = false;


		// specific
		if( isset($this->settings[ $i ]) )
		{
			$return = $this->settings[ $i ];
		}


		// all
		if( $i == 'all' )
		{
			$return = $this->settings;
		}


		// return
		return $return;
	}

   	/*
	*  include_before_theme
	*
	*  This function will include core files before the theme's functions.php file has been excecuted.
	*
	*  @type	action (plugins_loaded)
	*  @date	03/12/2015
	*  @since	1.0.0
	*
	*  @param	N/A
	*  @return	N/A
	*/

	function include_before_theme()
	{
		// admin only includes
		if( is_admin() )
		{
			$this->migration();

			$this->add_elementor_action();
		}
	}

	public function add_elementor_action()
	{
		add_action( 'elementor_pro/init', function() {
			$path = apply_filters('flashy/get_info', 'path');

			require_once($path . 'core/elementor/form-action.php');

			// Instantiate the action class
			$flashyapp = new Flashyapp_Elementor();

			// Register the action with form widget
			\ElementorPro\Plugin::instance()->modules_manager->get_modules( 'forms' )->add_form_action( $flashyapp->get_name(), $flashyapp );
		});
	}

	/*
	*  include_after_theme
	*
	*  This function will include core files after the theme's functions.php file has been excecuted.
	*
	*  @type	action (after_setup_theme)
	*  @date	03/12/2015
	*  @since	1.0.0
	*
	*  @param	N/A
	*  @return	N/A
	*/

	function include_after_theme() {

		# All Includes (Admin & Web)
		$path = apply_filters('flashy/get_info', 'path');
		
		require_once($path . 'core/Flashy/Flashy.php');

		$this->setApiKey($this->getApiKey());

		require_once(ABSPATH . 'wp-admin/includes/plugin.php');

		if( is_plugin_active('woocommerce/woocommerce.php') )
		{
			if( is_admin() )
			{
				require_once('core/process/wp-background-process.php');
			}

			require_once('core/classes/Flashy_Products_Feed.php');
			require_once('core/classes/Flashy_Export.php');

			$this->export = new Flashy_Export();
		}

		if( !get_option("flashy_key") )
			return;

		require_once('core/snippets/new-contact.php');
		require_once('core/snippets/new-order.php');
		require_once('core/snippets/set-customer.php');
		require_once('core/snippets/tracking.php');

		if( is_plugin_active('woocommerce/woocommerce.php') )
		{
			require_once('core/snippets/add-to-cart.php');
			require_once('core/snippets/conversions.php');
		}

		if( is_plugin_active('woocommerce-points-and-rewards/woocommerce-points-and-rewards.php') )
        {
            require_once('core/snippets/wc-points.php');
        }

        if( is_plugin_active('yith-woocommerce-points-and-rewards/init.php') )
        {
            require_once('core/snippets/yith-points.php');
        }

		if( flashy_settings("add_checkbox") == "yes" )
		{
			$accept_marketing = flashy_settings("accept_marketing");

			if( isset($accept_marketing['checkout']) && $accept_marketing['checkout'] == "yes" )
			{
				$checkbox_position = ( flashy_settings("checkbox_position") ) ? flashy_settings("checkbox_position") : null;

				if( $checkbox_position && isset($checkbox_position['checkout']) )
				{
					add_action( $checkbox_position['checkout'], array($this, 'add_accept_marketing') );
				}
				else
				{
					add_action( 'woocommerce_after_order_notes', array($this, 'add_accept_marketing') );
				}
			}

			if( isset($accept_marketing['signup']) && $accept_marketing['signup'] == "yes" )
			{
				add_action( 'woocommerce_register_form', array($this, 'add_accept_marketing') );
			}
		}

		if( $this->isCF7Active() )
			add_action('wpcf7_before_send_mail', array($this, 'handle_cf7_forms'));

		if( isset($_GET['flashy_cart']) && $this->getContactId() && is_plugin_active('woocommerce/woocommerce.php') )
		{
			add_action('wp_loaded', array($this, 'restoreCart'));
		}

        if( isset($_GET["flashy_order"]) && !empty($_GET["flashy_order"]) && !empty($_GET["order_id"]) && is_plugin_active('woocommerce/woocommerce.php') )
        {
            add_action('wp_loaded', array($this, 'set_cart_from_order'), 10, 0);
        }
	}

	public function isCF7Active()
	{
		return is_plugin_active('contact-form-7/wp-contact-form-7.php') && class_exists('WPCF7_ContactForm') && method_exists('WPCF7_ContactForm', 'get_instance');
	}

	public function restoreCart()
	{
		$cart = $this->getCart($this->getContactId());

		if( $cart )
		{
			$cart_contents = json_decode($cart->cart, true);

			$woo = WC();

			$woo->cart->empty_cart();

            $bundles = [];

			foreach ( $cart_contents as $cart_item )
			{
                $data_cart_item = [];

                if( isset($cart_item['bundled_items']) )
                {
                    $bundles = array_merge($bundles, $cart_item['bundled_items']);

                    $data_cart_item = ["stamp" => $cart_item['stamp']];
                }

                if( !in_array($cart_item['key'], $bundles) )
                {
				    $woo->cart->add_to_cart($cart_item['product_id'], $cart_item['quantity'], $cart_item['variation_id'], $cart_item['variation'], $data_cart_item);
                }
			}
		}
	}

    public function set_cart_from_order()
    {
        $order_id = sanitize_text_field((int)$_GET['order_id']);

        if( empty($order_id) )
            return;

        $cart = wc_get_order($order_id);

        if( $cart )
		{
			$woo = WC();

			$woo->cart->empty_cart();

            $bundles = [];

            foreach( $cart->get_items() as $item_key => $item )
            {
                $product = $item->get_product();

                if( $product->is_type('bundle') )
                {
                    if( $item->get_meta('_bundled_items') )
                    {
                        $bundles = array_merge($bundles, $item->get_meta('_bundled_items'));
                    }
                }

                $variation_data = [];

                if( !empty($item->get_variation_id()) )
                    $variation_data = wc_get_product_variation_attributes( $item->get_variation_id() );

                $data_cart_item = [];

                if( $item->get_meta('_stamp') )
                {
                    $data_cart_item = ["stamp" => $item->get_meta('_stamp')];
                }

                if( !in_array($item->get_meta('_bundle_cart_key'), $bundles) )
                {
                    $woo->cart->add_to_cart($item->get_product_id(), $item->get_quantity(), $item->get_variation_id(), $variation_data, $data_cart_item);
                }
            }
		}
    }

	public function getContactId()
	{
        if(isset($_GET['flsid']))
			return $_GET['flsid'];

        if(isset($_COOKIE['fls_id']))
			return $_COOKIE['fls_id'];

        if( isset($_GET['flashy']) )
			return $_GET['flashy'];

		if(isset($_COOKIE['flashy_id']))
			return $_COOKIE['flashy_id'];

		if( isset($_GET['email']) && filter_var(urldecode($_GET['email']), FILTER_VALIDATE_EMAIL) )
			return base64_encode(urldecode($_GET['email']));

		return false;
	}

	private function migration()
	{
		$db_version  = get_option("flashy_db_version");

		if( $db_version != "1" )
		{
			$charset_collate = $this->wpdb->get_charset_collate();

			$sql = "CREATE TABLE `" . $this->flashy_carts . "` (
					  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
	  				  `flashy_id` text NOT NULL,
					  `cart` longtext DEFAULT NULL,
					  PRIMARY KEY (`id`),
					  KEY `flashy_carts_flashy_id_index` (`flashy_id`(768))
					) " . $charset_collate . ";";

			require_once(ABSPATH . 'wp-admin/includes/upgrade.php');

			dbDelta($sql);

			add_option( 'flashy_db_version', "1");
		}
	}

	private function getCart($flashy_id)
	{
		return $this->wpdb->get_row($this->wpdb->prepare("SELECT * FROM " . $this->flashy_carts . " WHERE flashy_id = %s", $flashy_id));
	}

	public function saveContactCart($flashy_id, $cart)
	{
		$cart_contents = json_encode($cart);

		$cart = $this->getCart($flashy_id);

		$data = array("flashy_id" => $flashy_id, "cart" => $cart_contents);

		$data_format = array('%s', '%s');

		if( $cart == NULL )
		{
			$this->wpdb->insert($this->flashy_carts, $data, $data_format);
		}
		else
		{
			$update = $this->wpdb->update($this->flashy_carts, $data, array("id" => $cart->id), $data_format, array("%d"));
		}
	}

	public function add_accept_marketing()
	{
        if (is_user_logged_in())
        {
            $status = get_user_meta(get_current_user_id(), 'flashy_accept_marketing', true);

            if ((bool) $status)
            {
                return;
            }
        }

        if( flashy_settings("checkbox_marked") && flashy_settings("checkbox_marked") == "yes" )
        {
            $status = true;
        }
        else
        {
            $status = false;
        }

		$label = __( flashy_settings("allow_text"), 'flashy' );

		$checkbox = '<p class="form-row form-row-wide">';
        $checkbox .= '<input class="woocommerce-form__input woocommerce-form__input-checkbox input-checkbox" id="flashy_accept_marketing" type="checkbox" name="flashy_accept_marketing" value="1"' .($status ? ' checked="checked"' : '').'> ';
        $checkbox .= '<label for="flashy_accept_marketing" class="woocommerce-form__label woocommerce-form__label-for-checkbox inline" style="display:inline-block;"><span>' . $label . '</span></label>';
        $checkbox .= '</p>';
        $checkbox .= '<div class="clear"></div>';

        echo $checkbox;
	}

	/**
	 * set flashy api key
	 */
	public function setApiKey($key)
	{
		if($key)
		{
            $this->api = new Flashy\Flashy(array(
                'api_key' => $key,
                'log_path' => $this->settings['path'] . 'error.log'
            ));
		}

        if( isset($this->api) )
        {
            if( get_option('environment') === "dev" )
			{
                $this->api->client->setBasePath('https://api.flashy.dev/');
                $this->api->client->setDebug(true);
            }
            else if( get_option('environment') === "local" )
			{
                $this->api->client->setBasePath(FLASHY_LOCAL);
                $this->api->client->setDebug(true);
            }
        }
	}

	public function getApiKey()
	{
		return get_option("flashy_key");
	}

	/*
	*  init
	*
	*  This function is called during the 'init' action and will do things such as:
	*  create post_type, register scripts, add actions / filters
	*
	*  @type	action (init)
	*  @date	03/12/2015
	*  @since	1.0.0
	*
	*  @param	N/A
	*  @return	N/A
	*/

	function init()
	{
		$styles = array(
			'flashy' => home_url('wp-content/plugins/wp-flashy-marketing-automation/css/flashy.css'),
		);

		foreach( $styles as $k => $v )
		{
			wp_register_style( $k, $v, false, $this->settings['version'] );
		}

		// admin only
		if( is_admin() )
		{
			// set lang local
			$language = $this->getAdminLanguage();

			if( $language )
				load_textdomain('flashy', $this->settings['path'] . 'lang/flashy-' . $language . '.mo');

			$this->post_save_api();

			$this->post_save_catalog();

			$this->post_save_list();

			$this->post_settings();

			$this->post_flashy_cf7();

			add_action('admin_menu', array($this,'admin_menu'));

			wp_enqueue_style(array('flashy'));
		}

		if( isset($_GET["flashy_export"]) )
		{
			$this->export_mode();

			add_action( 'woocommerce_after_register_post_type', array($this, 'export'), 10, 0 );
		}

		// Flashy Actions
		add_action('shutdown', array($this, 'run_flashy_actions'), 10, 0);
	}

	/**
	 * Get Admin Language
	 */
	public function getAdminLanguage()
	{
		$user = wp_get_current_user();

		if( $user )
		{
			$user_meta = get_user_meta($user->ID, 'locale', true);

			if( !empty($user_meta) )
				return $user_meta;

			return get_locale();
		}
		else
		{
			return false;
		}
	}

	function add_action($action, $data)
	{
		$this->actions[$action] = $data;
	}

	function add_hook($func, $data)
	{
		$this->hooks[$func] = $data;
	}

	function load_flashy_hooks()
	{
		try {
			foreach( $this->hooks as $hook => $data)
			{
				$hook($data);
			}
		} catch (\Exception $e) {
			flashy_log("There has been error with loading Flashy hooks.", true);
			flashy_log($e->getMessage(), true);
			flashy_log($e->getTraceAsString(), true);
		}
	}

	function run_flashy_actions()
	{
		$this->load_flashy_hooks();

		$list_id = get_option('flashy_list_id');

		try {
			foreach ($this->actions as $action => $data)
			{
				if($action == "create")
				{
                    $data = $this->getContactAdditionalData($data);

                    Helper::tryOrLog( function () use ($data) {

                        $overwrite = !empty($data['overwrite']) ? true : null;

                        $create = flashy()->api->contacts->create($data, 'email', false, $overwrite);
                    });
				}
				else if( $action == "subscribe" )
				{
                    $data = $this->getContactAdditionalData($data);

                    Helper::tryOrLog( function () use ($data, $list_id) {
					    $create = flashy()->api->contacts->subscribe($data, $list_id, 'email');
                    });
				}
				else if( $action == "purchase" )
				{
					$data = $this->getOrderContext($data);

                    Helper::tryOrLog( function () use ($data) {
                        $create = flashy()->api->events->track("Purchase", $data);
                    });
				}
                else if( $action == "started_checkout" )
				{
                    Helper::tryOrLog( function () use ($data) {
					    flashy()->api->events->track("UpdateCart", [
                            "value" => $data['value'],
                            "content_ids" => $data['content_ids'],
                            "currency" => $data['currency'],
                            "email" => $data['email'],
                            "context" => array(
                                "status" => $data['status'],
                                "checkout_url" => $data['context']['checkout_url'] ?? null
                            )
                        ]);
                    });
				}
				else if($action == "purchase_updated")
                {
                    $data = $this->getOrderContext($data);

					Helper::tryOrLog( function () use($data) {
						$track = flashy()->api->events->track("PurchaseUpdated", $data);
					});
                }
                else if($action == "custom_event")
                {
                    if( is_plugin_active('yith-woocommerce-points-and-rewards/init.php') )
                        $data = $this->getCustomEventData($data, 'yith_points');

                    Helper::tryOrLog( function () use($data) {
						$track = flashy()->api->events->track("CustomEvent", $data);
					});
                }
			}

		} catch (\Exception $e) {
			flashy_log("There has been error with Flashy reporting.", true);
			flashy_log($e->getMessage(), true);
			flashy_log($e->getTraceAsString(), true);
		}
	}

    /**
     * @param $event_data
     * @return array
     * @throws Exception
     */
    public function yithPointsData($event_data)
    {
        $table_log = $this->wpdb->prefix . 'yith_ywpar_points_log';

        $check_table = $this->wpdb->prepare( 'SHOW TABLES LIKE %s', $this->wpdb->esc_like( $table_log ) );

        if( $this->wpdb->get_var( $check_table ) === $table_log )
        {
            $logs = $this->wpdb->get_results($this->wpdb->prepare("SELECT * FROM " . $table_log . " WHERE user_id = %s order by id ASC", $event_data['user_id']));

            if (!$logs)
                return [];

            $newest = count($logs) - 1;

            $user_data = get_user_by('id', $event_data['user_id']);

            $email = "";

            if (!empty($user_data)) {
                $email = $user_data->data->user_email;
            } else {
                return [];
            }

            $event = [
                "event_name" => "points",
                "email" => $email,
                "contact" => [
                    "loyalty_membership_created" => isset($logs[0]->date_earning) ? (new DateTime($logs[0]->date_earning))->getTimestamp() : 0,
                    "total_points" => $event_data['points_left'],
                ]
            ];

            if( strpos((int)$logs[$newest]->amount, '-') !== false )
            {
                $event['contact']['last_points_used'] = isset($logs[$newest]->date_earning) ? (new DateTime($logs[$newest]->date_earning))->getTimestamp() : 0;

                $added_or_removed = "removed";
            } else {

                $event['contact']['last_points_earned'] = isset($logs[$newest]->date_earning) ? (new DateTime($logs[$newest]->date_earning))->getTimestamp() : 0;

                $added_or_removed = "added";
            }

            $prev_points = $event_data['points_left'] - (int)$logs[$newest]->amount;

            $event['context'] = [
                "previous_points" => $prev_points,
                "current_balance" => $event_data['points_left'],
                "difference" => $logs[$newest]->amount,
                "reason" => $logs[$newest]->description,
                "added_or_removed" => $added_or_removed,
            ];

            return $event;
        }
        else
        {
            flashy_log(['error' => 'did not find yith log table']);

            return [];
        }
    }

    /**
     * @param $event_data
     * @param $custom_service
     * @return array
     * @throws Exception
     */
    public function getCustomEventData($event_data, $custom_service = null)
    {
        if( !empty($custom_service) )
        {
            if( $custom_service === 'yith_points' )
            {
                return $this->yithPointsData($event_data);
            }
        }

        return [];
    }

    /**
     * @param $purchase_data
     * @return mixed
     */
    public function getOrderContext($purchase_data)
	{
		$context = apply_filters("flashy_get_order_context", $purchase_data['order_id']);

		if( $context && gettype($context) === "array" )
		{
			$purchase_data['context'] = array_merge($purchase_data['context'], $context);
		}

		return $purchase_data;
	}

    /**
     * @param $contact_data
     * @return mixed
     */
    public function getContactAdditionalData($contact_data)
    {
        $additional_info = apply_filters("flashy_get_additional_contact_info", $contact_data);

        if( $additional_info && gettype($additional_info) === "array" )
		{
			$contact_data = array_merge($contact_data, $additional_info);
		}

        $contact_data['overwrite'] = true;

		return $contact_data;
    }

	/*
	*  before export runs
	*
	*/
	function export_mode()
	{
		ini_set('display_errors', 0);

		error_reporting(0);
	}

	function export()
	{
		if( isset($_GET["key"]) && $_GET["key"] != "" && $_GET["key"] == get_option("flashy_key") && get_option("flashy_key") != "" )
		{
			$per_page = isset($_GET["per_page"]) ? (int) $_GET["per_page"] : -1;

			$page = isset($_GET["page"]) ? (int) $_GET["page"] : 1;

			$pagination = isset($_GET['flashy_pagination']) ? "true" : false;

			if ($per_page == 0 || $per_page < -1)
			{
				wp_send_json(["success" => false, "error" => "Invalid Argument 'per_page'."]);
			}
			else if ($page <= 0)
			{
				wp_send_json(["success" => false, "error" => "Invalid Argument 'page'."]);
			}

			if ($pagination !== 'true')
			{
				$per_page = -1;

				$page =  1;
			}

			if($_GET["flashy_export"] == "products")
			{
				$products = $this->export->getProducts($per_page, $page);

				if ($pagination !== 'true')
				{
					$data = ["success" => true, "total" => $products["total"], "data" => $products["products"]];
				}
				else
				{
					$next_url = $this->build_next_page_url($_GET, $per_page, $page, $products["total"]);

					$data = ["success" => true, "total" => (int) $products["total"], "current_page" => $products["current_page"], "next_page" => $next_url, "data" => $products["products"]];
				}

				wp_send_json($data);
			}
			else if($_GET["flashy_export"] == "customers")
			{
				$customers = $this->export->getCustomers($per_page, $page);

				if ($pagination !== 'true')
				{
					$data = ["success" => true, "total" => (int) $customers["total"], "data" => $customers["customers"]];
				}
				else
				{
					$next_url = $this->build_next_page_url($_GET, $per_page, $page, $customers["total"]);

					$data = ["success" => true, "total" => (int) $customers["total"], "current_page" => $customers["current_page"], "next_page" => $next_url, "data" => $customers["customers"]];
				}

				wp_send_json($data);
			}
            else if($_GET["flashy_export"] == "guests")
			{
				$guests = $this->export->getGuests($per_page, $page);

				if ($pagination !== 'true')
				{
					$data = ["success" => true, "total" => (int) $guests["total"], "data" => $guests["guests"]];
				}
				else
				{
					$next_url = $this->build_next_page_url($_GET, $per_page, $page, $guests["total"]);

					$data = ["success" => true, "total" => (int) $guests["total"], "current_page" => $guests["current_page"], "next_page" => $next_url, "data" => $guests["guests"]];
				}

				wp_send_json($data);
			}
			else if($_GET["flashy_export"] == "orders")
			{
				$orders = $this->export->getOrders($per_page, $page);

				if ($pagination !== 'true')
				{
					$data = ["success" => true, 'total' => (int) $orders['total'], "data" => $orders["orders"]];
				}
				else
				{
					$next_url = $this->build_next_page_url($_GET, $per_page, $page, $orders["total"]);

					$data = ["success" => true, "total" => (int) $orders["total"], "current_page" => $orders["current_page"], "next_page" => $next_url, "data" => $orders["orders"]];
				}

				wp_send_json($data);
			}
			else if($_GET["flashy_export"] == "logs")
			{
				flashy_log("Log exported.");

				$path = apply_filters('flashy/get_info', 'path');

				$logs = explode("\n", file_get_contents($path . "error.log"));

				wp_send_json($logs);
			}
			else if($_GET["flashy_export"] == "resetLogs")
			{
				flashy_log_reset();
				wp_send_json('Logs deleted');
			}
			else if($_GET["flashy_export"] == "info")
			{
				wp_send_json([
					"success" => true,
					"woocommerce" => $this->woo_version(),
					"wordpress" => get_bloginfo( 'version' ),
					"php" => phpversion(),
					"memory_limit" => ini_get('memory_limit'),
					"debug" => WP_DEBUG,
					"debug_log" => WP_DEBUG_LOG,
					"log_path" => ini_get('error_log')
				]);
			}
			else if($_GET["flashy_export"] == "createCoupon")
            {
				$args = $_GET["args"];

				$args = json_decode(base64_decode( $args ), true);

                $createCoupon = $this->export->createCoupon($args);

				wp_send_json($createCoupon);
			}
            else if( $_GET['flashy_export'] == "categories" )
            {
                $categories = $this->export->getProductCategories();

                wp_send_json(["success" => true, "data" => $categories]);
            }
			else
			{
				wp_send_json(["success" => false, "error" => "endpoint_not_found"]);
			}
		}
		else
		{
			wp_send_json(["success" => false, "error" => "Missing API Key."]);
		}
	}

	private function build_next_page_url($get_data, $per_page, $page, $total)
    {
        if ($per_page * $page < $total)
        {
            $get_data["page"] = $page + 1;

            return get_site_url() . "/?" . http_build_query($get_data);
        }

        return null;
    }

	/*
	*  post_save_api
	*
	*  This function is called during the 'init' action checking if api key posted.
	*
	*  @type	action (init)
	*  @date	03/12/2015
	*  @since	1.0.0
	*
	*  @param	N/A
	*  @return	N/A
	*/
	function post_save_api()
	{
		if( isset($_POST['nonce']) && wp_verify_nonce($_POST['nonce'], 'flashy_api_key') )
		{
			if($_POST['flashy_key'] == null || $_POST['flashy_key'] == "")
			{
				add_action( 'admin_notices', array($this, 'key_saved_error') );
			}
			else
			{
				$this->flashy_api_key_changed();
				$this->saveUpdates('key');
			}
		}
	}

	/*
	*  post_save_catalog
	*
	*  This function is called during the 'init' action checking if catalog id posted.
	*
	*  @type	action (init)
	*  @date	03/12/2015
	*  @since	1.0.0
	*
	*  @param	N/A
	*  @return	N/A
	*/
	function post_save_catalog()
	{
		if( isset($_POST['nonce']) && wp_verify_nonce($_POST['nonce'], 'flashy_save_catalog') )
		{
			$this->saveOptionValue("flashy_catalog", intval($_POST['flashy_catalog']));

            $account = Helper::tryOrLog( function () {
                return $this->api->account->get();
            });

			if( isset( $account ) && $account->success() == true )
			{
				// $this->saveOptionValue("flashy_last_update", 15); // For testing

				if( time() > get_option("flashy_last_update") )
				{
					$time = time() + 60*60*24;

					$this->saveOptionValue("flashy_last_update", $time);

					$this->export->push_to_queue($account['account']);

					$this->export->save()->dispatch();
				}
			}

			add_action( 'admin_notices', array($this, 'export_success_notice') );
		}
	}

	/**
	 * Change all settings after api key update
	 * @return boolean
	 */
	function flashy_api_key_changed()
	{
		// Get Account Info
		$api_key = preg_replace('/\s+/', '', $_POST['flashy_key']);

		$this->setApiKey($api_key);

        $account = Helper::tryOrLog( function () {
            try {
                return $this->api->account->get();
            }
            catch (\Flashy\Exceptions\FlashyAuthenticationException $e)
            {
                if( $e->getCode() == 403 )
                {
                    return 403;
                }
                else
                {
                    throw $e;
                }
            }
            catch (\Flashy\Exceptions\FlashyClientException $e)
            {
                Helper::log("Connection issue: " . $e->getMessage());

                return 403;
            }
        });

		if( $account != 403 && isset($account) && $account->success() == true )
		{
			$this->saveOptionValue("flashy_key", $api_key);

			$this->saveOptionValue("flashy_account_id", $account['id']);

			$this->saveOptionValue("flashy_account", $account['account']);

			$this->connect();

			add_action( 'admin_notices', array($this, 'key_saved_notice'));
		}
        else if ( $account == 403 )
        {
            add_action( 'admin_notices', array($this, 'error_code_403'), 10, 1 );
        }
		else
		{
			add_action( 'admin_notices', array($this, 'key_saved_error') );
		}
	}

	public function saveOptionValue($option, $value)
	{
		$get = get_option($option);

		if( $get === false )
		{
			add_option($option, $value, '', 'yes');
		}
		else
		{
			update_option($option, $value);
		}
	}

	/**
	 * save api key notice
	 * @return string
	 */
	function key_saved_notice()
	{
		?>
		<div class="notice notice-success is-dismissible">
			<p><?php _e( 'Changes has been successfully saved', 'flashy' ); ?></p>
		</div>
		<?php
	}

	/**
	 * save api key notice
	 * @return string
	 */
	function key_saved_error()
	{
		?>
		<div class="notice notice-error is-dismissible">
			<p><?php _e( 'The API key is wrong, please try again', 'flashy' ); ?></p>
		</div>
		<?php
	}

    function error_code_403()
	{
		?>
		<div class="notice notice-error is-dismissible">
			<p><?php _e( 'Please contact the support team: support@flashyapp.com', 'flashy' ); ?></p>
		</div>
		<?php
	}

	function list_saved_error()
	{
		?>
		<div class="notice notice-error is-dismissible">
			<p><?php _e( 'The List ID is wrong, please try again', 'flashy' ); ?></p>
		</div>
		<?php
	}

	/**
	 * save flashy id notice
	 * @return string
	 */
	function pixel_saved_notice()
	{
		?>
		<div class="updated">
			<p><?php _e( 'The Flashy account ID has been saved.', 'flashy' ); ?></p>
		</div>
		<?php
	}

	function export_success_notice()
	{
		?>
		<div class="updated">
			<p><?php _e( 'The products successfully exported.', 'flashy' ); ?></p>
		</div>
		<?php
	}

	/**
	 * save api key notice
	 * @return string
	 */
	function woocommerce_missing_notice()
	{
		?>
		<div class="update-nag notice">
			<p><?php _e( 'WooCommerce plugin is missing!', 'flashy' ); ?></p>
		</div>
		<?php
	}

    /**
     * @param $data
     * @return void
     */
    function add_action_purchase($data)
    {
        $order_id = $data['order_id'];
        $order = new WC_Order($order_id);
        $currency = $order->get_currency();
        $total = $order->get_total();
        $order_items = $order->get_items();
        $status = $order->get_status();

        foreach ($order_items as $order_item)
        {
            if( $order_item['variation_id'] == 0  )
            {
                $products[] = $order_item['product_id'];
            }
            else
            {
                $products[] = $order_item['variation_id'];
            }
        }

	    $flashy_id = get_option('flashy_account_id');

        if( isset($flashy_id) && $flashy_id != '' )
        {
            $purchase = array(
                "account_id" => (int) $flashy_id,
                "email" => $order->get_billing_email(),
                "order_id" => strval($order_id),
                "content_ids" => $products,
                "value" => (int) $total,
                "status" => $order->get_status(),
                "currency" => $currency,
            );

            if( get_option('flashy_add_context') == "yes" )
            {
                $purchase['context'] = array(
                    'checkout_url' => $order->get_checkout_payment_url()
                );

                $items = array();

                foreach ( $order->get_items() as $item_id => $item )
                {
                    $product = $item->get_product();

                    if( empty($product) )
                        continue;
                    
                    $items[] = array(
                        "image_link" => $product ? wp_get_attachment_url($product->get_image_id()) : '',
                        "title" => $item->get_name(),
                        "quantity" => $item->get_quantity(),
                        "total" => $item->get_total(),
                    );
                }

                $purchase['context'] = array_merge($purchase['context'], array(
                    "status" => $order->get_status(),
                    "items"   => $items,
                    "total"   => $order->get_total(),
                    "order_id"   => $order_id,
                    "billing" => array(
                        "address" => $order->get_billing_address_1(),
                        "city" => $order->get_billing_city(),
                        "state" => $order->get_billing_state(),
                        "postcode" => $order->get_billing_postcode(),
                        "country" => $order->get_billing_country(),
                    ),
                    "shipping" => array(
                        "address" => $order->get_shipping_address_1(),
                        "city" => $order->get_shipping_city(),
                        "state" => $order->get_shipping_state(),
                        "postcode" => $order->get_shipping_postcode(),
                        "country" => $order->get_shipping_country(),
                        "method" => $order->get_shipping_method(),
                        "price" => !empty($order->get_shipping_total()) ? $order->get_shipping_total() : 0,
                    )
                ));
            }

            if( class_exists("WC_Shipment_Tracking") )
			{
				try {
					$st = WC_Shipment_Tracking_Actions::get_instance();

					$tracking_items = $st->get_tracking_items( $order_id );

                    if( $tracking_items )
                    {
                        foreach( $tracking_items as $tracking )
                        {
                            $data['tracking_number'] = $tracking['tracking_number'];
                        }
                    }
				} catch (Exception $e) {
				}
			}

            $status = get_flashy_status($status);

            if( $status['event'] !== "none" )
            {
                flashy()->add_action($status['event'], $purchase);
            }
        }
    }

	function order_status_changed($order_id, $status_from, $status_to, $instance)
    {
        if( doing_action('handle_bulk_actions-edit-shop_order') )
            return;

    	$api_key = get_option('flashy_key');

    	if( is_plugin_active('woocommerce/woocommerce.php') && $api_key && $api_key !== "" && $this->api && !isset($this->hooks['flashy_purchase']))
		{
            $this->add_action_purchase(['order_id' => $order_id]);
		}
    }

    function handle_order_bulk_changes($redirect_to, $action, $order_ids)
    {
        if( strpos($action, 'mark_') !== FALSE && is_array($order_ids) ) {

            $api_key = get_option('flashy_key');

            if( is_plugin_active('woocommerce/woocommerce.php') && $api_key && $api_key !== "" && $this->api && !isset($this->hooks['flashy_purchase']) )
            {
                $flashy_id = get_option('flashy_account_id');

                if( isset($flashy_id) && !empty($flashy_id) )
                {
                    foreach( $order_ids as $order_id )
                    {
                        $order = new WC_Order($order_id);

                        $status = str_replace('mark_','', $action);

                        $data = [
                            'account_id' => (int)$flashy_id,
                            'email' => $order->get_billing_email(),
                            'order_id' => $order_id,
                            'status' => $status,
                        ];

                        Helper::tryOrLog(function () use ($data) {
                            $track = flashy()->api->events->track("PurchaseUpdated", $data);
                        });
                    }
                }
            }
        }

        return $redirect_to;
    }

	function check_woocommerce()
	{
		if( is_plugin_active('woocommerce/woocommerce.php') )
		{
			add_action( 'admin_notices', array($this, 'woocommerce_missing_notice') );
		}
	}

	function handle_cf7_forms($post)
	{
		$form_id = $post->id();

		$submission = WPCF7_Submission::get_instance();

		$posted_data = $submission->get_posted_data();

		$form = WPCF7_ContactForm::get_instance($form_id);

		$map = get_option('flashy_cf7');

		if( isset($map[$form_id]) && isset($map[$form_id]['active']) && $map[$form_id]['active'] == "1" )
		{
			$map = $map[$form_id];

			$contact = [];

			foreach ($post->scan_form_tags() as $field)
			{
				if( isset($posted_data[$field->name]) && $map[$field->name] != "" )
				{
					if( gettype($posted_data[$field->name]) !== "array" )
						$contact[ $map[$field->name] ] = $posted_data[$field->name];
					else
					{
						$contact[ $map[$field->name] ] = implode(",", $posted_data[$field->name]);
					}
				}
			}

			try {
				if( $map['list_id'] != "" )
				{
                    Helper::tryOrLog( function () use($contact, $map) {
                        $create = flashy()->api->contacts->subscribe($contact, $map['list_id'], 'email');
                    });
				}
				else
				{
                    Helper::tryOrLog( function () use($contact) {
                        $create = flashy()->api->contacts->create($contact, 'email');
                    });
				}

			} catch (\Exception $e) {
				flashy_log("There was an error from CF7 event list.", true);
				flashy_log($e->getTraceAsString(), true);
			}
		}
	}

	/*
	*  admin_menu
	*
	*  @description:
	*  @since 1.0.0
	*  @created: 03/12/2015
	*/

	function admin_menu()
	{
		add_menu_page(__("Flashy",'flashy'), __("Flashy",'flashy'), 'manage_options', 'flashy', array($this, 'html'), false, '90');
	}

	function is_connected()
	{
		return ( get_option('flashy_connected') == null ? false : true );
	}

	function connect()
	{
		$api_key = get_option('flashy_key');

		$store = array(
			"profile" => array(
				"from_name" => get_option('woocommerce_email_from_name'),
				"from_email" => get_option('woocommerce_email_from_address'),
				"reply_to" => get_option('woocommerce_email_from_address'),
			),
			"store"	=> array(
				"platform" => "woo",
				"api_key" => $api_key,
				"store_name" => get_option('woocommerce_email_from_name'),
				"store" => get_home_url(),
				"debug" => array(
					"woocommerce" => $this->woo_version(),
					"wordpress" => get_bloginfo( 'version' ),
					"php" => phpversion(),
					"memory_limit" => ini_get('memory_limit'),
				),
			),
			"contacts" => [
				"url" => get_home_url() . "/?flashy_export=customers&flashy_pagination=true&per_page=100&key=" . $api_key,
				"format" => "json_url"
			],
			"products" => [
				"url" => get_home_url() . "/?flashy_export=products&flashy_pagination=true&per_page=100&key=" . $api_key,
				"format" => "json_url",
			],
			"orders" => [
				"url" => get_home_url() . "/?flashy_export=orders&flashy_pagination=true&per_page=100&key=" . $api_key,
				"format" => "json_url"
			],
		);

        $return = Helper::tryOrLog( function () use($store) {
            return $this->api->platforms->connect($store);
        });

		flashy_log(json_encode($return->getBody()));

		if( ! $this->is_connected() )
			$this->saveOptionValue("flashy_connected", time());
	}

	function post_save_list()
	{
		if( isset($_POST['nonce']) && wp_verify_nonce($_POST['nonce'], 'flashy_list_key') )
		{
			if($_POST['list_id'] == null || $_POST['list_id'] == "")
			{
				add_action( 'admin_notices', array($this, 'key_saved_error') );
			}
			else
			{
				$this->saveOptionValue("flashy_list_id", (int) $_POST['list_id']);

				if( isset($_POST['flashy_subscribe']) )
					$this->saveOptionValue("flashy_subscribe", $_POST['flashy_subscribe']);

				$this->saveUpdates('list');

				add_action('admin_notices', array($this, 'key_saved_notice'));
			}
		}
	}

	function post_settings()
	{
		if( isset($_POST['nonce']) && wp_verify_nonce($_POST['nonce'], 'flashy_save_settings') )
		{
			$this->saveOptionValue("flashy_allow_guest", $_POST['flashy_allow_guest']);

			$this->saveOptionValue("flashy_add_context", $_POST['flashy_add_context']);

            if( isset($_POST['flashy_settings']['woo_statuses']) )
                $_POST['flashy_settings']['woo_statuses'] = stripslashes($_POST['flashy_settings']['woo_statuses']);

			$this->saveOptionValue("flashy_settings", $_POST['flashy_settings']);

			$this->saveUpdates('settings');

			if( isset($_POST['flashy_subscribe']) )
				$this->saveOptionValue("flashy_subscribe", $_POST['flashy_subscribe']);

			add_action('admin_notices', array($this, 'key_saved_notice'));
		}
	}

	function post_flashy_cf7()
	{
		if( isset($_POST['nonce']) && wp_verify_nonce($_POST['nonce'], 'flashy_save_cf7') )
		{
			$this->saveOptionValue("flashy_cf7", $_POST['flashy_cf7']);

			$this->saveUpdates('cf7');

			add_action('admin_notices', array($this, 'key_saved_notice'));
		}
	}

	function woo_version()
	{
		if ( class_exists('woocommerce') ) {
			global $woocommerce;

			return $woocommerce->version;
		}
		else {
			return false;
		}
	}

	function saveDefaultList($list_id, $lists)
	{
		if( $list_id == false && isset($lists) && count($lists) > 0 )
		{
			$this->saveOptionValue("flashy_list_id", array_keys($lists)[0]);
		}
	}

	public function lists()
    {
        $lists = Helper::tryOrLog( function () {
            return $this->api->lists->get();
        });

		$options = [];

		$options[''] = __("Choose a list","flashy");

		if( isset($lists) )
		{
			foreach( $lists->getData() as $list )
			{
				$options[$list['id']] = $list['title'];
			}
		}

		return $options;
	}

	public function fields()
	{
		$fields = Helper::tryOrLog( function () {
			return $this->api->contacts->properties();
		});

		$contact_fields = [];

		if( isset( $fields ))
			foreach( $fields->getData() as $field )
			{
				$contact_fields[$field['key']] = $field['title'];
			}

		return $contact_fields;
	}

	public function reset()
	{
		$this->saveOptionValue("flashy_key", null);
		$this->saveOptionValue("flashy_account", null);
		$this->saveOptionValue("flashy_connected", null);
		$this->saveOptionValue("flashy_catalog", null);
		$this->saveOptionValue("flashy_list_id", null);
		$this->saveOptionValue("flashy_subscribe", null);
		$this->saveOptionValue("flashy_allow_guest", null);
		$this->saveOptionValue("flashy_add_context", null);
		$this->saveOptionValue("flashy_settings", null);
		$this->saveOptionValue("flashy_cf7", null);
		$this->saveOptionValue("flashy_updates", null);
		$this->saveOptionValue("environment", null);
		$this->saveOptionValue("flashy_log_state", null);
	}

	public function saveUpdates($key)
	{
		$flashy_updates = get_option('flashy_updates');

		if($flashy_updates == false)
		{
			$flashy_updates = [
				'list' => 'N/A',
				'settings' => 'N/A',
				'cf7' => 'N/A',
				'key' => 'N/A',
			];
		}
		else
		{
			$flashy_updates = json_decode($flashy_updates, true);
		}

		$flashy_updates[$key] = current_time('d/m/Y H:i:s');

		$this->saveOptionValue("flashy_updates", json_encode($flashy_updates));
	}


	/*
	*  html
	*
	*  @description:
	*  @since 1.0.0
	*  @created: 03/12/2015
	*/

	function html()
	{
		$api_key  = get_option('flashy_key');
		$account  = get_option('flashy_account');
		$connected  = get_option('flashy_connected');
		$flashy_catalog = get_option('flashy_catalog');
		$flashy_list_id = get_option('flashy_list_id');
		$flashy_subscribe = get_option('flashy_subscribe');
		$flashy_allow_guest = get_option('flashy_allow_guest');
		$flashy_add_context = get_option('flashy_add_context');
		$flashy_settings = get_option('flashy_settings');
		$flashy_cf7 = get_option('flashy_cf7');
		$flashy_updates = get_option('flashy_updates');

        if( is_plugin_active('woocommerce/woocommerce.php') )
		    $woo_statuses = get_woo_statuses();

		if($flashy_updates == false)
		{
			$flashy_updates = [
				'list' => 'N/A',
				'settings' => 'N/A',
				'cf7' => 'N/A',
				'key' => 'N/A',
			];
		}
		else
		{
			$flashy_updates = json_decode($flashy_updates, true);
		}


		if(flashy_settings("checkbox_position") && isset(flashy_settings("checkbox_position")['checkout']))
		{
			$checkbox_position = flashy_settings("checkbox_position")['checkout'];
		}
		else
		{
			$checkbox_position = 'woocommerce_after_order_notes';
		}

		if( $flashy_cf7 == false )
			$flashy_cf7 = [];

		if( $this->is_connected() )
		{
			$lists = $this->lists();

			$contact_fields = $this->fields();

			$this->saveDefaultList($flashy_list_id, $lists);
		}

		if( isset($_GET['reset']) && $_GET['reset'] == "true" )
		{
			$this->reset();
		}

		if(isset($_GET['environment']) && $_GET['environment'] == 'dev')
        {
            $this->saveOptionValue("environment", 'dev');
            return;
		}

        if( isset($_GET['environment']) && $_GET['environment'] == 'local' )
        {
            $this->saveOptionValue("environment", 'local');
            return;
		}

        if( isset($_GET['flashy_log_state']) )
        {
            if( $_GET['flashy_log_state'] == 'true' )
            {
                $this->saveOptionValue("flashy_log_state", 'true');
                return;
            }
            else if( $_GET['flashy_log_state'] == 'false' )
            {
                $this->saveOptionValue("flashy_log_state", null);
                return;
            }
		}
		?>

		<?php if(get_option("environment") === 'dev' || get_option("environment") === 'local') { ?>

            <div class="flashy-connect">
                <div class="title">
                    <h3 style="margin:0 0 10px 0"><?php _e("You are running on DEV / LOCAL mode",'flashy'); ?></h3>
                </div>
                <div class="flashy-content">
                    <div id="titlediv">
                        <div id="titlewrap">
                            <?php _e("In order to quit DEV / LOCAL mode just reset our plugin.",'flashy'); ?>
                        </div>
                    </div>
                </div>
            </div>

        <?php } ?>

		<?php if( !$this->is_connected() ) { ?>

		<?php if($api_key == null) { ?>
            <div class="flashy-connect">
                <form method="post" name="" action="">
                    <input type="hidden" name="nonce" value="<?php echo wp_create_nonce( 'flashy_api_key' ); ?>" />
                    <div class="title">
                        <h3 style="margin:0 0 10px 0;"><?php _e("API Key",'flashy'); ?></h3>
                    </div>
                    <div class="flashy-content">
                        <div id="titlediv">
                            <div id="titlewrap">
                                <input type="text" name="flashy_key" size="64" value="<?php echo $api_key; ?>" id="title">
                            </div>
                        </div>
                        <div class="submit-post">
                            <input type="submit" name="save_key" id="save_key" class="flashy-primary-btn" value="<?php _e("Connect to Flashy",'flashy'); ?>">
                        </div>
                    </div>

                    <div class="clearfix"></div>
                </form>
            </div>

            <div class="flashy-connect">
                <div class="title">
                    <h3 style="margin:0 0 10px 0"><?php _e("How To Install",'flashy'); ?></h3>
                </div>
                <div class="flashy-content">
                    <div id="titlediv">
                        <div id="titlewrap">
							<?php _e("Find your API key at <a href='https://my.flashy.app/auth/signin' target='_blank'>https://flashy.app</a> and save - That's it!",'flashy'); ?>
                        </div>
                    </div>
                </div>
            </div>

		<?php } ?>

	<?php } else { ?>

        <div class="flahsy-app" ng-app="flashy">

            <div class="main-controller" ng-controller="MainController">

                <div class="flashy-connect" style="text-align: center;">
                    <img src="https://cdn.flashyapp.com/icons/success.png" width="200" alt="">
                    <h3 style="margin:30px; color: #1c3155; font-weight: 700;font-size: 22px;"><?php _e("FLASHY CONNECTED",'flashy'); ?></h3>
					<?php if( time() > ($connected + 600) ) { ?>
                        <a target="_blank" href="https://my.flashy.app/dash/<?php echo $account; ?>/overview" class="flashy-success-btn"><?php _e("Go to dashboard",'flashy'); ?></a>
					<?php } ?>
                </div>

                <div class="flashy-connect">
                    <form method="post" action="" name="save_list">
                        <input type="hidden" name="nonce" value="<?php echo wp_create_nonce( 'flashy_list_key' ); ?>" />
                        <div class="title">
                            <h2 style="font-size:24px;"><?php _e("Subscribers List",'flashy'); ?></h2>
                        </div>

                        <div class="flashy-content">
                            <div id="titlediv">
                                <div id="titlewrap">
                                    <select name="list_id" id="list_id" style="width: 100%;padding:10px;">
										<?php foreach ($lists as $list_id => $list_title ) { ?>
											<?php if( $flashy_list_id == $list_id ) { ?>
                                                <option value="<?php echo $list_id; ?>" selected><?php echo $list_title; ?></option>
											<?php } else { ?>
                                                <option value="<?php echo $list_id; ?>"><?php echo $list_title; ?></option>
											<?php } ?>
										<?php } ?>
                                    </select>
									<?php if(count($lists) <= 1): ?>
                                        <p class="alert alert-danger"><?php _e("You must create a list in our platform. Click the following <a href='https://flashy.app/help/contact-management/how-to-create-a-list/'>link</a> for more information.",'flashy'); ?></p>
									<?php elseif($flashy_list_id == ''): ?>
                                        <p class="p-alert alert alert-danger"><?php _e("You must choose a subscriber list from the following options.",'flashy'); ?></p>
									<?php endif; ?>
                                </div>
                            </div>

                            <div class="submit-post">
                                <input type="submit" name="save_list" id="save_list" class="flashy-primary-btn" value="<?php _e("Save List",'flashy'); ?>">
                                <p class="update-msg"><?php _e("Last updated at: ",'flashy'); ?> <?= $flashy_updates['list'] ?></p>
                            </div>
                        </div>

                        <div class="clearfix"></div>
                    </form>
                </div>

                <div class="flashy-connect">
                    <form method="post" name="save_settings" action="">
                        <input type="hidden" name="nonce" value="<?php echo wp_create_nonce( 'flashy_save_settings' ); ?>" />

                        <h2 style="font-size:24px;margin-bottom: 30px;"><?php _e("Settings", 'flashy'); ?></h2>

                        <div class="flex">
                            <div style="width:33%;">
                                <div class="title">
                                    <h3><?php _e("Add Accept Marketing Checkbox",'flashy'); ?></h3>
                                    <p style="font-size:16px;width: 80%;"><?php _e("Flashy can manage your subscription list by adding a checkbox to the pages you choose or you can use your own checkbox.",'flashy'); ?></p>
                                </div>

                                <div class="flashy-content">
                                    <div id="titlediv">
                                        <div id="titlewrap">
                                            <select name="flashy_settings[add_checkbox]" ng-model="settings.add_checkbox" id="add_checkbox" style="width: 100%;padding:10px;">
                                                <option value="yes"><?php _e("Add Flashy Checkbox",'flashy'); ?></option>
                                                <option value="no"><?php _e("We Have Our Own Checkbox",'flashy'); ?></option>
                                            </select>
                                            <select name="flashy_settings[checkbox_marked]" ng-model="settings.checkbox_marked" id="checkbox_marked" style="width: 100%;padding:10px; margin-top:10px;" ng-if="settings.add_checkbox && settings.add_checkbox == 'yes'">
                                                <option value="yes"><?php _e("Mark checkbox by default",'flashy'); ?></option>
                                                <option value="no"><?php _e("Do not mark checkbox by default",'flashy'); ?></option>
                                            </select>
                                        </div>
                                    </div>
                                </div>

                                <div class="clearfix"></div>
                            </div>

                            <div style="width:33%;" ng-if="!settings.add_checkbox || settings.add_checkbox == 'yes'">
                                <div class="title">
                                    <h3><?php _e("Where To Add Checkbox",'flashy'); ?></h3>
                                </div>

                                <div class="flashy-content">
                                    <div id="titlediv">
                                        <div id="titlewrap">
                                            <p style="font-size:16px;"><?php _e("Choose where you want to add Flashy checkbox",'flashy'); ?></p>

                                            <div style="margin-bottom: 10px;">
                                                <input type="checkbox" ng-model="settings.accept_marketing.checkout" name="flashy_settings[accept_marketing][checkout]" id="flashy_settings_add_checkout" value="yes" ng-true-value="'yes'">
                                                <label for="flashy_settings_add_checkout" style="cursor: pointer;font-weight:bold;"><?php _e("On checkout page",'flashy'); ?></label>
                                            </div>

                                            <div style="margin-bottom: 10px;">
                                                <input type="checkbox" ng-model="settings.accept_marketing.signup" name="flashy_settings[accept_marketing][signup]" id="flashy_settings_add_signup" value="yes" ng-true-value="'yes'">
                                                <label for="flashy_settings_add_signup" style="cursor: pointer;font-weight:bold;"><?php _e("On signup page",'flashy'); ?></label>
                                            </div>

                                            <div class="clearfix"></div>
                                        </div>
                                    </div>
                                </div>

                                <div class="clearfix"></div>
                            </div>

                            <div style="width:33%;">
                                <div ng-if="settings.accept_marketing.checkout || settings.accept_marketing.signup">
                                    <div class="title">
                                        <h3><?php _e("Accept Marketing Text", 'flashy'); ?></h3>
                                    </div>

                                    <div class="flashy-content">
                                        <div id="titlediv">
                                            <div id="titlewrap">
                                                <p style="font-size:16px;"><?php _e("Add a message for your customers before the checkbox.",'flashy'); ?></p>

                                                <input type="text" id="allow_text" name="flashy_settings[allow_text]" ng-model="settings.allow_text" style="width: 100%;padding:10px;margin-top: 5px;max-width:25rem;">
                                            </div>

                                            <div class="clearfix"></div>
                                        </div>
                                    </div>

                                    <div class="clearfix"></div>
                                </div>

                                <div style="margin-top: 1rem" ng-if="settings.add_checkbox == 'yes' && settings.accept_marketing.checkout">
                                    <div class="title">
                                        <h3><?php _e("Checkout Checkbox Position",'flashy'); ?></h3>
                                    </div>

                                    <div class="flashy-content">
                                        <div id="titlediv">
                                            <div id="titlewrap">
                                                <p style="font-size:16px;"><?php _e("Choose where you want to position Flashy checkbox on the <b>checkout</b> page.",'flashy'); ?></p>

                                                <div ng-init="settings.checkbox_position.checkout = '<?php echo $checkbox_position; ?>'" style="margin-bottom: 10px;">
                                                    <div>
                                                        <input ng-model="settings.checkbox_position.checkout" name="flashy_settings[checkbox_position][checkout]" type="radio" id="woocommerce_after_order_notes" value="woocommerce_after_order_notes">
                                                        <label for="woocommerce_after_order_notes"><?php _e("After Order Notes", 'flashy'); ?></label>
                                                    </div>
                                                    <div>
                                                        <input ng-model="settings.checkbox_position.checkout" name="flashy_settings[checkbox_position][checkout]" type="radio" id="woocommerce_review_order_before_submit" value="woocommerce_review_order_before_submit">
                                                        <label for="woocommerce_review_order_before_submit"><?php _e("On Review Order (Before Submit)", 'flashy'); ?></label>
                                                    </div>
                                                </div>

                                                <div class="clearfix"></div>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="clearfix"></div>
                                </div>
                            </div>

                            <div style="width:33%;" ng-if="settings.add_checkbox == 'no'">
                                <div class="title">
                                    <h3><?php _e("Checkbox Name",'flashy'); ?></h3>
                                </div>

                                <div class="flashy-content">
                                    <div id="titlediv">
                                        <div id="titlewrap">
                                            <p style="font-size:16px;"><?php _e("If you have a checkbox on the checkout page that offers the customer to join your mailing list, you can subscribe him automatically. The name of the field on the checkout page they check must be filled.",'flashy'); ?></p>
											<?php if( $flashy_subscribe != false ) { ?>
                                                <input type="text" name="flashy_subscribe" size="64" value="<?php echo $flashy_subscribe; ?>" id="flashy_subscribe" style="width: 100%;padding:10px;">
											<?php } else { ?>
                                                <input type="text" name="flashy_subscribe" size="64" placeholder="Use Meta Term To Automatically Subscribe" id="flashy_subscribe" style="width: 100%;padding:10px;">
											<?php } ?>
                                        </div>
                                    </div>
                                </div>

                                <div class="clearfix"></div>
                            </div>
                        </div>

                        <div class="clearfix"></div>
                        <hr style="margin:30px 0;">

                        <div class="flex">

                            <div style="width:50%;">
                                <div class="title">
                                    <h3><?php _e("Create Account For Guests",'flashy'); ?></h3>
                                    <p style="font-size:16px;width: 80%;"><?php _e("When a visitor places an order on the site <b><u>as a guest</u></b>, you can control whether or not to create an account for him in Flashy.",'flashy'); ?></p>
                                </div>

                                <div class="flashy-content">
                                    <div id="titlediv">
                                        <div id="titlewrap">
                                            <select name="flashy_allow_guest" id="flashy_allow_guest">
                                                <option value="yes"><?php _e("Yes", 'flashy'); ?></option>
                                                <option value="no" <?php echo ( $flashy_allow_guest == "no" ) ? 'selected="selected"' : ""; ?>><?php _e("No", 'flashy'); ?></option>
                                            </select>
                                        </div>
                                    </div>
                                </div>

                                <div class="clearfix"></div>
                            </div>

                            <div style="width:50%;">
                                <div class="title">
                                    <h3><?php _e("When To Create Flashy Contact",'flashy'); ?></h3>
                                    <p style="font-size:16px;width: 80%;"><?php _e("You can decide in when our plugin will create a new Flashy contact.",'flashy'); ?></p>
                                </div>

                                <div class="flashy-content">
                                    <div id="titlediv">
                                        <div id="titlewrap">
                                            <select name="flashy_settings[create_contact]">
                                                <option value="all"><?php _e("On User Creation & Purchase", 'flashy'); ?></option>
                                                <option value="purchase" <?php echo ( flashy_settings("create_contact") == "purchase" ) ? 'selected="selected"' : ""; ?>><?php _e("After Purchase Only", 'flashy'); ?></option>
                                            </select>
                                        </div>
                                    </div>
                                </div>

                                <div class="clearfix"></div>
                            </div>

                        </div>

                        <div class="clearfix"></div>

                        <hr style="margin:30px 0;">

                        <div class="clearfix"></div>

                        <div class="flex">

                            <div style="width:50%;float:left;">
                                <div class="title">
                                    <h3><?php _e("Context Information",'flashy'); ?></h3>
                                    <p style="font-size:16px;"><?php _e("Add contextual data to Flashy's events.",'flashy'); ?></p>
                                </div>

                                <div class="flashy-content">
                                    <div id="titlediv">
                                        <div id="titlewrap">
                                            <select name="flashy_add_context" id="flashy_add_context">
                                                <option value="no"><?php _e("Without Contextual Data", 'flashy'); ?></option>
                                                <option value="yes" <?php echo ( $flashy_add_context == "yes" ) ? 'selected="selected"' : ""; ?>><?php _e("Add Contextual Data", 'flashy'); ?></option>
                                            </select>
                                        </div>
                                    </div>
                                </div>

                                <div class="clearfix"></div>
                            </div>

                            <div style="width:50%;float:left;display:none;">
                                <div class="title">
                                    <h3><?php _e("When To Send Purchase Event",'flashy'); ?></h3>
                                    <p style="font-size:16px;"><?php _e("On thank you page - after customer completed payment. <br/> On order placed - before the checkout process completed.",'flashy'); ?></p>
                                </div>

                                <div class="clearfix"></div>
                            </div>
                        </div>

                        <div style="clear:both;"></div>

                        <hr style="margin:30px 0;">

                        <div class="woo">
                            <h3 style=""><?php _e("WooCommerce Statuses", 'flashy'); ?></h3>

                            <div class="flex">
                                <div style="width:33%;">
                                    <div class="title">
                                        <p style="font-size:16px;width: 80%;"><?php _e("We suggest to keep the statuses by default, but if you are using custom WooCommerce statuses you can control the integration.",'flashy'); ?></p>
                                        <a href="URL:void(0)" ng-show="show_statuses != true" ng-click="show_statuses=true" style="font-size: 18px;font-weight: bold;cursor:pointer;"><?php _e("Manage Statuses",'flashy'); ?></a>
                                    </div>

                                    <div class="flashy-content" ng-show="show_statuses == true">
                                       <?php foreach( $woo_statuses as $key => $status ) { ?>
                                           <label for="status-<?php echo $key; ?>" style="font-size: 16px;font-weight: bold;display: block;margin-top: 8px;"><?php echo $status['label']; ?> (<?php _e($key, 'flashy'); ?>)</label>
                                           <select id="status-<?php echo $key; ?>" style="width: 100%;padding:10px;margin-bottom: 15px;display:block;" ng-model="settings.woo_statuses['<?php echo $key; ?>']['event']">
                                               <option value="started_checkout"><?php _e("Checkout Created", 'flashy'); ?></option>
                                               <option value="purchase"><?php _e("Order Created", 'flashy'); ?></option>
                                               <option value="purchase_updated"><?php _e("Order Updated", 'flashy'); ?></option>
                                               <option value="none"><?php _e("Don't Track", 'flashy'); ?></option>
                                           </select>
                                        <?php } ?>
                                    </div>

                                    <input type="text" name="flashy_settings[woo_statuses]" ng-value="getWooStatuses(settings.woo_statuses)" style="display:none;">

                                    <div class="clearfix"></div>
                                </div>
                            </div>
                        </div>

                        <hr style="margin:30px 0;">

                        <div class="submit-post">
                            <input type="submit" class="flashy-primary-btn" value="<?php _e("Save Changes",'flashy'); ?>" style="margin-top:0;">
                            <p class="update-msg"><?php _e("Last updated at: ",'flashy'); ?> <?= $flashy_updates['settings'] ?></p>
                        </div>
                    </form>
                </div>

				<?php if( $this->isCF7Active() ) { ?>
                    <div class="flashy-connect">
                        <div class="title">
                            <h2 style="font-size:24px;"><?php _e("Contact Forms", 'flashy'); ?></h2>
                        </div>

                        <div class="flashy-content">
                            <div id="titlediv">
                                <div id="titlewrap">
									<?php _e("Create contact profile on CF7 forms.",'flashy'); ?>

                                    <form method="post" action="" name="save_cf7_flashy">
                                        <input type="hidden" name="nonce" value="<?php echo wp_create_nonce( 'flashy_save_cf7' ); ?>" />

                                        <div id="forms-list">
											<?php
											$args = array('post_type' => 'wpcf7_contact_form', 'posts_per_page' => -1);

											if( $data = get_posts($args) )
											{
												foreach($data as $form) { ?>
                                                    <div class="card w-75">
                                                        <div class="card-header">
                                                            <h5 class="mb-0">
                                                                <button class="btn btn-link" type="button" data-toggle="collapse" data-target="#form-<?php echo $form->ID; ?>" aria-expanded="true" aria-controls="form-<?php echo $form->ID; ?>">
																	<?php echo $form->post_title; ?>
                                                                </button>
                                                            </h5>
                                                        </div>
                                                        <div id="form-<?php echo $form->ID; ?>" class="collapse" data-parent="#forms-list">
                                                            <div class="card-body">

                                                                <label for="active-<?php echo $form->ID; ?>"><b><?php _e("Active",'flashy'); ?></b></label>
                                                                <select name="flashy_cf7[<?php echo $form->ID; ?>][active]" ng-model='flashy_cf7[<?php echo $form->ID; ?>]["active"]' id="active-<?php echo $form->ID; ?>" style="margin-bottom: 10px;">
                                                                    <option value=""><?php _e("No",'flashy'); ?></option>
                                                                    <option value="1"><?php _e("Yes",'flashy'); ?></option>
                                                                </select>

                                                                <div ng-if='flashy_cf7[<?php echo $form->ID; ?>]["active"] == 1'>
                                                                    <label for="form-list-<?php echo $form->ID; ?>"><b><?php _e("Flashy List", 'flashy'); ?></b></label>
                                                                    <select name="flashy_cf7[<?php echo $form->ID; ?>][list_id]" ng-model='flashy_cf7[<?php echo $form->ID; ?>]["list_id"]' id="form-list-<?php echo $form->ID; ?>">
                                                                        <option value=""><?php _e("None", 'flashy'); ?></option>
																		<?php foreach ($lists as $list_id => $list_title) { ?>
                                                                            <option value="<?php echo $list_id; ?>"><?php echo $list_title; ?></option>
																		<?php } ?>
                                                                    </select>

                                                                    <h3 style="margin-top: 20px;"><?php _e("Map Fields", 'flashy'); ?></h3>
                                                                    <table class="table table-striped">
                                                                        <thead>
                                                                        <tr>
                                                                            <th scope="col"><?php _e("CF7 Field",'flashy'); ?></th>
                                                                            <th scope="col"><?php _e("Flashy Field",'flashy'); ?></th>
                                                                        </tr>
                                                                        </thead>
                                                                        <tbody>
																		<?php
																		$ContactForm = WPCF7_ContactForm::get_instance( $form->ID );
																		$fields = $ContactForm->scan_form_tags();
																		foreach ($fields as $field) { if( $field->name == "" ) continue; ?>
                                                                            <tr>
                                                                                <td><?php echo $field->name; ?></td>
                                                                                <td>
                                                                                    <select name='flashy_cf7[<?php echo $form->ID; ?>][<?php echo $field->name; ?>]' ng-model='flashy_cf7[<?php echo $form->ID; ?>]["<?php echo $field->name; ?>"]'>
                                                                                        <option value=""><?php _e("None", 'flashy'); ?></option>
																						<?php foreach ($contact_fields as $field_key => $cfield) { ?>
                                                                                            <option value="<?php echo $field_key; ?>"><?php echo $cfield; ?></option>
																						<?php } ?>
                                                                                    </select>
                                                                                </td>
                                                                            </tr>
																		<?php } ?>
                                                                        </tbody>
                                                                    </table>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>
													<?php
												}
											}
											?>
                                        </div>

                                        <div class="submit-post">
                                            <input type="submit" name="save_all" id="save_all" class="flashy-primary-btn" value="<?php _e("Save Contact Forms",'flashy'); ?>">
                                            <p class="update-msg"><?php _e("Last updated at: ",'flashy'); ?> <?= $flashy_updates['cf7'] ?></p>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        </div>
                    </div>
				<?php } ?>

                <div class="flashy-connect">
                    <form method="post" name="" action="">
                        <input type="hidden" name="nonce" value="<?php echo wp_create_nonce( 'flashy_api_key' ); ?>" />
                        <div class="title">
                            <h3><?php _e("API Key",'flashy'); ?></h3>
                        </div>
                        <div class="flashy-content">
                            <div id="titlediv">
                                <div id="titlewrap">
                                    <input type="text" name="flashy_key" size="64" value="<?php echo $api_key; ?>" id="title">
                                </div>
                            </div>
                            <div class="submit-post">
                                <input type="submit" name="save_key" id="save_key" class="flashy-primary-btn" value="<?php _e("Update API Key",'flashy'); ?>">
                                <p class="update-msg"><?php _e("Last updated at: ",'flashy'); ?> <?= $flashy_updates['key'] ?></p>
                            </div>
                        </div>

                        <div class="clearfix"></div>
                    </form>
                </div>

                <div class="flashy-connect">
                    <div class="title">
                        <h3><?php _e("Your Benefits",'flashy'); ?></h3>
                    </div>
                    <div class="flashy-content">
                        <div id="titlediv">
                            <div id="titlewrap">
                                <ul>
                                    <li><?php _e("Syncing Products, Orders and Customers.",'flashy'); ?></li>
                                    <li><?php _e("Tracking Events: Page View, View Product, Add To Cart and Purchase.",'flashy'); ?></li>
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="flashy-connect">
                    <div class="title">
                        <h3><?php _e("Your Products Catalogs",'flashy'); ?></h3>
                    </div>

                    <div class="flashy-content">
                        <div id="titlediv">
                            <div id="titlewrap">
								<?php echo get_home_url() . "/?flashy_export=products&flashy_pagination=true&per_page=100&page=1&key=" . $api_key; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

        </div>

        <script src="https://maxcdn.bootstrapcdn.com/bootstrap/4.0.0/js/bootstrap.min.js" integrity="sha384-JZR6Spejh4U02d8jOt6vLEHfe/JQGiRRSQQxSfFWpi1MquVdAyjUar5+76PVCmYl" crossorigin="anonymous"></script>
		<?php if( $this->getAdminLanguage() == "he_IL" ) { ?>
            <link rel="stylesheet" href="https://cdn.rtlcss.com/bootstrap/v4.0.0/css/bootstrap.min.css" integrity="sha384-P4uhUIGk/q1gaD/NdgkBIl3a6QywJjlsFJFk7SPRdruoGddvRVSwv5qFnvZ73cpz" crossorigin="anonymous">
		<?php } else { ?>
            <link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/4.0.0/css/bootstrap.min.css" integrity="sha384-Gn5384xqQ1aoWXA+058RXPxPg6fy4IWvTNh0E263XmFcJlSAwiGgFAW/dAiS6JXm" crossorigin="anonymous">
		<?php } ?>
        <script src="https://cdnjs.cloudflare.com/ajax/libs/angular.js/1.8.0/angular.min.js"></script>
        <script>
			var flashy = angular.module('flashy',[]);

			flashy.controller('MainController', ['$scope', function($scope) {

				$scope.settings = {
					"allow_text": "<?php echo ( flashy_settings("allow_text") ) ? flashy_settings("allow_text") : _e("I agree to receive promotional emails and text messages.", 'flashy'); ?>",
					"add_checkbox": "<?php echo ( flashy_settings("add_checkbox") != "no" ) ? "yes" : "no"; ?>",
                    "checkbox_marked": "<?php echo ( flashy_settings("checkbox_marked") === "yes" ) ? "yes" : "no"; ?>",
					"accept_marketing": <?php echo ( !flashy_settings("accept_marketing") ) ? "{}" : json_encode(flashy_settings("accept_marketing")); ?>,
					"checkbox_position": <?php echo ( !flashy_settings("checkbox_position") ) ? '{"checkout": "woocommerce_after_order_notes"}' : json_encode(flashy_settings("checkbox_position"), true); ?>,
				};

                $scope.getWooStatuses = function(statuses) {
                    return JSON.stringify(statuses);
                }

				<?php if( $this->isCF7Active() ) { ?>
					$scope.flashy_cf7 = <?php echo json_encode($flashy_cf7); ?>;
				<?php } ?>


                <?php if( isset($woo_statuses) ) { ?>
					$scope.settings.woo_statuses = <?php echo json_encode($woo_statuses); ?>;
				<?php } ?>
			}]);

			let tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'))
			let tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
				return new bootstrap.Tooltip(tooltipTriggerEl)
			})
        </script>
		<?php
	}
		return;
	}
}

/*
*  flashy
*
*  The main function responsible for returning the one true flashy Instance to functions everywhere.
*  Use this function like you would a global variable, except without needing to declare the global.
*
*  Example: <?php $flashy = flashy(); ?>
*
*  @type	function
*  @date	03/12/2015
*  @since	1.0.0
*
*  @param	N/A
*  @return	(object)
*/

function flashy()
{
	global $flashy;

	if( !isset($flashy) )
	{
		$flashy = new wp_flashy();
	}

	return $flashy;
}

function flashy_log($contents, $force = false)
{
    if( get_option('flashy_log_state') === 'true' )
        $force = true;

	if( $force === false && get_option("environment") !== 'dev' )
		return;

	$log = apply_filters('flashy/get_info', 'path') . "/error.log";

	$current = file_get_contents($log);

	if( gettype($contents) == "array" || gettype($contents) == "object" )
		$contents = json_encode($contents);

	$current .= "[" . date('Y-m-d H:i:s') . "] " . $contents . "\n";

	file_put_contents($log, $current);
}

function flashy_log_reset()
{
    $log = apply_filters('flashy/get_info', 'path') . "/error.log";

    $content = "[" . date('Y-m-d H:i:s') . "] Reset Log \n";

    file_put_contents($log, $content);
}

function flashy_dd($v)
{
	echo "<pre>";
	var_dump($v);
	die;
}

function flashy_settings($key = null)
{
    $settings = get_option('flashy_settings');

    if ($key == null)
        return $settings;

    if (isset($settings[$key]))
        return $settings[$key];

    return null;
}

function get_woo_statuses()
{
    $woo_statuses = flashy_settings("woo_statuses");

    if( !$woo_statuses )
	{
        $statuses = wc_get_order_statuses();

        $woo_statuses = [];

        foreach( $statuses as $key => $status )
		{
            if( $key === "wc-pending" || $key === "wc-on-hold" || $key === "wc-checkout-draft" )
			{
                $woo_statuses[$key] = [
                    "label" => $status,
                    "event" => "started_checkout",
                ];
            }
            else if( $key === "wc-processing" )
			{
                $woo_statuses[$key] = [
                    "label" => $status,
                    "event" => "purchase",
                ];
            }
            else
			{
                $woo_statuses[$key] = [
                    "label" => $status,
                    "event" => "purchase_updated",
                ];
            }
        }
    }
    else
	{
        $woo_statuses = json_decode($woo_statuses, true);
    }

    return $woo_statuses;
}

function get_flashy_status($status)
{
	$woo_statuses = get_woo_statuses();

	return $woo_statuses["wc-" . $status];
}

// initialize
flashy();

endif; // class_exists check