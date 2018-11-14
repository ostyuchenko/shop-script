<?php
/**
 * Helper object available in templates as $wa->shop->checkout()
 */
class shopCheckoutViewHelper
{
    /**
     * Returns HTML rendering cart block for new one-page checkout
     *
     * @param array
     * @return string
     */
    public function cart($opts=array())
    {
        $template_path = wa()->getAppPath('templates/actions/frontend/FrontendOrderCart.html', 'shop');
        return $this->renderTemplate($template_path, $this->cartVars() + array(
            'shop_checkout_include_path' => wa()->getAppPath('templates/actions/frontend/order/', 'shop'),
            'options' => $opts + [
                'adaptive' => true,
            ],
        ));
    }

    /**
     * Returns variables that $wa->shop->checkout()->cart() assigns to its template.
     * @return array
     */
    public function cartVars($clear_cache=false)
    {
        static $result = null;
        if ($clear_cache || $result === null) {
            $old_is_template = waConfig::get('is_template');
            waConfig::set('is_template', null);
            $data = wa()->getStorage()->get('shop/checkout');
            $order = $this->getFakeOrder(new shopCart());
            $result = array_merge(
                $this->cartBasicVars($order),
                $this->cartCouponVars($data, $order),
                $this->cartAffiliateVars($data, $order)
            );

            /**
             * @event frontend_order_cart_vars
             * Allows to modify template vars of $wa->shop->checkout()->cart() before sending them to template
             */
            wa('shop')->event('frontend_order_cart_vars', $result);

            waConfig::set('is_template', $old_is_template);
        }
        return $result;
    }

    protected function cartBasicVars($order)
    {
        $currency = $order['currency'];
        $currency_info = reset(ref(wa('shop')->getConfig()->getCurrencies($currency)));
        $locale_info = waLocale::getInfo(wa()->getLocale());

        $feature_codes = array_reduce($order['items'], function($carry, $item) {
            return $carry + $item['product']['features'];
        }, []);

        $config = new shopCheckoutConfig(ifset(ref(wa()->getRouting()->getRoute()), 'checkout_storefront_id', []));
        return [
            'cart' => $order,
            'currency_info' => [
                'code' => $currency_info['code'],
                'fraction_divider' => ifset($locale_info, 'decimal_point', '.'),
                'fraction_size' => ifset($currency_info, 'precision', 2),
                'group_divider' => ifset($locale_info, 'thousands_sep', ''),
                'group_size' => 3,

                'pattern_html' => str_replace('0', '%s', waCurrency::format('%{h}', 0, $currency)),
                'pattern_text' => str_replace('0', '%s', waCurrency::format('%{s}', 0, $currency)),

                'is_primary' => $currency_info['is_primary'],
                'rate' => $currency_info['rate'],
                'rounding' => $currency_info['rounding'],
                'round_up_only' => $currency_info['round_up_only'],
            ],
            'features' => (new shopFeatureModel())->getByCode(array_keys($feature_codes)),
            'config' => $config,
        ];
    }

    protected function getTotalWeightHtml($weight_value)
    {
        $f = (new shopFeatureModel())->getByCode('weight');
        if (!$f || $f['type'] != 'dimension.weight') {
            return waLocale::format($weight_value);
        }

        $weight_info = shopDimension::getInstance()->getDimension('weight');
        $feature_values_dimension = new shopFeatureValuesDimensionModel();
        $dimension = new shopDimensionValue([
            'type' => 'weight',
            'feature_id' => $f['id'],
            'value' => $weight_value,
            'value_base_unit' => $weight_value,
            'unit' => $weight_info['base_unit'],
        ] + $feature_values_dimension->getEmptyRow());

        return $dimension->format(false);
    }

