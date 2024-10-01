<?php
/**
 * Export WooCommerce Products
 *
 */
class Flashy_Products_Feed
{
    /**
     * The Variable that contain all products.
     *
     * @since    1.0.0
     * @access   private
     * @var      array $productsList Products list array.
     */
    private $productsList = array();

    /**
     * main function to get the products data
     * @param $posts_per_page
     * @param $offset
     * @return array
     */
    public function get($posts_per_page = 100, $offset = 0)
    {
        $args = $this->args($posts_per_page, $offset);

        $query = new WP_Query( $args );

        if ( $query->have_posts() ) {
            while ( $query->have_posts() ) : $query->the_post();
                $this->productsData( get_the_ID() );
            endwhile;
        }

	    return [
            "products" => $this->productsList,
            "total" => $query->found_posts
        ];
    }

    /**
     * method to put arguments in wc query
     * @param $posts_per_page
     * @param $offset
     * @return array
     */
    public function args($posts_per_page, $offset)
    {
        return [
            'post_type'              => array( 'product' ),
		    'post_status'            => 'publish',
		    'posts_per_page'         =>  $posts_per_page,
		    'orderby'                => 'date',
		    'order'                  => 'desc',
		    'fields'                 => 'ids',
		    'offset'                 =>  $offset,
		    'suppress_filters'		 => false,
		    'cache_results'          => false,
		    'update_post_term_cache' => false,
		    'update_post_meta_cache' => false,
        ];
    }

    /**
     * mehod to get all products and sub products
     * @param $product_id
     * @return void
     */
    public function productsData($product_id)
    {
        $product = wc_get_product($product_id);

        if( empty($product) )
            return;

        $this->productsList[] = $this->arrangeFields($product);

        if( $product->has_child() )
        {
            if( $product->get_children() )
            {
                foreach( $product->get_children() as $child )
                {
                   $child_product = wc_get_product($child);

                   if( empty($child_product) )
                       return;

                   $this->productsList[] = $this->arrangeFields($child_product , $product);
                }
            }
        }
    }

    /**
     * method for all fields we need for export
     * @param $product
     * @param $parent
     * @return array
     */
    public function arrangeFields($product, $parent = [])
    {
        $field = [
            'id' => $product->get_id(),
            "title" => $product->get_name(),
            "link" => $product->get_permalink(),
            "feature_image" => $this->childFieldsCheck("feature_image", $product, $parent),
            "description" => $this->childFieldsCheck("description", $product, $parent),
            "price" => $this->getProductPrice($product),
            "sale_price" => $this->getProductSalePrice($product),
            "condition" => "New",
            "product_type" =>  $this->childFieldsCheck("product_type", $product, $parent),
            "variation_type" => $product->get_type(),
            "parent_id" => $product->get_parent_id() ? $product->get_parent_id() : 0,
            "tags" => $this->childFieldsCheck("tags", $product, $parent),
            "sku" => $product->get_sku(),
            "created_at" => $product->get_date_created()->getTimestamp(),
            "updated_at" => $product->get_date_modified()->getTimestamp(),
        ];

		if( $product->get_manage_stock() )
		{
            $field["quantity"] = $product->get_stock_quantity() ? $product->get_stock_quantity() : 0;

			if( $field['quantity'] > 0 )
			{
				$field['availability'] = "in stock";
			}
			else if( $field['quantity'] === 0 && $product->backorders_allowed() )
			{
				$field['quantity'] = 500;
				$field['availability'] = "backorder";
			}
			else
			{
				$field['availability'] = "out of stock";
			}
		}
		else
		{
			if( $product->is_in_stock() )
			{
				$field['availability'] = "in stock";
				$field["quantity"] = 500;
			}
			else if( $product->backorders_allowed() )
			{
				$field["quantity"] = 500;
				$field['availability'] = "backorder";
			}
			else
			{
				$field['availability'] = "out of stock";
			}
		}

        if( $product->has_child() )
        {
            $field["variants"] = $this->getProductVariantsJson($product, ["id", "title"]);

            $field["extra_images"] = $this->getProductImagesToSrc($product);
        }

        return $field;
    }

