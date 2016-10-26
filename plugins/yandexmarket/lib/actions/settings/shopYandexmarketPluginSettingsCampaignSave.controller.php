<?php

class shopYandexmarketPluginSettingsCampaignSaveController extends waJsonController
{
    public function execute()
    {
        $campaign_id = waRequest::request('campaign_id', null, waRequest::TYPE_INT);
        $campaign = waRequest::post('campaign');

        $shipping_methods = array();
        foreach (ifset($campaign['shipping_methods'], array()) as $id => $shipping_params) {
            if (!empty($shipping_params['enabled'])) {
                $shipping_methods[$id] = array(
                    'estimate' => $shipping_params['estimate'],
                );

                if ($id == 'dummy') {
                    $shipping_methods[$id]['name'] = $shipping_params['name'];
                } else {
                    if (empty($campaign['local_delivery_only']) && !empty($shipping_params['estimate_ext'])) {
                        $shipping_methods[$id]['estimate_ext'] = $shipping_params['estimate_ext'];
                    }
                }

                if (isset($shipping_params['cost']) && ($shipping_params['cost'] != '')) {
                    $shipping_methods[$id]['cost'] = $shipping_params['cost'];
                }
                if (!empty($shipping_params['type'])) {
                    $shipping_methods[$id]['type'] = $shipping_params['type'];
                }

                if (!empty($campaign['payment']['CASH_ON_DELIVERY']) && !empty($shipping_params['cash'])) {
                    $shipping_methods[$id]['cash'] = true;
                }

                if (!empty($campaign['payment']['CARD_ON_DELIVERY']) && !empty($shipping_params['card'])) {
                    $shipping_methods[$id]['card'] = true;
                }

                if (!empty($shipping_params['cal'])) {
                    $shipping_methods[$id]['cal'] = true;
                }
            }
        }

        if (ifset($campaign['order_before_mode']) == 'per-day') {
            $working_days = array();
            for ($day = 0; $day < 7; $day++) {
                $day_info = ifset($campaign['order_before_per_day'][$day], array());
                if (!empty($day_info['workday'])) {
                    $working_days[$day] = max(1, min(24, ifset($day_info['before'], 24)));
                }
            }
            $campaign['order_before_per_day'] = $working_days;
        } elseif (isset($campaign['order_before_per_day'])) {
            unset($campaign['order_before_per_day']);
        }

        $campaign['shipping_methods'] = $shipping_methods;
        $campaign += array(
            'over_sell'           => false,
            'pickup'              => false,
            'delivery'            => false,
            'local_delivery_only' => false,
            'deliveryIncluded'    => false,
        );

        //TODO validate campaign settings

        $model = new shopYandexmarketCampaignsModel();
        $model->set($campaign_id, $campaign);
    }
}