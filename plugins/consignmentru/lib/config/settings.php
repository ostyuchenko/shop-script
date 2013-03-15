<?php
return array(
    'NDS'                      => array(
        'value'        => '0',
        'title'        => 'Ставка НДС (%)',
        'description'  => 'Укажите ставку НДС в процентах. Если Вы работаете по упрощенной системе налогообложения, укажите 0',
        'control_type' => 'text',
        'subject'      => 'printform',
    ),
    'NDS_IS_INCLUDED_IN_PRICE' => array(
        'value'        => false,
        'title'        => 'НДС уже включен в стоимость товаров',
        'description'  => 'Включите эту опцию, если налог уже включен в стоимость товаров в Вашем магазине. Если же НДС не включен в стоимость и должен прибавляться дополнительно, выключите эту опцию',
        'control_type' => 'checkbox',
        'subject'      => 'printform',
    ),
    'COMPANYNAME'              => array(
        'value'        => '',
        'title'        => 'Название компании',
        'description'  => 'Укажите название организации, от имени которой выписывается счет',
        'control_type' => 'text',
        'subject'      => 'printform',
    ),
    'COMPANYADDRESS'           => array(
        'value'        => '',
        'title'        => 'Адрес компании',
        'description'  => 'Укажите адрес организации, от имени которой выписывается счет',
        'control_type' => 'text',
        'subject'      => 'printform',
    ),
    'COMPANYPHONE'             => array(
        'value'        => '',
        'title'        => 'Телефон компании',
        'description'  => 'Укажите телефон организации',
        'control_type' => 'text',
        'subject'      => 'printform',
    ),
    'CEO_NAME'                 => array(
        'value'        => '',
        'title'        => 'Директор компании',
        'description'  => 'Укажите Фамилию И.О.',
        'control_type' => 'text',
        'subject'      => 'printform',
    ),
    'BUH_NAME'                 => array(
        'value'        => '',
        'title'        => 'Бухгалтер компании',
        'description'  => 'Укажите Фамилию И.О.',
        'control_type' => 'text',
        'subject'      => 'printform',
    ),
    'BANK_ACCOUNT_NUMBER'      => array(
        'value'        => '',
        'title'        => 'Расчетный счет',
        'description'  => 'Номер расчетного счета организации',
        'control_type' => 'text',
        'subject'      => 'printform',
    ),
    'INN'                      => array(
        'value'        => '',
        'title'        => 'ИНН',
        'description'  => 'ИНН организации',
        'control_type' => 'text',
        'subject'      => 'printform',
    ),
    'KPP'                      => array(
        'value'        => '',
        'title'        => 'КПП',
        'description'  => '',
        'control_type' => 'text',
        'subject'      => 'printform',
    ),
    'BANKNAME'                 => array(
        'value'        => '',
        'title'        => 'Наименование банка',
        'description'  => '',
        'control_type' => 'text',
        'subject'      => 'printform',
    ),
    'BANK_KOR_NUMBER'          => array(
        'value'        => '',
        'title'        => 'Корреспондентский счет',
        'description'  => '',
        'control_type' => 'text',
        'subject'      => 'printform',
    ),
    'BIK'                      => array(
        'value'        => '',
        'title'        => 'БИК',
        'description'  => '',
        'control_type' => 'text',
        'subject'      => 'printform',
    ),
    'CUSTOMER_COMPANY_FIELD'   => array(
        'value'        => 'company',
        'title'        => 'Компания покупателя',
        'description'  => 'Поле "Компания" в форме регистрации',
        'control_type' => waHtmlControl::CONTACTFIELD,
        'subject'      => 'printform',
    ),
    'CUSTOMER_PHONE_FIELD'     => array(
        'value'        => 'phone',
        'title'        => 'Телефон покупателя',
        'description'  => 'Поле "телефон" в форме регистрации',
        'control_type' => waHtmlControl::CONTACTFIELD,
        'subject'      => 'printform',
    ),
);
