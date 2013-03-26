<?php

class shopLoginAction extends waLoginAction
{

    public function execute()
    {
        $this->setLayout(new shopFrontendLayout());
        $this->setThemeTemplate('login.html');
        try {
            parent::execute();
        } catch (waException $e) {
            if ($e->getCode() == 404) {
                $this->view->assign('error_code', $e->getCode());
                $this->view->assign('error_message', $e->getMessage());
                $this->setThemeTemplate('error.html');
            } else {
                throw $e;
            }
        }
    }

    protected function afterAuth()
    {
        $url = $this->getStorage()->get('auth_referer');
        if (!$url) {
            $url = wa()->getRouteUrl('shop/frontend/my/');
        }
        $this->getStorage()->del('auth_referer');
        $this->getStorage()->del('shop/cart');
        $this->redirect($url);
    }
}