    /**
     * @param $product
     * @return array
     */
    public function getProductImagesToSrc($product)
    {
        if( wc_get_product($product) )
        {
            $src = [];

            foreach( $product->get_gallery_image_ids() as $image )
            {
                $src[] = wp_get_attachment_image_src($image, 'large') ? wp_get_attachment_image_src($image, 'large')[0] : [];

                if( count($src) == 4 )
                    break;

            }

            return $src;
        }

        return [];
    }

    /**
     * @param $product
     * @param $array
     * @return false|string
     */
    public function getProductVariantsJson($product, $array)
    {
        $data = [];

        foreach( $product->get_children() as $child )
        {
            $child_product = wc_get_product($child);

            $tmp = [];

            if( in_array("id", $array) )
                $tmp["id"] = $child_product->get_id();

            if( in_array("title", $array) )
                $tmp["title"] = $child_product->get_title();
            
            $data[] = $tmp;
        }

        return $data;
    }

    /**
     * @param $product
     * @return mixed
     */
    public function getProductPrice($product)
    {
        $product = $this->getProductOrFirstChild($product);

        return !empty($product->get_regular_price()) ? $product->get_regular_price() : $product->get_price();
    }

    /**
     * @param $product
     * @return string
     */
    public function getProductSalePrice($product)
    {
        $product = $this->getProductOrFirstChild($product);

        return $product->get_sale_price();
    }

    /**
     * @param $product
     * @return false|mixed|WC_Product|null
     */
    public function getProductOrFirstChild($product)
    {
        if( $product->get_children() && is_array($product->get_children()) )
        {
            $firstChild = $product->get_children()[0];

            $product = !empty(wc_get_product($firstChild)) ? wc_get_product($firstChild) : $product;
        }

        return $product;
    }

    /**
     * @param $field
     * @param $product
     * @param $parent
     * @return array|false|string|void
     */
    public function childFieldsCheck($field, $product, $parent)
    {
        if( $field === 'feature_image' )
        {
            if( !empty( $parent ) && get_post_type( $parent ) )
                return wp_get_attachment_url( $product->get_image_id() ) ? wp_get_attachment_url( $product->get_image_id() ) : wp_get_attachment_url( $parent->get_image_id() );

            return wp_get_attachment_url( $product->get_image_id() );
        }
        else if( $field === 'description' )
        {
            if( !empty( $parent ) && get_post_type( $parent ) )
                return !empty(trim(preg_replace('/\s+/', ' ', strip_tags($product->get_description())))) ? trim(preg_replace('/\s+/', ' ', strip_tags($product->get_description()))) : trim(preg_replace('/\s+/', ' ', strip_tags($parent->get_description())));

            return trim(preg_replace('/\s+/', ' ', strip_tags($product->get_description())));
        }
        else if( $field === 'product_type' )
        {
            if( !empty( $parent ) && get_post_type( $parent ) )
                return !empty($this->get_product_term_list( $product->get_id(), 'product_cat', "", ">" )) ? $this->get_product_term_list( $product->get_id(), 'product_cat', "", ">" ) : $this->get_product_term_list( $parent->get_id(), 'product_cat', "", ">" );

            return $this->get_product_term_list( $product->get_id(), 'product_cat', "", ">" );
        }
        else if( $field === 'tags' )
        {
            if( !empty( $parent ) && get_post_type( $parent ) )
                return !empty(wp_get_post_terms( $parent->get_id(), 'product_tag' )) ? array_column(wp_get_post_terms( $parent->get_id(), 'product_tag' ), 'name') : [];

            return !empty(wp_get_post_terms( $product->get_id(), 'product_tag' )) ? array_column(wp_get_post_terms( $product->get_id(), 'product_tag' ), 'name') : [];
        }


    }

    /**
     * method to get the hierarchy of product categories
     * @param $id
     * @param $taxonomy
     * @param $before
     * @param $sep
     * @param $after
     * @return string
     */
    function get_product_term_list($id, $taxonomy, $before = '', $sep = ',', $after = '')
    {
        $terms = get_the_terms($id, $taxonomy);

        if (is_wp_error($terms))
        {
        	return "";
        }

        if (empty($terms)) {
            return "";
        }

        $links = array();

        foreach ($terms as $term) {
            $links[] = $term->name;
        }

        ksort($links);

        return $before . join($sep, $links) . $after;
    }
}