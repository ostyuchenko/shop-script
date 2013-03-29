<?php

class shopOrderSaveController extends waJsonController
{
    private $models = array();

    public function execute()
    {
        $id = waRequest::get('id', null, waRequest::TYPE_INT);

        // null             - don't add/edit contact info
        // not zero numeric - edit existing contact
        // zero numeric     - add contact
        $customer_id = waRequest::post('customer_id', null, waRequest::TYPE_INT);

        if ($customer_id !== null) {
            $contact = new waContact($customer_id);
            $form = shopHelper::getCustomerForm($customer_id);
            if (!$form->isValid($contact)) {
                $this->errors['customer']['html'] = $form->html();
            }
        }

        if ($data = $this->getData($id)) {
            $this->validate($data);
        }

        if ($this->errors) {
            return;
        }

        if ($customer_id !== null) {
            foreach((array)$form->post() as $fld_id => $fld_data) {
                if (!$fld_data) {
                    continue;
                }
                if (is_array($fld_data) && !empty($fld_data[0])) {
                    $contact[$fld_id] = array();
                    foreach($fld_data as $v) {
                        $contact->set($fld_id, $v, true);
                    }
                } else {
                    $contact[$fld_id] = $fld_data;
                }
            }

            if ($customer_id && shopHelper::getContactRights(wa()->getUser()->getId())) {
                if ($errors = $contact->save(array(), true)) { // !!! FIXME: when this returns an error, JS fails to show it
                    $this->errors['customer'] = $errors;
                    return;
                }
            }
            $data['contact'] = $contact;
        }

        $workflow = new shopWorkflow();
        $this->getParams($data, $id);

        if (!$id) {
            $id = $workflow->getActionById('create')->run($data);
        } else {
            $data['id'] = $id;
            $workflow->getActionById('edit')->run($data);
        }

        $this->response['order'] = $this->workupOrder($this->getModel()->getOrder($id));
    }

    private function getParams(&$data, $id)
    {
        $model = new shopPluginModel();

        $shipping_address = array();
        if (!empty($data['contact'])) {
            $address = $data['contact']->getFirst('address.shipping');
            if (!$address) {
                $address = $data['contact']->getFirst('address');
            }
            if (!empty($address['data'])) {
                $shipping_address = $address['data'];
            }
        }
        // shipping
        if ($shipping_id = waRequest::post('shipping_id')) {
            $shipping_parts = explode('.', $shipping_id);
            $shipping_id = $shipping_parts[0];
            $rate_id = isset($shipping_parts[1]) ? $shipping_parts[1] : '';
            $data['params']['shipping_id'] = $shipping_id;
            $data['params']['shipping_rate_id'] = $rate_id;
            $plugin_info = $model->getById($shipping_id);
            $plugin = shopShipping::getPlugin($plugin_info['plugin'], $shipping_id);
            $rates = $plugin->getRates(array(), $shipping_address);
            if (!$rate_id) {
                $rate = reset($rates);
                $data['params']['shipping_rate_id'] = key($rates);
            } else {
                $rate = $rates[$rate_id];
            }
            $data['params']['shipping_plugin'] = $plugin->getId();
            $data['params']['shipping_name'] = $plugin_info['name'].(!empty($rate['name']) ? ' ('.$rate['name'].')' : '');
            $data['params']['shipping_est_delivery'] = $rate['est_delivery'];
        } else {
            foreach (array('id', 'rate_id', 'plugin', 'name', 'est_delivery') as $k) {
                $data['params']['shipping_'.$k] = null;
            }
        }
        // payment
        if ($payment_id = waRequest::post('payment_id')) {
            $data['params']['payment_id'] = $payment_id;
            $plugin_info = $model->getById($payment_id);
            $data['params']['payment_plugin'] = $plugin_info['plugin'];
            $data['params']['payment_name'] = $plugin_info['name'];
        }

        // shipping and billing addreses
        if (!empty($data['contact'])) {
            // Make sure all old address data is removed
            if ($id) {
                $opm = new shopOrderParamsModel();
                foreach($opm->get($id) as $k => $v) {
                    if (preg_match('~^(billing|shipping)_address\.~', $k)) {
                        $data['params'][$k] = '';
                    }
                }
            }
            // Save addresses from contact into params
            foreach (array('shipping', 'billing') as $ext) {
                $address = $data['contact']->getFirst('address.'.$ext);
                if (!$address) {
                    $address = $data['contact']->getFirst('address');
                }
                if (!empty($address['data'])) {
                    foreach ($address['data'] as $k => $v) {
                        $data['params'][$ext.'_address.'.$k] = $v;
                    }
                }
            }
        }
    }

