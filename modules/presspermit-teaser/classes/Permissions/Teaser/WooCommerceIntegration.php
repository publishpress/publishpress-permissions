<?php
namespace PublishPress\Permissions\Teaser;

/**
 * WooCommerce Teaser Integration
 * 
 * Handles hiding WooCommerce-specific elements (images, prices, add to cart, etc.) 
 * for teased products on the frontend.
 */
class WooCommerceIntegration
{
    function __construct()
    {
        if (!class_exists('WooCommerce')) {
            return;
        }

        // Hide product images and galleries
        add_filter('woocommerce_single_product_image_thumbnail_html', [$this, 'hideProductGallery'], 10, 2);
        add_filter('woocommerce_product_get_image', [$this, 'hideProductImage'], 10, 5);
        add_filter('woocommerce_product_get_gallery_image_ids', [$this, 'hideGalleryIds'], 10, 2);
        add_filter('post_thumbnail_html', [$this, 'hideTeasedThumbnail'], 10, 5);
        add_filter('woocommerce_placeholder_img_src', [$this, 'hidePlaceholderImage'], 10, 1);
        
        // Hide product links
        add_filter('woocommerce_loop_product_link', [$this, 'hideProductLink'], 10, 2);
        
        // Hide product details
        add_filter('woocommerce_product_get_price_html', [$this, 'hideProductPrice'], 999, 2);
        add_filter('woocommerce_is_purchasable', [$this, 'disablePurchaseForTeased'], 999, 2);
        add_filter('woocommerce_product_get_stock_status', [$this, 'hideStockStatus'], 10, 2);
        add_filter('woocommerce_product_is_in_stock', [$this, 'hideInStock'], 10, 2);
        add_filter('woocommerce_get_stock_html', [$this, 'hideStockHtml'], 10, 2);
        
        // Remove add to cart button and form via template hooks
        add_action('wp', [$this, 'maybeRemoveWooCommerceElements'], 20);
    }

    /**
     * Check if current product is being teased
     */
    private function isProductTeased($product_id = null)
    {
        if (!$product_id) {
            global $post;
            $product_id = $post ? $post->ID : 0;
        }

        if (!$product_id) {
            return false;
        }

        // Check if it's a product
        if (get_post_type($product_id) !== 'product') {
            return false;
        }

        // Check if it's being teased
        return \PublishPress\Permissions\Teaser::instance()->isTeaser($product_id);
    }

    /**
     * Remove WooCommerce template elements for teased products
     */
    function maybeRemoveWooCommerceElements()
    {
        if (!is_singular('product')) {
            return;
        }

        global $post;
        
        if (!$post || !$this->isProductTeased($post->ID)) {
            return;
        }

        // Remove price
        remove_action('woocommerce_single_product_summary', 'woocommerce_template_single_price', 10);
        
        // Remove excerpt (short description)
        remove_action('woocommerce_single_product_summary', 'woocommerce_template_single_excerpt', 20);
        
        // Remove add to cart button
        remove_action('woocommerce_single_product_summary', 'woocommerce_template_single_add_to_cart', 30);
        
        // Remove product meta (SKU, categories, tags)
        remove_action('woocommerce_single_product_summary', 'woocommerce_template_single_meta', 40);
        
        // Remove sharing buttons if present
        remove_action('woocommerce_single_product_summary', 'woocommerce_template_single_sharing', 50);
    }

    /**
     * Hide product gallery thumbnails
     */
    function hideProductGallery($html, $attachment_id)
    {
        if ($this->isProductTeased() && presspermit()->getOption('teaser_hide_thumbnail')) {
            return '';
        }
        
        return $html;
    }

    /**
     * Hide main product image
     */
    function hideProductImage($image, $product, $size, $attr, $placeholder)
    {
        $product_id = is_numeric($product) ? $product : (is_object($product) ? $product->get_id() : 0);
        
        if ($this->isProductTeased($product_id) && presspermit()->getOption('teaser_hide_thumbnail')) {
            return '';
        }
        
        return $image;
    }

    /**
     * Hide product gallery IDs
     */
    function hideGalleryIds($gallery_ids, $product)
    {
        $product_id = is_numeric($product) ? $product : (is_object($product) ? $product->get_id() : 0);
        
        if ($this->isProductTeased($product_id) && presspermit()->getOption('teaser_hide_thumbnail')) {
            return [];
        }
        
        return $gallery_ids;
    }

    /**
     * Hide post thumbnail for WooCommerce products
     */
    function hideTeasedThumbnail($html, $post_id, $post_thumbnail_id, $size, $attr)
    {
        if (!$post_id) {
            $post_id = get_the_ID();
        }
        
        if ($this->isProductTeased($post_id) && presspermit()->getOption('teaser_hide_thumbnail')) {
            return '';
        }
        
        return $html;
    }

    /**
     * Hide placeholder image
     */
    function hidePlaceholderImage($src)
    {
        if ($this->isProductTeased() && presspermit()->getOption('teaser_hide_thumbnail')) {
            // Return transparent 1x1 pixel
            return 'data:image/gif;base64,R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7';
        }
        
        return $src;
    }

    /**
     * Hide product link in loops
     */
    function hideProductLink($link, $product)
    {
        $product_id = is_numeric($product) ? $product : (is_object($product) ? $product->get_id() : 0);
        
        if ($this->isProductTeased($product_id)) {
            return '#';
        }
        
        return $link;
    }

    /**
     * Hide product price
     */
    function hideProductPrice($price, $product)
    {
        $product_id = is_numeric($product) ? $product : (is_object($product) ? $product->get_id() : 0);
        
        if ($this->isProductTeased($product_id)) {
            return '';
        }
        
        return $price;
    }

    /**
     * Disable purchase for teased products
     */
    function disablePurchaseForTeased($purchasable, $product)
    {
        $product_id = is_numeric($product) ? $product : (is_object($product) ? $product->get_id() : 0);
        
        if ($this->isProductTeased($product_id)) {
            return false;
        }
        
        return $purchasable;
    }

    /**
     * Hide stock status
     */
    function hideStockStatus($status, $product)
    {
        $product_id = is_numeric($product) ? $product : (is_object($product) ? $product->get_id() : 0);
        
        if ($this->isProductTeased($product_id)) {
            return '';
        }
        
        return $status;
    }

    /**
     * Hide in stock indicator
     */
    function hideInStock($in_stock, $product)
    {
        $product_id = is_numeric($product) ? $product : (is_object($product) ? $product->get_id() : 0);
        
        if ($this->isProductTeased($product_id)) {
            return false;
        }
        
        return $in_stock;
    }

    /**
     * Hide stock HTML output
     */
    function hideStockHtml($html, $product)
    {
        $product_id = is_numeric($product) ? $product : (is_object($product) ? $product->get_id() : 0);
        
        if ($this->isProductTeased($product_id)) {
            return '';
        }
        
        return $html;
    }
}