    protected function getFakeOrder($cart)
    {
        //
        // Most of the fields (including, most importantly, discount)
        // are calculated using shopOrder
        //
        $cart_items = $cart->items(true);
        $order = (new shopFrontendOrderActions())->makeOrderFromCart($cart_items);

        $result = [
            'cart_code' => $cart->getCode(),
        ];
        foreach(['currency', 'total', 'subtotal', 'discount', 'discount_description'] as $i) {
            $result[$i] = $order[$i];
        }
        $result['params'] = $order['discount_params'];

        // discounts by item
        $items_discount = array_map(function($discount) {
            return array(
                'id' => $discount['cart_item_id'],
                'discount' => $discount['value'],
            );
        }, $order->items_discount);
        $items_discount = waUtils::getFieldValues($items_discount, 'discount', 'id');

        // Cart items are not taken from shopOrder because it knows nothing
        // about cart. Items are taken from cart directly. We just need to convert
        // hierarchical structure into plain one.
        $result['items'] = $this->formatCartItems($cart_items, $items_discount);
        $result['count'] = array_sum(waUtils::getFieldValues($result['items'], 'quantity', true));
        $result['count_html'] = _w('%d product', '%d products', $result['count']);

        // validate item counts against storefront stock
        $result['items'] = $this->validateStock($result['items']);

        // Total weight
        $result['total_weight'] = array_reduce($result['items'], function($carry, $item) {
            return $carry + ifempty($item, 'product', 'weight', 0)*$item['quantity'];
        }, 0);
        $result['total_weight_html'] = $this->getTotalWeightHtml($result['total_weight']);

        return $result;
    }

    protected function cartCouponVars($data, $order)
    {
        if (!shopDiscounts::isEnabled('coupons')) {
            return array();
        }

        if (empty($data['coupon_code'])) {
            return array(
                'coupon_code' => '',
            );
        }

        return array(
            'coupon_code' => $data['coupon_code'],
            'coupon_discount' => ifset($order, 'params', 'coupon_discount', 0),
            'coupon_free_shipping' => ifset($order, 'params', 'coupon_free_shipping', 0),
        );
    }

    protected function cartAffiliateVars($data, $order)
    {
        if (!shopAffiliate::isEnabled()) {
            return array();
        }

        $affiliate_bonus = $affiliate_discount = 0;
        if (wa()->getUser()->isAuth()) {
            $customer_model = new shopCustomerModel();
            $customer = $customer_model->getById(wa()->getUser()->getId());
            $affiliate_bonus = $customer ? round($customer['affiliate_bonus'], 2) : 0;
        }

        $usage_percent = (float)wa()->getSetting('affiliate_usage_percent', 0, 'shop');
        $add_affiliate_bonus = shopAffiliate::calculateBonus($order);

        $affiliate_discount = 0;
        if (!empty($data['use_affiliate'])) {
            $affiliate_discount = shop_currency(shopAffiliate::convertBonus(ifset($order, 'params', 'affiliate_bonus', 0)), wa('shop')->getConfig()->getCurrency(true), null, false);
            if ($usage_percent) {
                $affiliate_discount = min($affiliate_discount, ($order['total'] + $affiliate_discount) * $usage_percent / 100.0);
            }
        } elseif ($affiliate_bonus) {
            $affiliate_discount = shop_currency(shopAffiliate::convertBonus($affiliate_bonus), wa('shop')->getConfig()->getCurrency(true), null, false);
            if ($usage_percent) {
                $affiliate_discount = min($affiliate_discount, $order['total'] * $usage_percent / 100.0);
            }
        }

        $template_vars = array(
            'affiliate' => array(
                'affiliate_bonus' => $affiliate_bonus,
                'affiliate_discount' => $affiliate_discount,
                'add_affiliate_bonus' => round($add_affiliate_bonus, 2),
                'use_affiliate' => !empty($data['use_affiliate']),
                'affiliate_percent' => $usage_percent,
                'used_affiliate_bonus' => 0,
            ),
        );

        if (!empty($data['use_affiliate'])) {
            $template_vars['affiliate']['used_affiliate_bonus'] = ifset($order, 'params', 'affiliate_bonus', 0);
        }

        return $template_vars;
    }