    private function getData($id)
    {
        $data = $id ? $this->getEditData() : $this->getAddData();
        if (!$data) {
            return array();
        }
        $data['shipping'] = waRequest::post('shipping', 0);
        $data['discount'] = waRequest::post('discount', 0);
        $data['tax'] = 0;
        $data['total'] = $this->calcTotal($data);
        return $data;
    }

    private function post($name = null, $default = null, $ns = null, $type = null)
    {
        $data = waRequest::post($name, $default, $type);
        if ($ns === null) {
            return $data;
        }
        if (isset($data[$ns])) {
            return $data[$ns];
        }
        return array();
    }

    private function validate($data)
    {
        if (empty($data['items'])) {
            $this->errors['order']['common'] = _w('There is not one product for order');
        }
        return empty($this->errors);
    }

    private function validateEditDataStocks()
    {
        $skus   = $this->post('sku', array(), 'edit');
        $stocks = $this->post('stock', array(), 'edit');
        $items  = $this->post('item', array(), 'edit');

        $sku_ids = array();
        foreach ($items as $index => $item_id) {
            $sku_ids[] = $skus[$item_id];
        }

        $sku_stocks = $this->getSkuStocks($sku_ids);
        foreach ($items as $index => $item_id) {
            $sku_id   = $skus[$item_id];
            $stock_id = $stocks[$item_id];
            if (empty($stock_id) && !empty($sku_stocks[$sku_id])) {
                $this->errors['order']['items'][$index]['stock_id'] = _w('Select stock');
            }
        }
        return empty($this->errors);
    }

    private function getEditData()
    {
        if (!$this->validateEditDataStocks()) {
            return array();
        }

        $items      = $this->post('item', array(), 'edit');
        $products   = $this->post('product', array(), 'edit');
        $skus       = $this->post('sku', array(), 'edit');
        $services   = $this->post('service', array(), 'edit');
        $variants   = $this->post('variant', array(), 'edit');
        $names      = $this->post('name', array(), 'edit');
        $prices     = $this->post('price', array(), 'edit');
        $quantities = $this->post('quantity', array(), 'edit');
        $stocks     = $this->post('stock', array(), 'edit');


        $product_ids = array();
        $sku_ids     = array();
        $service_ids = array();
        $variant_ids = array();
        $quantity = 0;

        $data = array(
            'items' => array()
        );

        foreach ($items as $index => $item_id) {
            $product_ids[] = $products[$item_id];
            $sku_ids[] = $skus[$item_id];

            $quantity = $quantities[$item_id];
            $data['items'][] = array(
                'id' => $item_id,
                'product_id' => $products[$item_id],
                'sku_id' => $skus[$item_id],
                'type' => 'product',
                'service_id' => null,
                'service_variant_id' => null,
                'price' => $prices[$item_id],
                'quantity' => $quantities[$item_id],
                'stock_id' => !empty($stocks[$item_id]) ? $stocks[$item_id] : null,
            );

            if (!empty($services[$index])) {
                foreach ($services[$index] as $group => $services_grouped) {
                    foreach ($services_grouped as $k => $service_id) {
                        $service_ids[] = $service_id;
                        $pitem = &$data['items'][];
                        $pitem = array(
                            'product_id' => $products[$item_id],
                            'sku_id' => $skus[$item_id],
                            'type' => 'service',
                            'service_id' => $service_id,
                            'price' => $prices[$group][$k],
                            'quantity' => $quantity,
                            'service_variant_id' => null
                        );
                        if ($group == 'item') {        // it's item for update: $k is ID of item
                            $pitem['id'] = $k;
                        } else {
                            $pitem['parent_id'] = $item_id;
                            $pitem['type'] = 'service';
                        }

                        if (!empty($variants[$index][$service_id])) {
                            $variant_ids[] = $variants[$index][$service_id];
                            $pitem['service_variant_id'] = $variants[$index][$service_id];
                        }
                        unset($pitems);
                    }
                }
            }
        }

        if ($product_ids) {
            $products = $this->getFields($product_ids, 'product', 'name,tax_id');
            $skus     = $this->getFields($sku_ids, 'product_skus', 'name, sku, purchase_price');
            $services = $this->getFields($service_ids, 'service', 'name,tax_id');
            $variants = $this->getFields($variant_ids, 'service_variants');


            foreach ($data['items'] as &$item) {
                // items with id mean for updating (old items)
                if (isset($item['id'])) {
                    if ($item['service_id']) {
                        if (isset($names[$item['id']])) {
                            $item['name'] = $names[$item['id']];
                        } else {
                            if ($variants[$item['service_variant_id']]['name']) {
                                $item['name'] = "{$services[$item['service_id']]['name']} ({$variants[$item['service_variant_id']]['name']})";
                            } else {
                                $item['name'] = "{$services[$item['service_id']]['name']}";
                            }
                        }
                        if (isset($services[$item['service_id']])) {
                            $item['tax_id'] = $services[$item['service_id']]['tax_id'];
                        } else {
                            $item['tax_id'] = null;
                        }
                        continue;
                    }

                    if (isset($names[$item['id']])) {
                        $item['name'] = $names[$item['id']];
                    } else {
                        $item['name'] = $products[$item['product_id']]['name'];
                        if ($skus[$item['sku_id']]['name']) {
                            $item['name'] .= ' ('.$skus[$item['sku_id']]['name'].')';
                        }
                    }
                    if (isset($products[$item['product_id']])) {
                        $item['tax_id'] = $products[$item['product_id']]['tax_id'];
                    } else {
                        $item['tax_id'] = null;
                    }
                } else {
                    if ($item['service_id']) {
                        if ($variants[$item['service_variant_id']]['name']) {
                            $item['name'] = "{$services[$item['service_id']]['name']} ({$variants[$item['service_variant_id']]['name']})";
                        } else {
                            $item['name'] = "{$services[$item['service_id']]['name']}";
                        }
                        if (isset($services[$item['service_id']])) {
                            $item['tax_id'] = $services[$item['service_id']]['tax_id'];
                        } else {
                            $item['tax_id'] = null;
                        }
                    } else {
                        $item['sku_code'] = $skus[$item['sku_id']]['sku'];
                        $item['purchase_price'] = $skus[$item['sku_id']]['purchase_price'];
                    }
                }
            }
            unset($item);
        }
        $data['items'] = array_merge($data['items'], $this->getItems());
        return $data;
    }

