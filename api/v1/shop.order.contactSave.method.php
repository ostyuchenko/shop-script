<?php


class shopOrderContactSaveMethod extends shopApiMethod
{
    protected $method = 'POST';

    public function execute()
    {
        $order_id = waRequest::post('order_id', null, waRequest::TYPE_INT);
        $contact_data = waRequest::post('contact', array(), 'array');

        $order_model = new shopOrderModel();
        $order = $order_model->getById($order_id);
        if (!$order) {
            throw new waAPIException('invalid_param', 'Order not found', 404);
        }

        $contact = new waContact($order['contact_id']);

        foreach ($contact_data as $k => $v) {
            $contact->set($k, $v);
        }

        $save = $contact->save(array(), true);

        if ($save) {
            $this->response = array('errors' => $save);
        } else {
            $this->response = array('status' => 'ok');
        }
    }
}