    protected function validateStock($cart_items)
    {
        $code = ifset(ref(reset($cart_items)), 'code', null);
        if (!$code) {
            return $cart_items;
        }

        if (wa()->getSetting('ignore_stock_count')) {
            $check_count = false;
        } else {
            $check_count = true;
            if (wa()->getSetting('limit_main_stock') && waRequest::param('stock_id')) {
                $stock_id = waRequest::param('stock_id', null, 'string');
                $check_count = $stock_id;
            }
        }

        $cart_model = new shopCartItemsModel();
        $item_counts = $cart_model->checkAvailability($code, $check_count);

        return array_map(function($item) use ($item_counts) {
            $item_data = ifset($item_counts, $item['id'], [
                'count' => 0,
                'available' => false,
                'can_be_ordered' => false,
            ]);
            $item['stock_count'] = $item_data['count'];
            $item['sku_available'] = (bool) $item_data['available'];
            $item['can_be_ordered'] = (bool) $item_data['can_be_ordered'];
            if (!$item['can_be_ordered']) {
                $name = $item['name'];
                if ($item['sku_name']) {
                    $name .= ' ('.$item['sku_name'].')';
                }
                $name = htmlspecialchars($name);

                if ($item['sku_available']) {
                    if ($item['stock_count'] > 0) {
                        $item['error'] = sprintf(_w('Only %d pcs of %s are available, and you already have all of them in your shopping cart.'), $item['stock_count'], $name);
                    } else {
                        $item['error'] = sprintf(_w('Oops! %s just went out of stock and is not available for purchase at the moment. We apologize for the inconvenience. Please remove this product from your shopping cart to proceed.'),
                            $name);
                    }
                } else {
                    $item['error'] = sprintf(_w('Oops! %s is not available for purchase at the moment. Please remove this product from your shopping cart to proceed.'),
                        $name);
                }
            }
            return $item;
        }, $cart_items);
    }

