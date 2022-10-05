<?php

class shopProdSetTypeDialogAction extends waViewAction
{
    public function execute()
    {
        $type_model = new shopTypeModel();
        $types = $type_model->getTypes();
        $this->view->assign('types', $types);
    }
}