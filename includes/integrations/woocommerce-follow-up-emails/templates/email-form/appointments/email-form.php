<div class="options_group wc-appointments-selector">
    <p class="form-field">
        <label for="storewide_type"><?php esc_html_e('Enable for', 'woocommerce-appointments'); ?></label>
        <select name="meta[storewide_type]" id="storewide_type" class="">
            <option value="all" <?php selected( $storewide_type, 'all' ); ?>><?php esc_html_e('All products', 'woocommerce-appointments'); ?></option>
            <option value="products" <?php selected( $storewide_type, 'products' ); ?>><?php esc_html_e('A specific product', 'woocommerce-appointments'); ?></option>
            <option value="categories" <?php selected( $storewide_type, 'categories' ); ?>><?php esc_html_e('A specific category', 'woocommerce-appointments'); ?></option>
        </select>
        <input type="hidden" name="storewide_type" id="storewide_type_hidden" disabled value="" />
    </p>

    <div class="non-signup reminder hideable <?php do_action('fue_form_product_tr_class', $email); ?> product_tr">
        <p class="form-field">
            <label for="product_ids"><?php esc_html_e('Product', 'woocommerce-appointments'); ?></label>
            <?php
            $product_id   = (!empty($email->product_id)) ? $email->product_id : '';
            $product_name = '';

            if ( !empty( $product_id ) ) {
                $product = WC_FUE_Compatibility::wc_get_product( $product_id );

                if ( $product ) {
                    $product_name   = wp_kses_post( $product->get_formatted_name() );
                }
            }
            ?>
            <select class="wc-product-search" id="product_id" name="product_id" data-placeholder="<?php esc_html_e( 'N/A', 'woocommerce-appointments' ); ?>" data-action="woocommerce_json_search_appointable_products">
                <option value="<?php esc_attr_e( $product_id ); ?>" selected="selected"><?php esc_html_e( $product_name ); ?></option>';
            </select>
        </p>

        <?php
        $display = 'display: none;';

        if ($has_variations)
            $display = 'display: inline-block;';
        ?>
        <p class="form-field product_include_variations" style="<?php echo $display; ?>">
            <input type="checkbox" name="meta[include_variations]" id="include_variations" value="yes" <?php if (isset($email->meta['include_variations']) && $email->meta['include_variations'] == 'yes') echo 'checked'; ?> />
            <label for="include_variations" class="inline"><?php esc_html_e('Include variations', 'woocommerce-appointments'); ?></label>
        </p>
    </div>

    <div class="non-signup reminder hideable <?php do_action('fue_form_category_tr_class', $email); ?> category_tr">
        <p class="form-field">
            <label for="category_id"><?php esc_html_e('Category', 'woocommerce-appointments'); ?></label>

            <select id="category_id" name="category_id" class="select2" data-placeholder="<?php esc_html_e('Search for a category&hellip;', 'woocommerce-appointments'); ?>">
                <option value="0"><?php esc_html_e('Any Category', 'woocommerce-appointments'); ?></option>
                <?php
                foreach ($categories as $category):
                    ?>
                    <option value="<?php esc_html_e($category->term_id); ?>" <?php selected( $email->category_id, $category->term_id ); ?>><?php esc_html_e($category->name); ?></option>
                <?php endforeach; ?>
            </select>
        </p>
    </div>
</div>
