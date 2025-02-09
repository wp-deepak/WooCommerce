<?php
/**
 * === WooCommerce Restaurant Business - WooFood Box Control ===
 */

// Admin: Add checkbox & price field for Food Box in product settings
add_action('woocommerce_product_options_general_product_data', function() {
    echo '<div class="options_group">';
    
    // Checkbox Field: Enable Food Box
    woocommerce_wp_checkbox([
        'id'          => '_enable_food_box',
        'label'       => __('Enable Food Box', 'woocommerce'),
        'description' => __('Check this if the item requires a food box.', 'woocommerce'),
    ]);
    
    // Price Field: Food Box Price (Initially Hidden)
    woocommerce_wp_text_input([
        'id'          => '_food_box_price',
        'label'       => __('Food Box Price', 'woocommerce'),
        'desc_tip'    => 'true',
        'description' => __('Set the price for the food box.', 'woocommerce'),
        'type'        => 'number',
        'custom_attributes' => ['step' => '0.01', 'min' => '0'],
        'wrapper_class' => 'show_if_food_box',
    ]);
    
    echo '</div>';
});

// Save field values
add_action('woocommerce_admin_process_product_object', function($product) {
    $product->update_meta_data('_enable_food_box', isset($_POST['_enable_food_box']) ? 'yes' : 'no');
    $product->update_meta_data('_food_box_price', isset($_POST['_food_box_price']) ? sanitize_text_field($_POST['_food_box_price']) : '');
});

// Admin: Toggle Food Box Price field visibility
add_action('admin_footer', function() {
    global $post;
    if ('product' !== get_post_type($post)) return;
    ?>
    <script>
        jQuery(document).ready(function($) {
            function toggleFoodBoxPrice() {
                $('.show_if_food_box').toggle($('#_enable_food_box').is(':checked'));
            }
            $('#_enable_food_box').change(toggleFoodBoxPrice);
            toggleFoodBoxPrice();
        });
    </script>
    <?php
});

// Frontend: Add Food Box Price to cart as a separate fee
add_action('woocommerce_cart_calculate_fees', function($cart) {
    if (is_admin() && !defined('DOING_AJAX')) return;

    $total_food_box_price = 0;

    foreach ($cart->get_cart() as $cart_item) {
        $product_id = $cart_item['product_id'];
        $enable_food_box = get_post_meta($product_id, '_enable_food_box', true);
        $food_box_price = floatval(get_post_meta($product_id, '_food_box_price', true));

        if ($enable_food_box === 'yes' && $food_box_price > 0) {
            $total_food_box_price += $food_box_price * $cart_item['quantity'];
            $cart_item['food_box_price'] = $food_box_price;
        }
    }

    if ($total_food_box_price > 0) {
        WC()->cart->add_fee(__('Food Box Charge', 'woocommerce'), $total_food_box_price);
    }
});

// Display Food Box Price in Cart & Checkout
add_filter('woocommerce_get_item_data', function($item_data, $cart_item) {
    if (!empty($cart_item['food_box_price'])) {
        $item_data[] = [
            'name'  => __('Food Box Price', 'woocommerce'),
            'value' => wc_price($cart_item['food_box_price'])
        ];
    }
    return $item_data;
}, 10, 2);

// Admin: Add "Food Box Management" submenu
add_action('admin_menu', function() {
    add_submenu_page(
        'woocommerce',
        __('Food Box Management', 'woocommerce'),
        __('Food Box Management', 'woocommerce'),
        'manage_woocommerce',
        'food-box-management',
        'render_food_box_management_page'
    );
});

// Render Food Box Management Page
function render_food_box_management_page() {
    $orders = wc_get_orders([
        'status' => ['processing', 'completed'], 
        'limit'  => 20
    ]);

    echo '<div class="wrap"><h1>' . __('Food Box Management', 'woocommerce') . '</h1>';
    echo '<table class="widefat">
            <thead>
                <tr>
                    <th>Order No.</th>
                    <th>Customer</th>
                    <th>Total Qty</th>
                    <th>Total Price</th>
                    <th>Status</th>
                    <th>Received Date</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>';

    foreach ($orders as $order) {
        $order_id = $order->get_id();
        $customer = $order->get_billing_first_name() . ' ' . $order->get_billing_last_name();
        $status = get_post_meta($order_id, '_food_box_status', true) ?: 'Pending';
        $received_date = get_post_meta($order_id, '_food_box_received_date', true) ?: 'N/A';
        
        if ($order->get_status() === 'completed') {
            $status = 'Received';
            if (empty(get_post_meta($order_id, '_food_box_received_date', true))) {
                update_post_meta($order_id, '_food_box_received_date', current_time('Y-m-d'));
            }
        }

        $total_qty = $total_price = 0;
        foreach ($order->get_items() as $item) {
            $food_box_price = wc_get_order_item_meta($item->get_id(), __('Food Box Price', 'woocommerce'), true);
            if ($food_box_price) {
                $total_qty += $item->get_quantity();
                $total_price += $food_box_price * $item->get_quantity();
            }
        }

        echo "<tr>
                <td>#{$order_id}</td>
                <td>{$customer}</td>
                <td>{$total_qty}</td>
                <td>" . wc_price($total_price) . "</td>
                <td>{$status}</td>
                <td>{$received_date}</td>
                <td>
                    <form method='post'>
                        <input type='hidden' name='food_box_order_id' value='{$order_id}'>
                        <select name='food_box_status'>
                            <option value='Pending' " . selected($status, 'Pending', false) . ">Pending</option>
                            <option value='Received' " . selected($status, 'Received', false) . ">Received</option>
                        </select>
                        <input type='date' name='food_box_received_date' value='{$received_date}'>
                        <input type='submit' name='update_food_box_status' value='Update'>
                    </form>
                </td>
            </tr>";
    }
    echo '</tbody></table></div>';
}

// Save Food Box Management Status
if (!empty($_POST['update_food_box_status']) && !empty($_POST['food_box_order_id'])) {
    update_post_meta(intval($_POST['food_box_order_id']), '_food_box_status', sanitize_text_field($_POST['food_box_status']));
    update_post_meta(intval($_POST['food_box_order_id']), '_food_box_received_date', sanitize_text_field($_POST['food_box_received_date']));
}

?>
