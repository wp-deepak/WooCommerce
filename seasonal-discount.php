<?php
/*
Plugin Name: Seasonal Discount
Description: Simplifies seasonal discounting promotions on your WooCommerce store. Set discounts for the last week of next month effortlessly. Save time by automating discount application for hundreds of products.
Version: 1.0
Author: Shukla Deepak
Author URI: https://www.linkedin.com/in/imshukladeepak/
License: GPL-2.0-or-later
License URI: https://www.gnu.org/licenses/gpl-2.0.html
Text Domain: woocommerce-seasonal-discount
*/

// Add Seasonal Discount submenu under WooCommerce menu.
if(!function_exists('add_seasonal_discount_submenu')){

    function add_seasonal_discount_submenu() {
        add_submenu_page(
            'woocommerce',
            'Seasonal Discount',
            'Seasonal Discount',
            'manage_options',
            'seasonal-discount',
            'render_seasonal_discount_settings_page'
        );
    }
    add_action('admin_menu', 'add_seasonal_discount_submenu');
}

// Initialize seasonal discount settings.
if(!function_exists('initialize_seasonal_discount_settings')){
   
    function initialize_seasonal_discount_settings() {
        register_setting( 'seasonal_discount_settings_group', 'seasonal_discount_settings' );

        add_settings_section(
            'seasonal_discount_settings_section', 
            'Seasonal Discount Settings', 
            'render_seasonal_discount_settings_section', 
            'seasonal_discount_settings' 
        );

        add_settings_field(
            'discount_percentage',
            'Discount Percentage', 
            'render_discount_field', 
            'seasonal_discount_settings', 
            'seasonal_discount_settings_section' 
        );

        add_settings_field(
            'sale_period', 
            'Sale Period', 
            'render_sale_period_field', 
            'seasonal_discount_settings', 
            'seasonal_discount_settings_section' 
        );
    }
    add_action('admin_init', 'initialize_seasonal_discount_settings');
}

// Render callback seasonal discount settings page.
if(!function_exists('render_seasonal_discount_settings_page')){
    
    function render_seasonal_discount_settings_page() {

        if (isset($_POST['reset_seasonal_discount_settings']) && $_POST['reset_seasonal_discount_settings'] == 1) {
            delete_option('seasonal_discount_settings');
            wp_safe_redirect(admin_url('admin.php?page=seasonal-discount'));
            exit;
        }

        $options = get_option('seasonal_discount_settings');
        $start_date = isset($options['start_date']) ? $options['start_date'] : '';
        $end_date = isset($options['end_date']) ? $options['end_date'] : '';
        $discount_percentage = isset($options['discount_percentage']) ? $options['discount_percentage'] : '';
        $discount_type = isset($options['discount_type']) ? $options['discount_type'] : 'cart'; 

        ?>
        <div class="wrap">
            <h2>Seasonal Discount Settings</h2><br/>
            <form method="post" action="options.php">
                <label for="start_date"><strong>Seasonal Discount Start Date: </strong></label>
                <input type="date" id="start_date" name="seasonal_discount_settings[start_date]" value="<?= esc_attr($start_date); ?>" /><br><br>

                <label for="end_date"><strong>Seasonal Discount End Date: </strong></label>
                <input type="date" id="end_date" name="seasonal_discount_settings[end_date]" value="<?= esc_attr($end_date); ?>" /><br><br>

                <label for="discount_percentage"><strong>Discount Percentage:</strong></label>
                <input type="number" id="discount_percentage" name="seasonal_discount_settings[discount_percentage]" value="<?= esc_attr($discount_percentage); ?>" min="0" max="100" />%<br><br>

                <label for="discount_type"><strong>Discount Type:</strong></label><br>
                <input type="radio" id="cart_discount" name="seasonal_discount_settings[discount_type]" value="cart" <?= checked($discount_type, 'cart'); ?>>
                <label for="cart_discount">Cart Discount</label><br>
                <input type="radio" id="product_discount" name="seasonal_discount_settings[discount_type]" value="product" <?= checked($discount_type, 'product'); ?>>
                <label for="product_discount">Product Discount</label><br><br>

                <?php
                settings_fields('seasonal_discount_settings_group');
                submit_button();
                ?>
            </form>
        </div>
        <script>
            document.addEventListener("DOMContentLoaded", function() {
                // Get start date and end date elements
                var startDateInput = document.getElementById("start_date");
                var endDateInput = document.getElementById("end_date");

                // Set minimum value for start date to today
                var today = new Date().toISOString().split('T')[0];
                startDateInput.min = today;

                // Add event listener to end date input to ensure it's greater than start date
                endDateInput.addEventListener('input', function() {
                    if (endDateInput.value <= startDateInput.value) {
                        endDateInput.setCustomValidity('End date must be greater than start date');
                    } else {
                        endDateInput.setCustomValidity('');
                    }
                });

                // Add event listener to start date input to ensure it's different from end date
                startDateInput.addEventListener('input', function() {
                    if (startDateInput.value >= endDateInput.value) {
                        startDateInput.setCustomValidity('Start date must be before end date');
                    } else {
                        startDateInput.setCustomValidity('');
                    }
                });
            });
        </script>
        <?php
    }
}