    private function getAddData()
    {
        return array(
            'currency' => $this->getConfig()->getCurrency(),
            'rate' => 1,
            'items' => $this->getItems()
        );
    }

    private function getItems()
    {
        $data = array();
        $products   = $this->post('product', array(), 'add');
        if (!$products) {
            return $data;
        }

        $skus       = $this->post('sku', array(), 'add');
        $prices     = $this->post('price', array(), 'add');
        $quantities = $this->post('quantity', array(), 'add');
        $services   = $this->post('service', array(), 'add');
        $variants   = $this->post('variant', array(), 'add');
        $stocks     = $this->post('stock', array(), 'add');

        $product_ids = array();
        $sku_ids     = array();
        $service_ids = array();
        $variant_ids = array();
        $quantity = 0;

        foreach ($products as $index => $product_id) {
            $product_ids[] = (int)$product_id;

            $sku_id = $skus[$index];
            $sku_ids[] = (int)$sku_id;
            $quantity = $quantities[$index]['product'];
            $data[] = array(
                'name' => '',
                'product_id' => $product_id,
                'sku_id' => $sku_id,
                'type' => 'product',
                'service_id' => null,
                'price' => $prices[$index]['product'],
                'currency' => '',
                'quantity' => $quantity,
                'service_variant_id' => null,
                'stock_id' => !empty($stocks[$index]['product']) ? $stocks[$index]['product'] : null
            );
            if (!empty($services[$index])) {
                foreach ($services[$index] as $service_id) {
                    $service_ids[] = (int)$service_id;
                    $item = array(
                        'name' => '',
                        'product_id' => $product_id,
                        'sku_id' => $skus[$index],
                        'type' => 'service',
                        'service_id' => $service_id,
                        'price' => $prices[$index]['service'][$service_id],
                        'currency' => '',
                        'quantity' => $quantity,
                        'service_variant_id' => null,
                        'stock_id' => null
                    );
                    if (!empty($variants[$index][$service_id])) {
                        $variant_ids[] = (int)$variants[$index][$service_id];
                        $item['service_variant_id'] = $variants[$index][$service_id];
                    }
                    $data[] = $item;
                }
            }
        }

        $products = $this->getFields($product_ids, 'product', 'name, tax_id');
        $skus = $this->getFields($sku_ids, 'product_skus', 'name, sku, purchase_price');
        $services = $this->getFields($service_ids, 'service', 'name, tax_id');
        $variants = $this->getFields($variant_ids, 'service_variants');

        foreach ($data as &$item) {
            if ($item['service_id']) {
                //$item['tax_id'] = $services[$item['service_id']]['tax_id'];
                $name = $services[$item['service_id']]['name'];
                if ($item['service_variant_id']) {
                    if ($variants[$item['service_variant_id']]['name']) {
                        $name .= " ({$variants[$item['service_variant_id']]['name']})";
                    }
                }
                $item['tax_id'] = $services[$item['service_id']]['tax_id'];
            } else {
                //$item['tax_id'] = $products[$item['product_id']]['tax_id'];
                $name = $products[$item['product_id']]['name'];
                if ($skus[$item['sku_id']]['name']) {
                    $name .= ' ('.$skus[$item['sku_id']]['name'].')';
                }
                $item['sku_code'] = $skus[$item['sku_id']]['sku'];
                $item['purchase_price'] = $skus[$item['sku_id']]['purchase_price'];
                $item['tax_id'] = $products[$item['product_id']]['tax_id'];
            }
            $item['name'] = $name;
        }
        unset($item);
        return $data;
    }

