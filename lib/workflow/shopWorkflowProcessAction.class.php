<?php

class shopWorkflowProcessAction extends shopWorkflowAction
{
    public function postExecute($order_id = null, $result = null)
    {
        $data = parent::postExecute($order_id, $result);
        if ($order_id != null) {
            $app_settings_model = new waAppSettingsModel();
            if (!$app_settings_model->get('shop', 'update_stock_count_on_create_order')) {
                
                // for logging changes in stocks
                shopProductStocksLogModel::setContext(
                        shopProductStocksLogModel::TYPE_ORDER,
                        'Order %s was processed',
                        array(
                            'order_id' => $order_id
                        )
                );
                
                $order_model = new shopOrderModel();
                $order_model->reduceProductsFromStocks($order_id);
                
                shopProductStocksLogModel::clearContext();
                
            }
        }
        return $data;
    }
}
