<?php

class shopProductBadgeSetController extends waJsonController
{
    public function execute()
    {
        $code = waRequest::post('code', null, waRequest::TYPE_STRING_TRIM);
        if (!$code) {
            throw new waException(_w("Empty code"));
        }

        $product_model = new shopProductModel();

        $hash = waRequest::post('hash', '', 'string');
        $all_product_ids = null;

        if (!$hash) {
            $all_product_ids = waRequest::request('product_id', array(), waRequest::TYPE_ARRAY_INT);
            if (!$all_product_ids) {
                $all_product_ids = waRequest::get('id', array(), waRequest::TYPE_ARRAY_INT);
            }

            $product = $product_model->getById($all_product_ids);

            if (!$product) {
                throw new waException(_w("Unknown product"));
            }
            if (!$product_model->checkRights(reset($product))) {
                $this->errors[] = _w('You do not have sufficient access rights to set badges for selected products.');
            }
            $hash = 'id/'.join(',', $all_product_ids);
        }

        if (!$this->errors) {
            /**
             * Attaches stickers to products in bulk and single edits. Get data before changes
             *
             * @param array|string $all_product_ids Products id(s)
             * @param string $code Badge code
             * @param string $hash Collection Hash
             *
             * @event product_badge_set.before
             */
            $params = array(
                'code'        => $code,
                'products_id' => $all_product_ids,
                'hash'        => $hash,
            );
            wa('shop')->event('product_badge_set.before', $params);

            $offset = 0;
            $count = 100;
            $collection = new shopProductsCollection($hash);
            $total_count = $collection->count();
            while ($offset < $total_count) {
                $product_ids = array_keys($collection->getProducts('*', $offset, $count));
                $product_model->updateById($product_ids, array('badge' => $code));
                $offset += count($product_ids);
            }

            /**
             * Attaches stickers to products in bulk and single edits
             *
             * @param array|string $all_product_ids Products id(s)
             * @param string $code Badge code
             * @param string $hash Collection Hash
             *
             * @event product_badge_set.after
             */
            $params = array(
                'code'        => $code,
                'products_id' => $all_product_ids,
                'hash'        => $hash,
            );
            wa('shop')->event('product_badge_set.after', $params);

            $badges = shopProductModel::badges();
            $this->response = isset($badges[$code]) ? $badges[$code]['code'] : $code;
        }
    }
}
