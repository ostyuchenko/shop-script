<?php

class shopProdAssociatePromoDialogAction extends waViewAction
{
    public function execute()
    {
        $promo_model = new shopPromoModel();
        $active_promos = $promo_model->getList(['status' => shopPromoModel::STATUS_ACTIVE]);
        $planned_promos = $promo_model->getList(['status' => shopPromoModel::STATUS_PLANNED]);

        $this->view->assign([
            'products_hash' => self::getProductsHash(),
            'active_promos' => $active_promos,
            'planned_promos' => $planned_promos,
        ]);
    }

    public static function getProductsHash()
    {
        $product_id = waRequest::post('product_id', [], waRequest::TYPE_ARRAY_INT);
        $presentation_id = waRequest::post('presentation_id', null, waRequest::TYPE_INT);
        if (!$presentation_id) {
            $products_hash = 'id/' . join(',', $product_id);
        } else {
            $presentation = new shopPresentation($presentation_id, true);
            $options = [];
            if ($presentation->getFilterId() > 0) {
                $options['exclude_products'] = $product_id;
                $options['prepare_filter'] = 'filter/' . $presentation->getFilterId();
            }
            $collection = new shopProductsCollection('', $options);
            $products = $presentation->getProducts($collection, [
                'fields' => ['id'],
                'offset' => max(0, waRequest::post('offset', 0, waRequest::TYPE_INT)),
            ]);
            $products_hash = 'id/' . join(',', array_keys($products));
        }

        return $products_hash;
    }
}