    protected function formatCartItems($cart_items, $items_discount)
    {
        // Convert items from hierarchical into flat list
        $items = [];
        foreach ($cart_items as $item_id => $item) {
            if (isset($item['services'])) {
                $i = $item;
                unset($i['services']);
                $items[$item_id] = $i;
                foreach ($item['services'] as $s) {
                    $items[$s['id']] = $s;
                }
            } else {
                $items[$item_id] = $item;
            }
        }

        shopOrderItemsModel::sortItemsByGeneralSettings($items);

        // Insert per-item discounts
        foreach($items as &$item) {
            $item['discount'] = ifset($items_discount, $item['id'], 0);
        }
        unset($item);

        // Gather all the ids
        $product_ids = $sku_ids = $service_ids = $type_ids = array();
        foreach ($items as $item) {
            $product_ids[$item['product_id']] = $item['product_id'];
            $sku_ids[$item['sku_id']] = $item['sku_id'];
            if ($item['type'] == 'product') {
                $type_ids[$item['product']['type_id']] = $item['product']['type_id'];
            }
        }

        $type_ids = array_values($type_ids);
        $product_ids = array_values($product_ids);
        $sku_ids = array_values($sku_ids);

        // get available services for all types of products
        $type_services_model = new shopTypeServicesModel();
        $rows = $type_services_model->getByField('type_id', $type_ids, true);
        $type_services = array();
        foreach ($rows as $row) {
            $service_ids[$row['service_id']] = $row['service_id'];
            $type_services[$row['type_id']][$row['service_id']] = true;
        }

        // get services for products and skus, part 1: gather service ids
        $product_services_model = new shopProductServicesModel();
        $rows = $product_services_model->getByProducts($product_ids);
        foreach ($rows as $i => $row) {
            if ($row['sku_id'] && !in_array($row['sku_id'], $sku_ids)) {
                unset($rows[$i]);
                continue;
            }
            $service_ids[$row['service_id']] = $row['service_id'];
        }

        $service_ids = array_values($service_ids);

        // Get services
        $service_model = new shopServiceModel();
        $services = $service_model->getByField('id', $service_ids, 'id');
        shopRounding::roundServices($services);

        // get services for products and skus, part 2
        $product_services = $sku_services = array();
        shopRounding::roundServiceVariants($rows, $services);
        foreach ($rows as $row) {
            if (!$row['sku_id']) {
                $product_services[$row['product_id']][$row['service_id']]['variants'][$row['service_variant_id']] = $row;
            }
            if ($row['sku_id']) {
                $sku_services[$row['sku_id']][$row['service_id']]['variants'][$row['service_variant_id']] = $row;
            }
        }

        // Get service variants
        $variant_model = new shopServiceVariantsModel();
        $rows = $variant_model->getByField('service_id', $service_ids, true);
        shopRounding::roundServiceVariants($rows, $services);
        foreach ($rows as $row) {
            $services[$row['service_id']]['variants'][$row['id']] = $row;
            unset($services[$row['service_id']]['variants'][$row['id']]['id']);
        }

        // When assigning services into cart items, we don't want service ids there
        foreach ($services as &$s) {
            unset($s['id']);
        }
        unset($s);

        // Assign service and product data into cart items
        foreach ($items as $item_id => $item) {
            if ($item['type'] == 'product') {
                $p = $item['product'];
                $item_services = array();
                // services from type settings
                if (isset($type_services[$p['type_id']])) {
                    foreach ($type_services[$p['type_id']] as $service_id => &$s) {
                        $item_services[$service_id] = $services[$service_id];
                    }
                }
                // services from product settings
                if (isset($product_services[$item['product_id']])) {
                    foreach ($product_services[$item['product_id']] as $service_id => $s) {
                        if (!isset($s['status']) || $s['status']) {
                            if (!isset($item_services[$service_id])) {
                                $item_services[$service_id] = $services[$service_id];
                            }
                            // update variants
                            foreach ($s['variants'] as $variant_id => $v) {
                                if ($v['status']) {
                                    if ($v['price'] !== null) {
                                        $item_services[$service_id]['variants'][$variant_id]['price'] = $v['price'];
                                    }
                                } else {
                                    unset($item_services[$service_id]['variants'][$variant_id]);
                                }
                                // default variant is different for this product
                                if ($v['status'] == shopProductServicesModel::STATUS_DEFAULT) {
                                    $item_services[$service_id]['variant_id'] = $variant_id;
                                }
                            }
                        } elseif (isset($item_services[$service_id])) {
                            // remove disabled service
                            unset($item_services[$service_id]);
                        }
                    }
                }
                // services from sku settings
                if (isset($sku_services[$item['sku_id']])) {
                    foreach ($sku_services[$item['sku_id']] as $service_id => $s) {
                        if (!isset($s['status']) || $s['status']) {
                            // update variants
                            foreach ($s['variants'] as $variant_id => $v) {
                                if ($v['status']) {
                                    if ($v['price'] !== null) {
                                        $item_services[$service_id]['variants'][$variant_id]['price'] = $v['price'];
                                    }
                                } else {
                                    unset($item_services[$service_id]['variants'][$variant_id]);
                                }
                            }
                        } elseif (isset($item_services[$service_id])) {
                            // remove disabled service
                            unset($item_services[$service_id]);
                        }
                    }
                }
                foreach ($item_services as $s_id => &$s) {
                    if (!$s['variants']) {
                        unset($item_services[$s_id]);
                        continue;
                    }

                    if ($s['currency'] == '%') {
                        shopProductServicesModel::workupItemServices($s, $item);
                    }

                    if (count($s['variants']) == 1) {
                        reset($s['variants']);
                        $v_id = key($s['variants']);
                        $v = $s['variants'][$v_id];
                        $s['variant_id'] = $v_id;
                        $s['price'] = $v['price'];
                        unset($s['variants']);
                    }
                }
                unset($s);
                uasort($item_services, array('shopServiceModel', 'sortServices'));

                $items[$item_id]['services'] = $item_services;
            } else {
                $items[$item['parent_id']]['services'][$item['service_id']]['id'] = $item['id'];
                if (isset($item['service_variant_id'])) {
                    $items[$item['parent_id']]['services'][$item['service_id']]['variant_id'] = $item['service_variant_id'];
                }
                unset($items[$item_id]);
            }
        }

        // Full price and compare (strike-out) price for each item, with services
        foreach ($items as $item_id => $item) {
            $services_price = array_sum(array_map(function($s) use ($item) {
                if (empty($s['id'])) {
                    return 0;
                } else if (isset($s['variants'])) {
                    return shop_currency($s['variants'][$s['variant_id']]['price'] * $item['quantity'], $s['currency'], null, false);
                } else {
                    return shop_currency($s['price'] * $item['quantity'], $s['currency'], null, false);
                }
            }, ifset($item, 'services', [])));

            $items[$item_id]['full_price'] = $services_price + shop_currency($item['price'] * $item['quantity'], $item['currency'], null, false);
            $items[$item_id]['full_compare_price'] = $services_price + shop_currency($item['compare_price'] * $item['quantity'], $item['currency'], null, false);
        }

        // Prepare product features, including weight
        $product_features_model = new shopProductFeaturesModel();
        foreach($items as &$item) {
            $item['product']['features'] = $product_features_model->getValues($item['product_id'], $item['sku_id'], $item['product']['type_id'], $item['product']['sku_type'], false);
            $item['product']['weight'] = ifset($item, 'product', 'features', 'weight', null);
            $item['product']['weight_html'] = $item['product']['weight'];
            $item['total_weight'] = null;
            $item['total_weight_html'] = null;
            if ($item['product']['weight'] instanceof shopDimensionValue) {
                $weight = $item['product']['weight'];
                $item['product']['weight_html'] = $weight->format(false);
                $item['product']['weight'] = $weight->value_base_unit;
                $weight['value'] *= $item['quantity'];
                $weight['value_base_unit'] *= $item['quantity'];
                $item['total_weight_html'] = $weight->format(false);
                $item['total_weight'] = $weight->value_base_unit;
            } else if (is_numeric($item['product']['weight'])) {
                $item['total_weight'] = $item['product']['weight'] * $item['quantity'];
                $item['total_weight_html'] = $item['total_weight'];
            }
            $item['weight'] = $item['product']['weight'];
            $item['weight_html'] = $item['product']['weight_html'];
        }
        unset($item);

        return $items;
    }

