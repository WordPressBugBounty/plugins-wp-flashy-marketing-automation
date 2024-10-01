<?php

function flashy_tracking()
{
	$flashy_id 	= get_option('flashy_account_id');
	$thunder_path = 'https://js.flashyapp.com/thunder.js';

	if(get_option("environment") === 'dev')
	{
		$thunder_path = 'https://js.flashy.dev/thunder.js';
    }

	if(isset($flashy_id) && $flashy_id != '')
	{
		?>
            <script>
                window.flashyMetadata = {"platform": "WordPress","version": "2.0.8"};
                console.log("Flashy Init", flashyMetadata);
            </script>
			<script>'use strict'; (function (a, b, c) { if (!a.flashy) { a.flashy = function () { a.flashy.event && a.flashy.event(arguments), a.flashy.queue.push(arguments) }, a.flashy.queue = []; var d = document.getElementsByTagName('script')[0], e = document.createElement(b); e.src = c, e.async = !0, d.parentNode.insertBefore(e, d) } })(window, 'script', '<?= $thunder_path ?>'), flashy('init', <?= $flashy_id; ?>);</script>
			<script>
				<?php if( function_exists("is_product") && is_product() ) {
                    $product_obj = wc_get_product(wc_get_product()->get_id());
                    $product_id = !empty($product_obj->get_children()[0]) ? $product_obj->get_children()[0] : wc_get_product()->get_id();
                    ?>
					flashy('ViewContent', {"content_ids": ["<?php echo $product_id; ?>"]});
				<?php } else if( function_exists("is_product_category") && is_product_category() ) {
                    $category = get_queried_object();
                    ?>
                    flashy('amplify:ViewCategory', {"category": "<?php echo $category->name; ?>"});
				<?php } else { ?>
					flashy('PageView');
				<?php } ?>
			</script>
		<?php
	}
}
add_action('wp_head', 'flashy_tracking');


function flashy_global_category_list()
{
   if( is_admin() ) return false;

   global $woocommerce;

   $categories = [];

   $items = $woocommerce->cart->get_cart();

   foreach( $items as $item )
   {
       $cat_names = get_the_terms($item['product_id'], 'product_cat');

       foreach( $cat_names as $cat_name )
       {
           if( $cat_name->parent )
           {
               $cat_name->name = get_the_category_by_ID($cat_name->parent) . "_" . $cat_name->name;
           }

           if( !in_array($cat_name->name, $categories) )
           {
               $categories[] = trim($cat_name->name);
           }
       }
   }

   if ( !empty($categories) ) { ?>
        <div id="flashy_categories" style="display:none;">
            <?php echo 'category:' . implode(', category:', $categories); ?>
        </div>
        <?php
   }
}

//if( is_plugin_active('woocommerce/woocommerce.php') )
//    add_action('wp_footer', 'flashy_global_category_list');
