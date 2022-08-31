<?php

class shopProdAddToCategoriesController extends waJsonController
{
    /**
     * @var shopCategoryModel
     */
    private $category_model;

    public function execute()
    {
        $this->category_model = new shopCategoryModel();
        $category_products_model = new shopCategoryProductsModel();

        if (!$this->getUser()->getRights('shop', 'setscategories')) {
            $this->errors[] = [
                'id' => 'not_selected',
                'text' => _w('Товары не выбраны'),
            ];
        }

        $category_ids = waRequest::post('category_id', [], waRequest::TYPE_ARRAY_INT);
        $new_category_id = null;
        $new_category = waRequest::post('new_category', '', waRequest::TYPE_STRING_TRIM);
        if ($new_category) {
            $new_category_id = $this->createCategory($new_category);
            $category_ids[] = $new_category_id;
        }
        if (!$category_ids) {
            return;
        }

        $presentation_id = waRequest::post('presentation_id', null, waRequest::TYPE_INT);
        if (!$presentation_id) {
            $all_product_ids = waRequest::post('product_id', [], waRequest::TYPE_ARRAY_INT);
        } else {
            $presentation = new shopPresentation($presentation_id, true);
            $options = [];
            if ($presentation->getFilterId() > 0) {
                $options['prepare_filter_id'] = $presentation->getFilterId();
            }
            $collection = new shopProductsCollection('', $options);
            $all_product_ids = $presentation->getProducts($collection, [
                'fields' => ['id'],
                'offset' => max(0, waRequest::post('offset', 0, waRequest::TYPE_INT)),
            ]);
            $all_product_ids = array_values($all_product_ids);
        }
        $hash = 'id/'.join(',', $all_product_ids);

        /**
         * Adds a product to the category. Get data before changes
         *
         * @param int $new_category_id
         * @param array $category_ids with $new_category_id
         * @param string $hash
         * @param array products_id
         *
         * @event products_set_categories.before
         */
        $params = array(
            'new_category_id' => $new_category_id,
            'category_ids'    => $category_ids,
            'hash'            => $hash,
            'products_id'     => $all_product_ids,
        );
        wa('shop')->event('products_set_categories.before', $params);

        $ids_left = $all_product_ids;
        while ($ids_left) {
            $product_ids = array_splice($ids_left, 0, min(100, count($ids_left)));
            $category_products_model->add($product_ids, $category_ids);
        }

        if (count($all_product_ids) > 1) {
            $this->logAction('products_edit', count($all_product_ids) . '$' . implode(',', $all_product_ids));
        } elseif (isset($all_product_ids[0]) && is_numeric($all_product_ids[0])) {
            $this->logAction('product_edit', $all_product_ids[0]);
        }

        /**
         * Adds a product to the category
         *
         * @param int $new_category_id
         * @param array $category_ids with $new_category_id
         * @param string $hash
         * @param array|string products_id
         *
         * @event products_set_categories.after
         */
        $params = array(
            'new_category_id' => $new_category_id,
            'category_ids'    => $category_ids,
            'hash'            => $hash,
            'products_id'     => $all_product_ids,
        );
        wa('shop')->event('products_set_categories.after', $params);

        $categories = $this->category_model->getByField('id', $category_ids, 'id');
        if (isset($categories[$new_category_id])) {
            $this->response['new_category'] = $categories[$new_category_id];
            unset($categories[$new_category_id]);
        }
        $this->response['categories'] = $categories;
    }

    protected function createCategory($name)
    {
        $url = shopHelper::transliterate($name, false);
        $url = $this->category_model->suggestUniqueUrl($url);
        if (empty($name)) {
            $name = _w('(no-name)');
        }
        return $this->category_model->add(array(
            'name' => $name,
            'url'  => $url
        ));
    }
}
