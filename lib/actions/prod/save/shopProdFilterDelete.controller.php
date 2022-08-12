<?php
/**
 * Delete filter
 */
class shopProdFilterDeleteController extends waJsonController
{
    public function execute()
    {
        $filter_template_id = waRequest::post('filter_id', null, waRequest::TYPE_INT);

        $filter_model = new shopFilterModel();
        $filter_id = $filter_model->select('id')->where('id = ?', (int)$filter_template_id)->fetchAll('id');
        if ($filter_id) {
            $filter_model->deleteById($filter_id);
            $default_filter = $filter_model->getDefaultTemplateByUser(wa()->getUser()->getId());
            $filter_model->updateByField('parent_id', $filter_template_id, $default_filter['id']);

            $filter_model->correctSort();
        }
    }
}
