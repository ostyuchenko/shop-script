<?php

class shopShippingCli extends waCliController
{
    /** @var shopShipping */
    private $adapter;

    public function preExecute()
    {
        parent::preExecute();
        $this->adapter = shopShipping::getInstance();
    }

    public function execute()
    {
        $plugin_model = new shopPluginModel();
        $options = array(
            'all' => true,
        );
        $methods = $plugin_model->listPlugins(shopPluginModel::TYPE_SHIPPING, $options);

        $adapter = shopShipping::getInstance();
        foreach ($methods as $shipping_id => $method) {
            try {
                $plugin = waShipping::factory($method['plugin'], $shipping_id, $adapter);

                $this->runSync($plugin, $method);

            } catch (waException $ex) {
                $message = $ex->getMessage();
                $data = compact('message', 'shipping_id');
                waLog::log(var_export($data, true), 'shop/shipping.cli.log');
            }
        }

        $app_settings_model = new waAppSettingsModel();
        $app_settings_model->set('shop', 'shipping_plugins_sync', time());
    }

    /**
     * @param waShipping $plugin
     * @param string[]   $method
     */
    protected function runSync($plugin, $method)
    {
        if (!empty($method['status'])) {
            try {
                $plugin->runSync();
            } catch (waException $ex) {
                $message = $ex->getMessage();
                $data = compact('message', 'shipping_id');
                waLog::log(var_export($data, true), 'shop/shipping.cli.log');
            }
        }
    }
}