    //
    // Cart-related methods above this.
    // Form-related methods below this.
    //

    /**
     * Returns HTML rendering form block for new one-page checkout
     *
     * @param array
     * @return string
     */
    public function form($opts=array())
    {
        $template_path = wa()->getAppPath('templates/actions/frontend/FrontendOrderForm.html', 'shop');
        return $this->renderTemplate($template_path, $this->formVars() + array(
            'shop_checkout_include_path' => wa()->getAppPath('templates/actions/frontend/order/', 'shop'),
            'options' => $opts,
        ));
    }

    /**
     * Returns variables that $wa->shop->checkout()->form() assigns to its template.
     * @return array
     */
    public function formVars($clear_cache=false)
    {
        static $result = null;
        if ($clear_cache || $result === null) {
            $old_is_template = waConfig::get('is_template');
            waConfig::set('is_template', null);

            // Get checkout order block data from session
            $session_checkout = wa()->getStorage()->get('shop/checkout');
            $session_input = (!empty($session_checkout['order']) && is_array($session_checkout['order'])) ? $session_checkout['order'] : [];

            $order = (new shopFrontendOrderActions())->makeOrderFromCart();
            $process_data = shopCheckoutStep::processAll('form', $order, $session_input);
            $config = new shopCheckoutConfig(ifset(ref(wa()->getRouting()->getRoute()), 'checkout_storefront_id', null));
            $result = array_merge([
                'config' => $config,
            ], $process_data['result']);
            waConfig::set('is_template', $old_is_template);
        }
        return $result;
    }

    protected function renderTemplate($template_path, $assign = array())
    {
        $view = wa('shop')->getView();
        $old_vars = $view->getVars();

        // Keep vars from parent template
        //$view->clearAllAssign();

        $view->assign($assign);
        $html = $view->fetch($template_path);
        $view->clearAllAssign();
        $view->assign($old_vars);
        return $html;
    }

    /**
     * Returns url to storefront checkout
     * @param bool $absolute
     * @return string
     *
     * NEW CHECKOUT: /shop/order/
     * OLD CHECKOUT: /shop/checkout/
     */
    public function url($absolute = false)
    {
        $route = wa()->getRouting()->getRoute();
        $app = ifset($route, 'app', null);
        if ($app !== 'shop') {
            $route = wa('shop')->getConfig()->getStorefrontRoute();
        }

        $checkout_version = ifset($route, 'checkout_version', 1);

        if ($checkout_version == 2) {
            return wa()->getRouteUrl('shop/frontend/order', [], $absolute);
        }

        return wa()->getRouteUrl('shop/frontend/checkout', [], $absolute);
    }

    /**
     * Returns url to storefront cart
     * @param bool $absolute
     * @return string
     *
     * NEW CHECKOUT: /shop/order/
     * OLD CHECKOUT: /shop/cart/
     */
    public function cartUrl($absolute = false)
    {
        $route = wa()->getRouting()->getRoute();
        $app = ifset($route, 'app', null);
        if ($app !== 'shop') {
            $route = wa('shop')->getConfig()->getStorefrontRoute();
        }

        $checkout_version = ifset($route, 'checkout_version', 1);

        if ($checkout_version == 2) {
            return wa()->getRouteUrl('shop/frontend/order', [], $absolute);
        }

        return wa()->getRouteUrl('shop/frontend/cart', [], $absolute);
    }
}