    public function calcTotal($data)
    {
        $total = 0;
        foreach ($data['items'] as $item) {
            $total += $this->cast($item['price'])*(int)$item['quantity'];
        }
        if ($total == 0) {
            return $total;
        }
        return $total - $this->cast($data['discount']) + $this->cast($data['shipping']);
    }

    private function cast($value)
    {
        if (strpos($value, ',') !== false) {
            $value = str_replace(',', '.', $value);
        }
        return str_replace(',', '.', (double)$value);
    }

    public function getCustomerData()
    {
        return waRequest::post('customer');
    }

    public function getSkuStocks($sku_ids)
    {
        if (!$sku_ids) {
            return array();
        }
        $sku_stocks = $this->getModel('product_stocks')->
            select('*')->
            where("sku_id IN (".implode(',', $sku_ids).")")->
            order('sku_id')->
            fetchAll();

        $data = array();
        foreach ($sku_ids as $sku_id) {
            $data[$sku_id] = array();
        }

        $sku_id = null;
        foreach ($sku_stocks as $item) {
            if ($item['sku_id'] != $sku_id) {
                $sku_id = $item['sku_id'];
            }
            $data[$sku_id][$item['stock_id']] = $item;
        }
        return $data;
    }

    public function getFields(array $ids, $model_name, $fields = 'name')
    {
        if (!$ids) {
            return array();
        }
        return $this->getModel($model_name)->select('id, '.$fields)->where("id IN (".implode(',', $ids).")")->fetchAll('id');
    }

    public function getCurrencies()
    {
        return $this->getModel('currency')->getCurrencies();
    }

    /**
     * @param string $name
     * @return shopOrderModel
     */
    public function getModel($name = 'order')
    {
        if (!isset($this->models[$name])) {
            if ($name == 'product') {
                $this->models[$name] = new shopProductModel();
            } else if ($name == 'product_skus') {
                $this->models[$name] = new shopProductSkusModel();
            } else if ($name == 'product_stocks') {
                $this->models[$name] = new shopProductStocksModel();
            } else if ($name == 'currency') {
                $this->models[$name] = new shopCurrencyModel();
            } else if ($name == 'order_items') {
                $this->models[$name] = new shopOrderItemsModel();
            } else if ($name == 'service') {
                $this->models[$name] = new shopServiceModel();
            } else if ($name == 'service_variants') {
                $this->models[$name] = new shopServiceVariantsModel();
            } else {
                $this->models[$name] = new shopOrderModel();
            }
        }
        return $this->models[$name];
    }

    public function workupOrder($order)
    {
        if (!empty($order['items'])) {
            foreach ($order['items'] as &$item) {
                $item['name'] = htmlspecialchars($item['name']);
                unset($item);
            }
        }
        $order['contact']['name'] = htmlspecialchars($order['contact']['name']);
        $orders = array($order);
        shopHelper::workupOrders($orders);
        return $orders[0];
    }
}
