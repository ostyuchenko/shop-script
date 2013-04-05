<?php
return array(
    'shop_affiliate_transaction' => array(
        'id' => array('int', 11, 'unsigned' => 1, 'null' => 0, 'autoincrement' => 1),
        'contact_id' => array('int', 11, 'unsigned' => 1, 'null' => 0),
        'create_datetime' => array('datetime', 'null' => 0),
        'order_id' => array('int', 11, 'unsigned' => 1),
        'amount' => array('decimal', "15,4", 'null' => 0),
        'balance' => array('decimal', "15,4", 'null' => 0),
        'comment' => array('text'),
        ':keys' => array(
            'PRIMARY' => 'id',
            'contact_id' => 'contact_id',
        ),
    ),
    'shop_cart_items' => array(
        'id' => array('int', 11, 'null' => 0, 'autoincrement' => 1),
        'code' => array('varchar', 32),
        'contact_id' => array('int', 11),
        'product_id' => array('int', 11, 'null' => 0),
        'sku_id' => array('int', 11, 'null' => 0),
        'create_datetime' => array('datetime', 'null' => 0),
        'quantity' => array('int', 11, 'null' => 0, 'default' => '1'),
        'type' => array('enum', "'product','service'", 'null' => 0, 'default' => 'product'),
        'service_id' => array('int', 11),
        'service_variant_id' => array('int', 11),
        'parent_id' => array('int', 11),
        ':keys' => array(
            'PRIMARY' => 'id',
            'code' => 'code',
        ),
    ),
    'shop_category' => array(
        'id' => array('int', 11, 'null' => 0, 'autoincrement' => 1),
        'left_key' => array('int', 11),
        'right_key' => array('int', 11),
        'depth' => array('int', 11, 'null' => 0, 'default' => '0'),
        'parent_id' => array('int', 11, 'null' => 0, 'default' => '0'),
        'name' => array('varchar', 255),
        'meta_title' => array('varchar', 255),
        'meta_keywords' => array('text'),
        'meta_description' => array('text'),
        'type' => array('int', 1, 'null' => 0, 'default' => '0'),
        'url' => array('varchar', 255),
        'full_url' => array('varchar', 255),
        'count' => array('int', 11, 'null' => 0, 'default' => '0'),
        'description' => array('text'),
        'conditions' => array('text'),
        'create_datetime' => array('datetime', 'null' => 0),
        'edit_datetime' => array('datetime'),
        'filter' => array('text'),
        'sort_products' => array('varchar', 32),
        'include_sub_categories' => array('tinyint', 1, 'null' => 0, 'default' => '0'),
        'status' => array('tinyint', 1, 'null' => 0, 'default' => '1'),
        ':keys' => array(
            'PRIMARY' => 'id',
            'url' => array('parent_id', 'url', 'unique' => 1),
            'full_url' => array('full_url', 'unique' => 1),
            'parent_id' => 'parent_id',
            'left_key' => 'left_key',
            'right_key' => 'right_key',
        ),
    ),
    'shop_category_params' => array(
        'category_id' => array('int', 11, 'null' => 0),
        'name' => array('varchar', 255, 'null' => 0),
        'value' => array('text', 'null' => 0),
        ':keys' => array(
            'PRIMARY' => array('category_id', 'name'),
        ),
    ),
    'shop_category_products' => array(
        'product_id' => array('int', 11, 'null' => 0),
        'category_id' => array('int', 11, 'null' => 0),
        'sort' => array('int', 11, 'null' => 0, 'default' => '0'),
        ':keys' => array(
            'PRIMARY' => array('category_id', 'product_id'),
        ),
    ),
    'shop_contact_category_discount' => array(
        'category_id' => array('int', 10, 'unsigned' => 1, 'null' => 0),
        'discount' => array('decimal', "15,4", 'null' => 0),
        ':keys' => array(
            'PRIMARY' => 'category_id',
        ),
    ),
    'shop_coupon' => array(
        'id' => array('int', 10, 'unsigned' => 1, 'null' => 0, 'autoincrement' => 1),
        'code' => array('varchar', 32, 'null' => 0),
        'type' => array('varchar', 3, 'null' => 0),
        'limit' => array('int', 11),
        'used' => array('int', 11, 'null' => 0, 'default' => '0'),
        'value' => array('decimal', "15,4"),
        'comment' => array('text'),
        'expire_datetime' => array('datetime'),
        'create_datetime' => array('datetime', 'null' => 0),
        'create_contact_id' => array('int', 11, 'unsigned' => 1, 'null' => 0, 'default' => '0'),
        ':keys' => array(
            'PRIMARY' => 'id',
            'code' => array('code', 'unique' => 1),
        ),
    ),
    'shop_currency' => array(
        'code' => array('char', 3, 'null' => 0),
        'rate' => array('decimal', "15,8", 'null' => 0, 'default' => '1.00000000'),
        'sort' => array('int', 11, 'null' => 0, 'default' => '0'),
        ':keys' => array(
            'PRIMARY' => 'code',
        ),
    ),
    'shop_customer' => array(
        'contact_id' => array('int', 11, 'unsigned' => 1, 'null' => 0),
        'total_spent' => array('decimal', "15,4", 'null' => 0, 'default' => '0.0000'),
        'affiliate_bonus' => array('decimal', "15,4", 'null' => 0, 'default' => '0.0000'),
        'number_of_orders' => array('int', 11, 'unsigned' => 1, 'null' => 0, 'default' => '0'),
        'last_order_id' => array('int', 11, 'unsigned' => 1),
        ':keys' => array(
            'PRIMARY' => 'contact_id',
        ),
    ),
    'shop_discount_by_sum' => array(
        'type' => array('varchar', 32, 'null' => 0),
        'sum' => array('decimal', "15,4", 'null' => 0),
        'discount' => array('decimal', "15,4", 'null' => 0),
        ':keys' => array(
        ),
    ),
    'shop_feature' => array(
        'id' => array('int', 11, 'null' => 0, 'autoincrement' => 1),
        'code' => array('varchar', 64, 'null' => 0),
        'status' => array('enum', "'public','hidden','private'", 'null' => 0, 'default' => 'public'),
        'name' => array('varchar', 255),
        'type' => array('varchar', 255),
        'selectable' => array('int', 11, 'null' => 0),
        'multiple' => array('int', 11, 'null' => 0),
        ':keys' => array(
            'PRIMARY' => 'id',
            'code' => array('code', 'unique' => 1),
        ),
    ),
    'shop_feature_values_dimension' => array(
        'id' => array('int', 11, 'null' => 0, 'autoincrement' => 1),
        'feature_id' => array('int', 11, 'null' => 0),
        'sort' => array('int', 11, 'null' => 0, 'default' => '0'),
        'value' => array('double', 'null' => 0),
        'unit' => array('varchar', 255, 'null' => 0),
        'type' => array('varchar', 16, 'null' => 0),
        'value_base_unit' => array('double', 'null' => 0),
        ':keys' => array(
            'PRIMARY' => 'id',
            'feature_id' => array('feature_id', 'value', 'unit', 'type', 'unique' => 1),
        ),
    ),
    'shop_feature_values_double' => array(
        'id' => array('int', 11, 'null' => 0, 'autoincrement' => 1),
        'feature_id' => array('int', 11, 'null' => 0),
        'sort' => array('int', 11, 'null' => 0, 'default' => '0'),
        'value' => array('double', 'null' => 0),
        ':keys' => array(
            'PRIMARY' => 'id',
            'values' => array('feature_id', 'value', 'unique' => 1),
        ),
    ),
    'shop_feature_values_text' => array(
        'id' => array('int', 11, 'null' => 0, 'autoincrement' => 1),
        'feature_id' => array('int', 11, 'null' => 0),
        'sort' => array('int', 11, 'null' => 0, 'default' => '0'),
        'value' => array('text', 'null' => 0),
        ':keys' => array(
            'PRIMARY' => 'id',
        ),
    ),
    'shop_feature_values_varchar' => array(
        'id' => array('int', 11, 'null' => 0, 'autoincrement' => 1),
        'feature_id' => array('int', 11, 'null' => 0),
        'sort' => array('int', 11, 'null' => 0, 'default' => '0'),
        'value' => array('varchar', 255, 'null' => 0),
        ':keys' => array(
            'PRIMARY' => 'id',
            'values' => array('feature_id', 'value', 'unique' => 1),
        ),
    ),
    'shop_followup' => array(
        'id' => array('int', 10, 'unsigned' => 1, 'null' => 0, 'autoincrement' => 1),
        'name' => array('varchar', 255, 'null' => 0),
        'delay' => array('int', 10, 'unsigned' => 1, 'null' => 0),
        'first_order_only' => array('tinyint', 3, 'unsigned' => 1, 'null' => 0, 'default' => '1'),
        'subject' => array('text', 'null' => 0),
        'body' => array('text', 'null' => 0),
        'last_cron_time' => array('datetime', 'null' => 0),
        ':keys' => array(
            'PRIMARY' => 'id',
        ),
    ),
    'shop_notification' => array(
        'id' => array('int', 11, 'null' => 0, 'autoincrement' => 1),
        'name' => array('varchar', 128, 'null' => 0),
        'event' => array('varchar', 64, 'null' => 0),
        'transport' => array('enum', "'email','sms','http'", 'null' => 0, 'default' => 'email'),
        ':keys' => array(
            'PRIMARY' => 'id',
            'event' => 'event',
        ),
    ),
    'shop_notification_params' => array(
        'notification_id' => array('int', 11, 'null' => 0),
        'name' => array('varchar', 64, 'null' => 0),
        'value' => array('text', 'null' => 0),
        ':keys' => array(
            'PRIMARY' => array('notification_id', 'name'),
        ),
    ),
    'shop_order' => array(
        'id' => array('int', 11, 'null' => 0, 'autoincrement' => 1),
        'contact_id' => array('int', 11),
        'create_datetime' => array('datetime', 'null' => 0),
        'update_datetime' => array('datetime'),
        'state_id' => array('varchar', 32, 'null' => 0, 'default' => 'new'),
        'total' => array('decimal', "15,4", 'null' => 0),
        'currency' => array('char', 3, 'null' => 0),
        'rate' => array('decimal', "15,8", 'null' => 0, 'default' => '1.00000000'),
        'tax' => array('decimal', "15,4", 'null' => 0, 'default' => '0.0000'),
        'shipping' => array('decimal', "15,4", 'null' => 0, 'default' => '0.0000'),
        'discount' => array('decimal', "15,4", 'null' => 0, 'default' => '0.0000'),
        'assigned_contact_id' => array('int', 11),
        'paid_year' => array('smallint', 6),
        'paid_quarter' => array('smallint', 6),
        'paid_month' => array('smallint', 6),
        'paid_date' => array('date'),
        'is_first' => array('tinyint', 1, 'null' => 0, 'default' => '0'),
        'comment' => array('text'),
        ':keys' => array(
            'PRIMARY' => 'id',
            'state_id' => 'state_id',
            'contact_id' => 'contact_id',
        ),
    ),
    'shop_order_items' => array(
        'id' => array('int', 11, 'null' => 0, 'autoincrement' => 1),
        'order_id' => array('int', 11, 'null' => 0),
        'name' => array('varchar', 255, 'null' => 0),
        'product_id' => array('int', 11, 'null' => 0),
        'sku_id' => array('int', 11, 'null' => 0),
        'sku_code' => array('varchar', 255, 'null' => 0, 'default' => ''),
        'type' => array('enum', "'product','service'", 'null' => 0),
        'service_id' => array('int', 11),
        'service_variant_id' => array('int', 11),
        'price' => array('decimal', "15,4", 'null' => 0),
        'quantity' => array('int', 11, 'null' => 0),
        'parent_id' => array('int', 11),
        'stock_id' => array('int', 11),
        'purchase_price' => array('decimal', "15,4", 'null' => 0, 'default' => '0.0000'),
        ':keys' => array(
            'PRIMARY' => 'id',
        ),
    ),
    'shop_order_log' => array(
        'id' => array('int', 11, 'null' => 0, 'autoincrement' => 1),
        'order_id' => array('int', 11, 'null' => 0),
        'contact_id' => array('int', 11),
        'action_id' => array('varchar', 32, 'null' => 0),
        'datetime' => array('datetime', 'null' => 0),
        'before_state_id' => array('varchar', 16, 'null' => 0),
        'after_state_id' => array('varchar', 16, 'null' => 0),
        'text' => array('text', 'null' => 0),
        ':keys' => array(
            'PRIMARY' => 'id',
        ),
    ),
    'shop_order_log_params' => array(
        'id' => array('int', 11, 'null' => 0, 'autoincrement' => 1),
        'order_id' => array('int', 11, 'null' => 0),
        'log_id' => array('int', 11, 'null' => 0),
        'name' => array('varchar', 255, 'null' => 0),
        'value' => array('text', 'null' => 0),
        ':keys' => array(
            'PRIMARY' => 'id',
            'name' => array('order_id', 'log_id', 'name', 'unique' => 1),
        ),
    ),
    'shop_order_params' => array(
        'order_id' => array('int', 11, 'null' => 0),
        'name' => array('varchar', 64, 'null' => 0),
        'value' => array('varchar', 255, 'null' => 0),
        ':keys' => array(
            'PRIMARY' => array('order_id', 'name'),
        ),
    ),
    'shop_page' => array(
        'id' => array('int', 11, 'null' => 0, 'autoincrement' => 1),
        'parent_id' => array('int', 11),
        'domain' => array('varchar', 255),
        'route' => array('varchar', 255),
        'name' => array('varchar', 255, 'null' => 0),
        'title' => array('varchar', 255, 'null' => 0, 'default' => ''),
        'url' => array('varchar', 255),
        'full_url' => array('varchar', 255),
        'content' => array('text', 'null' => 0),
        'create_datetime' => array('datetime', 'null' => 0),
        'update_datetime' => array('datetime', 'null' => 0),
        'create_contact_id' => array('int', 11, 'null' => 0),
        'sort' => array('int', 11, 'null' => 0, 'default' => '0'),
        'status' => array('tinyint', 1, 'null' => 0, 'default' => '0'),
        ':keys' => array(
            'PRIMARY' => 'id',
        ),
    ),
    'shop_page_params' => array(
        'page_id' => array('int', 11, 'null' => 0),
        'name' => array('varchar', 255, 'null' => 0),
        'value' => array('text', 'null' => 0),
        ':keys' => array(
            'PRIMARY' => array('page_id', 'name'),
        ),
    ),
    'shop_plugin' => array(
        'id' => array('int', 11, 'null' => 0, 'autoincrement' => 1),
        'type' => array('varchar', 255, 'null' => 0),
        'plugin' => array('varchar', 255, 'null' => 0),
        'name' => array('varchar', 255, 'null' => 0),
        'description' => array('text', 'null' => 0),
        'logo' => array('text', 'null' => 0),
        'status' => array('int', 11, 'null' => 0),
        'sort' => array('int', 11, 'null' => 0),
        ':keys' => array(
            'PRIMARY' => 'id',
            'type' => 'type',
        ),
    ),
    'shop_plugin_settings' => array(
        'id' => array('int', 11, 'null' => 0),
        'name' => array('varchar', 64, 'null' => 0),
        'value' => array('text', 'null' => 0),
        ':keys' => array(
            'PRIMARY' => array('id', 'name'),
        ),
    ),
    'shop_product' => array(
        'id' => array('int', 11, 'null' => 0, 'autoincrement' => 1),
        'name' => array('varchar', 255),
        'summary' => array('text'),
        'meta_title' => array('varchar', 255),
        'meta_keywords' => array('text'),
        'meta_description' => array('text'),
        'description' => array('text'),
        'contact_id' => array('int', 11),
        'create_datetime' => array('datetime', 'null' => 0),
        'edit_datetime' => array('datetime'),
        'status' => array('tinyint', 1, 'null' => 0, 'default' => '1'),
        'type_id' => array('int', 11),
        'image_id' => array('int', 11),
        'sku_id' => array('int', 11),
        'ext' => array('varchar', 10),
        'url' => array('varchar', 255),
        'rating' => array('decimal', "3,2", 'null' => 0, 'default' => '0.00'),
        'price' => array('decimal', "15,4", 'null' => 0, 'default' => '0.0000'),
        'compare_price' => array('decimal', "15,4", 'null' => 0, 'default' => '0.0000'),
        'currency' => array('char', 3),
        'min_price' => array('decimal', "15,4", 'null' => 0, 'default' => '0.0000'),
        'max_price' => array('decimal', "15,4", 'null' => 0, 'default' => '0.0000'),
        'tax_id' => array('int', 11),
        'count' => array('int', 11),
        'cross_selling' => array('tinyint', 1),
        'upselling' => array('tinyint', 1),
        'rating_count' => array('int', 11, 'null' => 0, 'default' => '0'),
        'total_sales' => array('decimal', "15,4", 'null' => 0, 'default' => '0.0000'),
        'category_id' => array('int', 11),
        'badge' => array('varchar', 255),
        'sku_type' => array('tinyint', 1, 'null' => 0, 'default' => '0'),
        'base_price_selectable' => array('decimal', "15,4", 'null' => 0, 'default' => '0.0000'),
        ':keys' => array(
            'PRIMARY' => 'id',
            'url' => 'url',
            'total_sales' => 'total_sales',
        ),
    ),
    'shop_product_features' => array(
        'id' => array('int', 11, 'null' => 0, 'autoincrement' => 1),
        'product_id' => array('int', 11, 'null' => 0),
        'sku_id' => array('int', 11),
        'feature_id' => array('int', 11, 'null' => 0),
        'feature_value_id' => array('int', 11, 'null' => 0),
        ':keys' => array(
            'PRIMARY' => 'id',
            'feature' => array('product_id', 'sku_id', 'feature_id', 'feature_value_id', 'unique' => 1),
        ),
    ),
    'shop_product_features_selectable' => array(
        'product_id' => array('int', 11, 'null' => 0),
        'feature_id' => array('int', 11, 'null' => 0),
        'value_id' => array('int', 11, 'null' => 0),
        ':keys' => array(
            'PRIMARY' => array('product_id', 'feature_id', 'value_id'),
        ),
    ),
    'shop_product_images' => array(
        'id' => array('int', 11, 'null' => 0, 'autoincrement' => 1),
        'product_id' => array('int', 11, 'null' => 0),
        'upload_datetime' => array('datetime', 'null' => 0),
        'edit_datetime' => array('datetime'),
        'description' => array('varchar', 255),
        'sort' => array('int', 11, 'null' => 0, 'default' => '0'),
        'width' => array('int', 5, 'null' => 0, 'default' => '0'),
        'height' => array('int', 5, 'null' => 0, 'default' => '0'),
        'size' => array('int', 11),
        'ext' => array('varchar', 10),
        'badge_type' => array('int', 4),
        'badge_code' => array('text'),
        ':keys' => array(
            'PRIMARY' => 'id',
            'product_id' => 'product_id',
        ),
    ),
    'shop_product_pages' => array(
        'id' => array('int', 11, 'null' => 0, 'autoincrement' => 1),
        'product_id' => array('int', 11),
        'name' => array('varchar', 255, 'null' => 0),
        'title' => array('varchar', 255, 'null' => 0, 'default' => ''),
        'url' => array('varchar', 255),
        'content' => array('text', 'null' => 0),
        'create_datetime' => array('datetime', 'null' => 0),
        'update_datetime' => array('datetime', 'null' => 0),
        'create_contact_id' => array('int', 11, 'null' => 0),
        'sort' => array('int', 11, 'null' => 0, 'default' => '0'),
        'status' => array('tinyint', 1, 'null' => 0, 'default' => '0'),
        'keywords' => array('text'),
        'description' => array('text'),
        ':keys' => array(
            'PRIMARY' => 'id',
            'product_id' => array('product_id', 'url'),
        ),
    ),
    'shop_product_related' => array(
        'product_id' => array('int', 11, 'null' => 0),
        'type' => array('enum', "'cross_selling','upselling'", 'null' => 0),
        'related_product_id' => array('int', 11, 'null' => 0),
        ':keys' => array(
            'PRIMARY' => array('product_id', 'type', 'related_product_id'),
        ),
    ),
    'shop_product_reviews' => array(
        'id' => array('int', 11, 'null' => 0, 'autoincrement' => 1),
        'left_key' => array('int', 11),
        'right_key' => array('int', 11),
        'depth' => array('int', 11, 'null' => 0, 'default' => '0'),
        'parent_id' => array('int', 11, 'null' => 0, 'default' => '0'),
        'product_id' => array('int', 11, 'null' => 0),
        'review_id' => array('int', 11, 'null' => 0, 'default' => '0'),
        'datetime' => array('datetime', 'null' => 0),
        'status' => array('enum', "'approved','deleted'", 'null' => 0, 'default' => 'approved'),
        'title' => array('varchar', 64),
        'text' => array('text'),
        'rate' => array('decimal', "3,2"),
        'contact_id' => array('int', 11, 'unsigned' => 1, 'null' => 0, 'default' => '0'),
        'name' => array('varchar', 50),
        'email' => array('varchar', 50),
        'site' => array('varchar', 100),
        'auth_provider' => array('varchar', 100),
        'ip' => array('int', 11),
        ':keys' => array(
            'PRIMARY' => 'id',
            'contact_id' => 'contact_id',
            'status' => 'status',
            'parent_id' => 'parent_id',
            'product_id' => array('product_id', 'review_id'),
        ),
    ),
    'shop_product_services' => array(
        'id' => array('int', 11, 'null' => 0, 'autoincrement' => 1),
        'product_id' => array('int', 11, 'null' => 0),
        'sku_id' => array('int', 11),
        'service_id' => array('int', 11, 'null' => 0),
        'service_variant_id' => array('int', 11, 'null' => 0),
        'price' => array('decimal', "15,4"),
        'primary_price' => array('decimal', "15,4"),
        'status' => array('tinyint', 1, 'null' => 0, 'default' => '1'),
        ':keys' => array(
            'PRIMARY' => 'id',
            'product_id' => array('product_id', 'sku_id', 'service_id', 'service_variant_id', 'unique' => 1),
            'service_id' => array('service_id', 'service_variant_id'),
        ),
    ),
    'shop_product_skus' => array(
        'id' => array('int', 11, 'null' => 0, 'autoincrement' => 1),
        'product_id' => array('int', 11, 'null' => 0),
        'sku' => array('varchar', 255, 'null' => 0),
        'sort' => array('int', 11, 'null' => 0),
        'name' => array('varchar', 255, 'null' => 0),
        'image_id' => array('int', 11),
        'price' => array('decimal', "15,4", 'null' => 0, 'default' => '0.0000'),
        'primary_price' => array('decimal', "15,4", 'null' => 0, 'default' => '0.0000'),
        'purchase_price' => array('decimal', "15,4", 'null' => 0, 'default' => '0.0000'),
        'compare_price' => array('decimal', "15,4", 'null' => 0, 'default' => '0.0000'),
        'count' => array('int', 11),
        'available' => array('int', 11, 'null' => 0, 'default' => '1'),
        'dimension_id' => array('int', 11),
        'file_name' => array('varchar', 255, 'null' => 0, 'default' => ''),
        'file_size' => array('int', 11, 'null' => 0, 'default' => '0'),
        'file_description' => array('text'),
        'virtual' => array('tinyint', 1, 'null' => 0, 'default' => '0'),
        ':keys' => array(
            'PRIMARY' => 'id',
            'product_id' => 'product_id',
        ),
    ),
    'shop_product_stocks' => array(
        'sku_id' => array('int', 11, 'null' => 0),
        'stock_id' => array('int', 11, 'null' => 0, 'default' => '0'),
        'product_id' => array('int', 11, 'null' => 0),
        'count' => array('int', 11, 'null' => 0),
        ':keys' => array(
            'PRIMARY' => array('sku_id', 'stock_id'),
            'product_id' => array('product_id', 'sku_id'),
        ),
    ),
    'shop_product_tags' => array(
        'product_id' => array('int', 11, 'null' => 0),
        'tag_id' => array('int', 11, 'null' => 0),
        ':keys' => array(
            'PRIMARY' => array('product_id', 'tag_id'),
        ),
    ),
    'shop_search_index' => array(
        'word_id' => array('int', 11, 'null' => 0),
        'product_id' => array('int', 11, 'null' => 0),
        'weight' => array('int', 11, 'null' => 0),
        ':keys' => array(
            'PRIMARY' => array('product_id', 'word_id'),
            'word' => array('word_id', 'product_id', 'weight'),
        ),
    ),
    'shop_search_word' => array(
        'id' => array('int', 11, 'null' => 0, 'autoincrement' => 1),
        'name' => array('varchar', 255, 'null' => 0),
        ':keys' => array(
            'PRIMARY' => 'id',
            'name' => array('name', 'unique' => 1),
        ),
    ),
    'shop_service' => array(
        'id' => array('int', 11, 'null' => 0, 'autoincrement' => 1),
        'name' => array('varchar', 255),
        'description' => array('text'),
        'price' => array('decimal', "15,4", 'null' => 0, 'default' => '0.0000'),
        'currency' => array('char', 3),
        'variant_id' => array('int', 11, 'null' => 0),
        'tax_id' => array('int', 11, 'default' => '0'),
        ':keys' => array(
            'PRIMARY' => 'id',
        ),
    ),
    'shop_service_variants' => array(
        'id' => array('int', 11, 'null' => 0, 'autoincrement' => 1),
        'service_id' => array('int', 11, 'null' => 0),
        'name' => array('varchar', 255),
        'price' => array('decimal', "15,4", 'null' => 0, 'default' => '0.0000'),
        'primary_price' => array('decimal', "15,4", 'null' => 0, 'default' => '0.0000'),
        ':keys' => array(
            'PRIMARY' => 'id',
            'service_id' => 'service_id',
        ),
    ),
    'shop_set' => array(
        'id' => array('varchar', 64, 'null' => 0),
        'name' => array('varchar', 255),
        'rule' => array('varchar', 32),
        'type' => array('int', 1, 'default' => '0'),
        'count' => array('int', 11, 'null' => 0, 'default' => '0'),
        'sort' => array('int', 11, 'null' => 0, 'default' => '0'),
        'create_datetime' => array('datetime', 'null' => 0),
        'edit_datetime' => array('datetime'),
        ':keys' => array(
            'PRIMARY' => 'id',
        ),
    ),
    'shop_set_products' => array(
        'set_id' => array('varchar', 64, 'null' => 0),
        'product_id' => array('int', 11, 'null' => 0),
        'sort' => array('int', 11, 'null' => 0, 'default' => '0'),
        ':keys' => array(
            'PRIMARY' => array('set_id', 'product_id'),
        ),
    ),
    'shop_stock' => array(
        'id' => array('int', 11, 'null' => 0, 'autoincrement' => 1),
        'low_count' => array('int', 11, 'null' => 0, 'default' => '0'),
        'critical_count' => array('int', 11, 'null' => 0, 'default' => '0'),
        'sort' => array('int', 11, 'null' => 0, 'default' => '0'),
        'name' => array('varchar', 255),
        ':keys' => array(
            'PRIMARY' => 'id',
        ),
    ),
    'shop_tag' => array(
        'id' => array('int', 11, 'null' => 0, 'autoincrement' => 1),
        'name' => array('varchar', 255, 'null' => 0),
        'count' => array('int', 11, 'null' => 0, 'default' => '0'),
        ':keys' => array(
            'PRIMARY' => 'id',
            'name' => array('name', 'unique' => 1),
        ),
    ),
    'shop_tax' => array(
        'id' => array('int', 11, 'null' => 0, 'autoincrement' => 1),
        'name' => array('varchar', 255, 'null' => 0, 'default' => ''),
        'included' => array('int', 11, 'null' => 0, 'default' => '0'),
        'address_type' => array('varchar', 8, 'null' => 0, 'default' => 'shipping'),
        ':keys' => array(
            'PRIMARY' => 'id',
        ),
    ),
    'shop_tax_regions' => array(
        'tax_id' => array('int', 11, 'null' => 0),
        'country_iso3' => array('varchar', 3, 'null' => 0),
        'region_code' => array('varchar', 8),
        'tax_value' => array('decimal', "7,4", 'null' => 0, 'default' => '0.0000'),
        'tax_name' => array('varchar', 255),
        'params' => array('text'),
        ':keys' => array(
            'tax_country_region' => array('tax_id', 'country_iso3', 'region_code', 'unique' => 1),
        ),
    ),
    'shop_tax_zip_codes' => array(
        'tax_id' => array('int', 11, 'null' => 0),
        'zip_expr' => array('varchar', 16, 'null' => 0),
        'tax_value' => array('decimal', "7,4", 'null' => 0, 'default' => '0.0000'),
        'sort' => array('int', 11, 'null' => 0, 'default' => '0'),
        ':keys' => array(
            'PRIMARY' => array('tax_id', 'zip_expr'),
        ),
    ),
    'shop_type' => array(
        'id' => array('int', 11, 'null' => 0, 'autoincrement' => 1),
        'sort' => array('int', 11, 'null' => 0, 'default' => '0'),
        'name' => array('varchar', 255),
        'icon' => array('varchar', 255),
        'cross_selling' => array('varchar', 64, 'null' => 0, 'default' => 'alsobought'),
        'upselling' => array('tinyint', 1, 'null' => 0, 'default' => '0'),
        'count' => array('int', 11, 'null' => 0, 'default' => '0'),
        ':keys' => array(
            'PRIMARY' => 'id',
        ),
    ),
    'shop_type_features' => array(
        'type_id' => array('int', 11, 'null' => 0),
        'feature_id' => array('int', 11, 'null' => 0),
        'sort' => array('int', 11, 'null' => 0, 'default' => '0'),
        ':keys' => array(
            'PRIMARY' => array('type_id', 'feature_id'),
        ),
    ),
    'shop_type_services' => array(
        'type_id' => array('int', 11, 'null' => 0),
        'service_id' => array('int', 11, 'null' => 0),
        ':keys' => array(
            'PRIMARY' => array('type_id', 'service_id'),
            'service_id' => 'service_id',
        ),
    ),
    'shop_type_upselling' => array(
        'type_id' => array('int', 11, 'null' => 0),
        'feature' => array('varchar', 32, 'null' => 0),
        'feature_id' => array('int', 11),
        'cond' => array('varchar', 16, 'null' => 0),
        'value' => array('varchar', 255),
        ':keys' => array(
            'PRIMARY' => array('type_id', 'feature'),
        ),
    ),
);