// Display discount message before WooCommerce products header for displaying discount message.
if(!function_exists('display_discount_message')){
   
    function display_discount_message() {
         $start_date = strtotime(get_option('seasonal_discount_settings')['start_date'] ?? '');
         $discount_percentage = get_option('seasonal_discount_settings')['discount_percentage'] ?? '';

        if ($start_date && strtotime(date('Y-m-d')) >= $start_date && $discount_percentage) {
           echo '<div class="discount-message" style="padding: 10px; max-width: 100%; background: bisque; font-weight: 400; font-size: larger; text-align: center; margin: 20px;">Hurry up! Festival season ' . $discount_percentage . '% flat discount on each transaction.</div>';
        }
    }
    add_action('woocommerce_before_main_content', 'display_discount_message', 10);
}

// Hook for displaying in both cart and checkout
if(!function_exists('display_discount_product_before_order_total')){
    
    function display_discount_product_before_order_total() {
        $options = get_option('seasonal_discount_settings');
        $discount_percentage = $options['discount_percentage'] ?? 0;
        $discount_type = $options['discount_type'] ?? 'cart'; 
        $start_date = strtotime($options['start_date'] ?? '');
        $end_date = strtotime($options['end_date'] ?? '');
        $current_date = strtotime(date('Y-m-d'));

        // Ensure discount is active
        if ($discount_percentage > 0 && $start_date && $current_date >= $start_date && $current_date <= $end_date) {
            $discount_label = ($discount_type === 'cart') ? 'Cart Discount Applied' : 'Product Discount Applied';
            
            echo '<tr class="order-discount-product">';
            echo '<th>' . esc_html($discount_label) . '</th>';
            echo '<td style="color: green; font-weight: bold;">-' . sprintf(__('%d%% OFF', 'woocommerce'), $discount_percentage) . '</td>';
            echo '</tr>';
        }
    }

    // Hook for displaying in both cart and checkout
    add_action('woocommerce_review_order_before_order_total', 'display_discount_product_before_order_total');
    add_action('woocommerce_cart_totals_before_order_total', 'display_discount_product_before_order_total');
}

// Display message in cart checkout total section woocommerce_cart_calculate_fees
if(!function_exists('apply_discount_percentage_and_update_order_price')){
    
    function apply_discount_percentage_and_update_order_price($cart) {
        if (is_admin() && !defined('DOING_AJAX')) return;

        $options = get_option('seasonal_discount_settings');
        $discount_percentage = $options['discount_percentage'] ?? 0;
        $discount_type = $options['discount_type'] ?? 'cart'; 
        $start_date = strtotime($options['start_date'] ?? '');
        $end_date = strtotime($options['end_date'] ?? '');
        $current_date = strtotime(date('Y-m-d'));

        if ($start_date && $end_date && $current_date >= $start_date && $current_date <= $end_date) {
            $subtotal = $cart->subtotal;
            $discount_amount = ($subtotal * $discount_percentage) / 100;

            if ($discount_amount > 0) {
                $cart->add_fee(__('Seasonal Discount', 'woocommerce'), -$discount_amount, true);
            }
        }
    }
    
    add_action('woocommerce_cart_calculate_fees', 'apply_discount_percentage_and_update_order_price');
}
