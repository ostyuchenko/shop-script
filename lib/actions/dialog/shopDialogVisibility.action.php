<?php

/**
 * Shows a dialog after user clicks 'manage visibility' link
 * in products right sidebar; and processes submit from this dialog.
 */
class shopDialogVisibilityAction extends waViewAction
{
    public function execute()
    {
        if (waRequest::post()) {
            $result = $this->processPost();
        } else {
            $result = array(
                'successfull' => array(),
                'attempted'   => array(),
                'denied'      => array(),
            );
        }

        $this->view->assign(array(
            'status_change' => (int)!!waRequest::post('status'),
            'result'        => $result,
        ));
    }

    protected function processPost()
    {
        $set_status = (int)!!waRequest::post('status');
        $update_sku_availability = !!waRequest::post('update_skus');

        $products_id = array();
        $products_id_denied = array();
        $products_id_attempted = array();
        $products_id_successfull = array();

        // We get either a collection hash or a list of product_ids
        $hash = waRequest::post('hash', '', 'string');
        if (!$hash) {
            $ids = waRequest::post('product_id', array(), waRequest::TYPE_ARRAY_INT);
            if (!$ids) {
                return array(
                    'successfull' => $products_id_successfull,
                    'attempted'   => $products_id_attempted,
                    'denied'      => $products_id_denied,
                );
            } else {
                foreach ($ids as $id) {
                    $products_id_attempted[$id] = $id;
                }
            }
            $hash = 'id/'.join(',', $ids);
        }

        $product_model = new shopProductModel();
        $product_skus_model = new shopProductSkusModel();

        $is_admin = wa()->getUser()->isAdmin('shop'); // separate admin check to allow broken products with no type
        $types_allowed = shopHelper::getWritableTypes();

        $collection = new shopProductsCollection($hash);
        $total_count = $collection->count();
        $count = 100;
        $offset = 0;

        /**
         * Change the visibility of the product. Get data before changes
         *
         * @param bool $set_status
         * @param bool $update_sku_availability
         * @param string $hash
         * @param array $products_id
         * @param array $products_id_denied
         * @param array $products_id_attempted
         * @param array $products_id_successfull
         * @param object $collection
         *
         * @event products_visibility_set.before
         */
        $params = array(
            'status'                  => $set_status,
            'update_sku'              => $update_sku_availability,
            'hash'                    => $hash,
            'products_id'             => $products_id,
            'products_id_denied'      => $products_id_denied,
            'products_id_attempted'   => $products_id_attempted,
            'products_id_successfull' => $products_id_successfull,
            'collection'              => $collection,
        );
        wa('shop')->event('products_visibility_set.before', $params);

        // Read and update products in batches
        while ($offset < $total_count) {

            $products = $collection->getProducts('id,type_id', $offset, $count);

            // Filter products user does not have access to
            $products_id = array();
            foreach ($products as $p) {
                if (empty($types_allowed[$p['type_id']]) && !$is_admin) {
                    $products_id_denied[] = $p['id'];
                } else {
                    $products_id[] = $p['id'];
                    $products_id_successfull[] = $p['id'];
                    $products_id_attempted[$p['id']] = $p['id'];
                }
            }

            // Update products and skus
            $product_model->updateById($products_id, array('status' => $set_status));
            if ($update_sku_availability) {
                $product_skus_model->updateByField('product_id', $products_id, array(
                    'available' => $set_status,
                ));
            }

            $offset += count($products);
            if (!$products) {
                break; // being paranoid
            }
        }

        /**
         * Change the visibility of the product
         *
         * @param bool $set_status
         * @param bool $update_sku_availability
         * @param string $hash
         * @param array $products_id
         * @param array $products_id_denied
         * @param array $products_id_attempted
         * @param array $products_id_successfull
         * @param object $collection
         *
         * @event products_visibility_set.after
         */
        $params = array(
            'status'                  => $set_status,
            'update_sku'              => $update_sku_availability,
            'hash'                    => $hash,
            'products_id'             => $products_id,
            'products_id_denied'      => $products_id_denied,
            'products_id_attempted'   => $products_id_attempted,
            'products_id_successfull' => $products_id_successfull,
            'collection'              => $collection,
        );
        wa('shop')->event('products_visibility_set.after', $params);

        return array(
            'denied'      => $products_id_denied,
            'successfull' => $products_id_successfull,
            'attempted'   => array_values($products_id_attempted),
        );
    }
}

