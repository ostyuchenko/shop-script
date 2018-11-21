<?php

class shopSiteRoute_deleteAfterHandler extends waEventHandler
{
    /**
     * @param array $params array('domain' => string, 'route' => array)
     * @see waEventHandler::execute()
     * @return void
     */
    public function execute(&$params)
    {
        $our_app = isset($params['route']['app']) && $params['route']['app'] == 'shop';
        if ($our_app && !empty($params['route']['checkout_storefront_id'])) {
            $cfg_path = wa()->getConfig()->getConfigPath('checkout2.php', true, 'shop');
            if (file_exists($cfg_path)) {
                $cfg = include($cfg_path);
                if (!empty($cfg[$params['route']['checkout_storefront_id']])) {
                    unset($cfg[$params['route']['checkout_storefront_id']]);
                    waUtils::varExportToFile($cfg, $cfg_path);
                }
            }
        }
    }
}