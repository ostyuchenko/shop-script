<?php
return array (
  'name' => 'Честный ЗНАК',
  'img' => 'img/chestnyznak.png',
  'version' => '1.2.0',
  'vendor' => 'webasyst',
  'handlers' => array (
      'order_action_form.editcode' => 'orderActionForm',
      'reset_complete' => 'installSettingsPlugin',
  ),  'backend_menu' => 'installSettingsPlugin'
);
