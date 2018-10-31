<?php

class shopBackendLayout extends waLayout
{
    public function execute()
    {
        // Welcome-screen-ralated stuff: redirect on first login unless skipped
        $app_settings_model = new waAppSettingsModel();
        if (waRequest::get('skipwelcome')) {
            $app_settings_model->del('shop', 'welcome');
            $app_settings_model->del('shop', 'show_tutorial');
            $this->insertOneType();
        } else if ($app_settings_model->get('shop', 'welcome')) {
            $this->redirect(wa()->getConfig()->getBackendUrl(true).'shop/?action=welcome');
        }

        // Tutorial tab status
        $tutorial_progress = 0;
        $tutorial_visible = $app_settings_model->get('shop', 'show_tutorial') || waRequest::request('module') == 'tutorial';
        if ($tutorial_visible) {
            $tutorial_progress = shopTutorialActions::getTutorialProgress();
        }

        $order_model = new shopOrderModel();
        $this->view->assign(array(
            'page'              => $this->getPage(),
            'frontend_url'      => wa()->getRouteUrl('shop/frontend'),
            'backend_menu'      => $this->backendMenuEvent(),
            'new_orders_count'  => $order_model->getStateCounters('new'),
            'tutorial_progress' => $tutorial_progress,
            'tutorial_visible'  => $tutorial_visible,
            'is_web_push_on'    => $this->isWebPushOn()
        ));
    }

    // Layout is slightly different for different modules.
    // $page is passed to template to control that.
    protected function getPage()
    {
        // Default page
        if (wa()->getUser()->getRights('shop', 'orders')) {
            $default_page = 'orders';
        } elseif (wa()->getUser()->getRights('shop', 'type.%')) {
            $default_page = 'products';
        } elseif (wa()->getUser()->getRights('shop', 'design') || wa()->getUser()->getRights('shop', 'pages')) {
            $default_page = 'storefronts';
        } elseif (wa()->getUser()->getRights('shop', 'reports')) {
            $default_page = 'reports';
        } elseif (wa()->getUser()->getRights('shop', 'settings')) {
            $default_page = 'settings';
        } else {
            $default_page = 'products';
        }

        $module = waRequest::get('module', 'backend');
        $page = waRequest::get('action', ($module == 'backend') ? $default_page : 'default');
        if ($module != 'backend') {
            $page = $module.':'.$page;
        }
        $plugin = waRequest::get('plugin');
        if ($plugin) {
            if ($module == 'backend') {
                $page = ':'.$page;
            }
            $page = $plugin.':'.$page;
        }

        return $page;
    }

    protected function backendMenuEvent()
    {
        /**
         * Extend backend main menu
         * Add extra main menu items (tab items, submenu items)
         * @event backend_menu
         * @return array[string]array $return[%plugin_id%]
         * @return array[string][string]string $return[%plugin_id%]['aux_li'] Single menu items
         * @return array[string][string]string $return[%plugin_id%]['core_li'] Single menu items
         */
        return wa()->event('backend_menu');
    }

    protected function insertOneType()
    {
        $stm = new shopTypeModel();
        if (!$stm->countAll()) {
            $data = array(
                'name' => _w('Conventional commodity'),
                'icon' => 'ss pt box',
            );
            $stm->insert($data);
        }
    }


    protected function isWebPushOn()
    {
        $web_push = new shopWebPushNotifications();
        return $web_push->isOn();
    }
}