<?php

class shopFilter
{
    protected $filter;
    protected $template;
    protected $models = [];

    /**
     * $id === null: load user's default filter (create one of not exists)
     *
     * If $transient is false: load filter data, no matter if transient or template.
     *
     * If $transient is true and $id represents a template (i.e. its parent_id is null),
     * then load its corresponding transient filter.
     */
    public function __construct($id = null, $transient = false, $rules = true)
    {
        $template = $template_id = null;
        $filter = null;
        $reset_filter_to_template = true;

        $with_rules = ['rules' => $rules];
        if (!empty($id)) {
            $row = $this->getModel()->getById($id, $with_rules);
            if ($row) {
                if (empty($row['parent_id'])) {
                    $template = $row;
                } else {
                    if ($row['creator_contact_id'] == wa()->getUser()->getId()) {
                        $filter = $row;
                    }
                    $template = $this->getModel()->getById($row['parent_id'], $with_rules);
                }
                $template_id = $template['id'];
            }
        }

        if (empty($template)) {
            $template = $this->getModel()->getDefaultTemplateByUser(wa()->getUser()->getId(), $with_rules);
            if (empty($template)) {
                // This can not happen
                throw new waException('Error initializing default filter');
            }
            $template_id = $template['id'];
            $filter = null;
            $reset_filter_to_template = false;
        }
        if (empty($filter)) {
            if ($transient) {
                $filter = $this->getModel()->getTransientByTemplate($template_id, wa()->getUser()->getId(), [
                    'reset_filter_to_template' => $reset_filter_to_template,
                    'rules' => $rules,
                ]);
            } else {
                $filter = $template;
            }
            if (empty($filter)) {
                // This can not happen
                throw new waException('Error initializing default filter');
            }
        }

        $this->template = $template;
        $this->filter = ifempty($filter, $template);
    }

    public function getId()
    {
        return $this->filter['id'];
    }

    public function getFilter()
    {
        return $this->filter;
    }

    public function getTemplate()
    {
        return $this->template;
    }

    public function isTemplate()
    {
        return empty($this->filter['parent_id']);
    }

    /**
     * @return Array
     * @description Возвращает данные разделов с элементами для фильтрации
     */
    public function getFilterOptions()
    {
        $categories = shopProdCategoriesAction::getCategories();

        $sets = $this->getSetsWithGroups();

        $type_model = new shopTypeModel();
        $types = $type_model->select('`id`, `name`')->fetchAll();

        $storefronts = [];
        $all_storefronts = shopStorefrontList::getAllStorefronts(true);
        foreach ($all_storefronts as $storefront) {
            if (!empty($storefront['route']['checkout_storefront_id'])) {
                $storefront_id = $storefront['route']['checkout_storefront_id'];
            } else {
                $storefront_id = 'sfid'.md5(var_export($storefront['route'], true));
            }
            $storefronts[] = [
                'id' => $storefront_id,
                'name' => $storefront['url_decoded']
            ];
        }

        $tag_model = new shopTagModel();
        $tags = $tag_model->getAll();

        $features_data = self::getAllTypes(true, false, true);
        usort($features_data, function($f1, $f2) {
            return strnatcasecmp(mb_strtolower(trim($f1['name'])), mb_strtolower(trim($f2['name'])));
        });

        $features_data = $this->formatAllTypes($features_data);

        return [
            'categories' => $categories,
            'sets' => $sets,
            'types' => $types,
            'storefronts' => $storefronts,
            'tags' => $tags,
            'features' => $features_data
        ];
    }

    /**
     * @param bool $flat
     * @param bool $all
     * @param bool $format_features
     * @return array
     */
    public static function getAllTypes($flat = false, $all = true, $format_features = false)
    {
        $product_fields = [
            'create_datetime' => [
                'name' => _w('Date added'),
                'type' => 'date',
                'render_type' => 'range',
            ],
            'edit_datetime' => [
                'name' => _w('Дата последнего изменения'),
                'type' => 'date',
                'render_type' => 'range',
            ],
            'status' => [
                'name' => _w('Availability in the storefront'),
                'type' => 'select',
                'render_type' => 'select',
            ],
            'rating' => [
                'name' => _w('Rating'),
                'type' => 'double',
                'render_type' => 'range',
            ],
            'currency' => [
                'name' => _w('Currency'),
                'type' => 'select',
                'render_type' => 'select',
                'options' => [],
                'table' => 'currency',
            ],
            'tax_id' => [
                'name' => _w('Tax'),
                'type' => 'select',
                'render_type' => 'select',
                'options' => [],
                'table' => 'tax',
            ],
            'count' => [
                'name' => _w('In stock'),
                'type' => 'double',
                'render_type' => 'range',
            ],
            'badge' => [
                'name' => _w('Badge'),
                'type' => 'varchar',
                'render_type' => 'select',
            ],
            'sku_count' => [
                'name' => _w('Количество модификаций'),
                'type' => 'double',
                'render_type' => 'range',
            ],
        ];

        if (shopFrac::isEnabled()) {
            $product_fields += [
                'stock_unit_id' => [
                    'name' => _w('Stock quantity unit'),
                    'type' => 'select',
                    'render_type' => 'select',
                    'options' => [],
                    'table' => 'unit',
                ],
                'base_unit_id' => [
                    'name' => _w('Base quantity unit'),
                    'type' => 'select',
                    'render_type' => 'select',
                    'options' => [],
                    'table' => 'unit',
                ],
            ];
        }

        $sku_fields = [
            'price' => [
                'name' => _w('Price'),
                'type' => 'double',
                'render_type' => 'range',
                'display_type' => 'sku',
                'rule_type' => 'price',
                'replaces_previous' => true
            ],
            'compare_price' => [
                'name' => _w('Compare at price'),
                'type' => 'double',
                'render_type' => 'range',
                'display_type' => 'sku',
                'rule_type' => 'compare_price',
                'replaces_previous' => true
            ],
        ];

        $dynamic_fields = [
            'sales_30days' => [
                'name' => _w('Last 30 days sales'),
                'type' => 'double',
                'render_type' => 'range',
                'display_type' => 'dynamic',
                'rule_type' => 'sales_30days',
                'replaces_previous' => true,
            ],
            'stock_worth' => [
                'name' => _w('Stock net worth'),
                'type' => 'double',
                'render_type' => 'range',
                'display_type' => 'dynamic',
                'rule_type' => 'stock_worth',
                'replaces_previous' => true,
            ],
        ];

        foreach ($product_fields as $key => &$field) {
            $field['replaces_previous'] = true;
            $field['display_type'] = 'product';
            $field['rule_type'] = $key;
        }
        unset($field);

        $features = self::getFilterFeatures();
        if ($format_features) {
            $selectable_values = shopPresentation::addSelectableValues($features);
            $features = shopProdSkuAction::formatFeatures($selectable_values, false, false);
        }

        $options = [];
        if ($all) {
            $options += [
                'search' => [
                    'replaces_previous' => true,
                ],
                'categories' => [
                    'replaces_previous' => true,
                ],
                'sets' => [
                    'replaces_previous' => true,
                ],
                'types' => [
                    'replaces_previous' => true,
                ],
                'storefronts' => [
                    'replaces_previous' => false,
                ],
                'tags' => [
                    'replaces_previous' => false,
                ],
            ];
        }

        if ($flat) {
            $options += $product_fields + $sku_fields + $dynamic_fields + $features;
        } else {
            $options += [
                'product_fields' => $product_fields,
                'sku_fields' => $sku_fields,
                'dynamic_fields' => $dynamic_fields,
                'features' => $features,
            ];
        }

        return $options;
    }

    /**
     * @param array $items
     * @return array
     * @throws waException
     */
    public function formatAllTypes($items)
    {
        $currency_model = new shopCurrencyModel();
        $currencies = $currency_model->getCurrencies();
        $currency_id = wa('shop')->getConfig()->getCurrency(true);
        $currency = $currencies[$currency_id];

        $tax_model = new shopTaxModel();
        $taxes = $tax_model->getAll();

        $unit_model = new shopUnitModel();
        $enabled_units = $unit_model->getAllEnabled();
        $units = [];
        foreach ($enabled_units as $unit) {
            $units[] = [
                'name' => $unit['name'],
                'value' => $unit['id'],
            ];
        }

        foreach ($items as &$item) {
            if ($item['display_type'] !== 'feature') {
                if ($item['render_type'] == 'range') {
                    $item['options'] = [
                        ['name' => '', 'value' => ''],
                        ['name' => '', 'value' => '']
                    ];
                }

                /* Преобразуем данные в правильный формат */
                switch ($item['rule_type']) {
                    case 'price':
                    case 'compare_price':
                    case 'sales_30days':
                    case 'stock_worth':
                        $item['currency'] = $currency;
                        break;
                    case 'currency':
                        $item['options'] = [];
                        foreach($currencies as $_currency) {
                            $item['options'][] = [
                                'name' => $_currency['title'],
                                'value' => $_currency['code']
                            ];
                        }
                        break;
                    case 'tax_id':
                        $item['options'] = [];
                        foreach($taxes as $_tax) {
                            $item['options'][] = [
                                'name' => $_tax['name'],
                                'value' => $_tax['id']
                            ];
                        }
                        break;
                    case 'status':
                        $item['options'] = [
                            [
                                'name' => _w('Published'),
                                'value' => '1',
                                'icon' => 'fas fa-check color-green-dark'
                            ],
                            [
                                'name' => _w('Hidden'),
                                'value' => '0',
                                'icon' => 'fas fa-times color-yellow'
                            ],
                            [
                                'name' => _w('Unpublished'),
                                'value' => '-1',
                                'icon' => 'fas fa-times color-red'
                            ]
                        ];
                        break;
                    case 'base_unit_id':
                    case 'stock_unit_id':
                        $item['options'] = $units;
                        break;
                    case 'badge':
                        $item['options'] = [
                            [
                                'name' => _w('New!'),
                                'value' => 'new',
                                'icon' => 'fas fa-bolt'
                            ],
                            [
                                'name' => _w('Low price!'),
                                'value' => 'lowprice',
                                'icon' => 'fas fa-piggy-bank',
                            ],
                            [
                                'name' => _w('Bestseller!'),
                                'value' => 'bestseller',
                                'icon' => 'fas fa-chart-line',
                            ],
                            [
                                'name' => _w('Custom badge'),
                                'value' => 'custom',
                                'icon' => 'fas fa-code',
                            ]
                        ];
                        break;
                }
            }
        }

        return $items;
    }

    /**
     * @param string $rule_type
     * @param array $rule_params
     * @param array $type
     * @param string|null $unit
     * @return array
     * @throws waException
     */
    public static function validateValue($rule_type, $rule_params, $type = [], $unit = null)
    {
        $correct_params = [];
        if (is_array($rule_params)) {
            $count = count($rule_params);
            switch ($rule_type) {
                case 'create_datetime':
                case 'edit_datetime':
                    $correct_params = self::validateDate($count, $rule_params, false);
                    break;
                case 'status':
                    if ($count) {
                        foreach ($rule_params as $param) {
                            if ($param >= -1 && $param <= 1) {
                                $correct_params[] = (int)$param;
                            }
                        }
                    }
                    break;
                case 'rating':
                case 'price':
                case 'compare_price':
                case 'count':
                case 'sku_count':
                case 'sales_30days':
                case 'stock_worth':
                    if ($count == 2) {
                        if (is_numeric($rule_params[0]) && is_numeric($rule_params[1])) {
                            if ((double)$rule_params[0] > (double)$rule_params[1]) {
                                $correct_params = [$rule_params[1], $rule_params[0]];
                            } else {
                                $correct_params = [$rule_params[0], $rule_params[1]];
                            }
                        }
                    } elseif ($count == 1) {
                        if (is_numeric($rule_params[0])) {
                            $correct_params[] = $rule_params[0];
                        }
                    }
                    break;
                case 'currency':
                    if ($count) {
                        foreach ($rule_params as $currency) {
                            if (is_string($currency) && mb_strlen($currency) == 3) {
                                $correct_params[] = mb_strtoupper($currency);
                            }
                        }
                    }
                    break;
                case 'categories':
                case 'types':
                case 'tax_id':
                case 'tags':
                    if ($count) {
                        foreach ($rule_params as $param) {
                            if ($param > 0) {
                                $correct_params[] = (int)$param;
                            }
                        }
                    }
                    break;
                case 'sets':
                case 'storefronts':
                case 'search':
                    if ($count) {
                        foreach ($rule_params as $param) {
                            if (is_string($param) && mb_strlen($param)) {
                                $correct_params[] = $param;
                            }
                        }
                    }
                    break;
                case 'stock_unit_id':
                case 'base_unit_id':
                    if ($count && shopFrac::isEnabled()) {
                        foreach ($rule_params as $unit_id) {
                            if ($unit_id >= 0) {
                                $correct_params[] = (int)$unit_id;
                            }
                        }
                    }
                    break;
                case 'badge':
                    if ($count) {
                        $badges = shopProductModel::badges();
                        foreach ($rule_params as $badge) {
                            if (array_key_exists($badge, $badges)) {
                                $correct_params[] = $badge;
                            }
                        }
                    }
                    break;
            }
            if (isset($type['display_type']) && $type['display_type'] == 'feature') {
                if ($count && ($type['selectable'] || $type['multiple']
                    || in_array($type['type'], [shopFeatureModel::TYPE_VARCHAR, shopFeatureModel::TYPE_BOOLEAN, shopFeatureModel::TYPE_COLOR]))
                ) {
                    foreach ($rule_params as $param) {
                        if ($param >= 0) {
                            $correct_params[] = (int)$param;
                        }
                    }
                } elseif ($type['type'] == shopFeatureModel::TYPE_DATE || $type['type'] == 'range.date') {
                    $correct_params = self::validateDate($count, $rule_params, false);
                } elseif ($count == 2 && ($type['type'] == shopFeatureModel::TYPE_DOUBLE || mb_strpos($type['type'], 'range.') === 0
                    || mb_strpos($type['type'], 'dimension.') === 0)
                ) {
                    if (is_numeric($rule_params[0]) && is_numeric($rule_params[1])) {
                        if ((double)$rule_params[0] > (double)$rule_params[1]) {
                            $correct_params = [$rule_params[1], $rule_params[0]];
                        } else {
                            $correct_params = [$rule_params[0], $rule_params[1]];
                        }
                    }
                } elseif ($count == 1 && is_numeric($rule_params[0])
                    && ($type['type'] == shopFeatureModel::TYPE_DOUBLE || mb_strpos($type['type'], 'range.') === 0
                        || mb_strpos($type['type'], 'dimension.') === 0)
                ) {
                    $correct_params = [$rule_params[0]];
                }
            }
        }

        if ($unit && $correct_params) {
            $correct_params[] = $unit;
        }

        return $correct_params;
    }

    /**
     * @param int $count
     * @param array $rule_params
     * @param $datetime
     * @return array
     * @throws waException
     */
    protected static function validateDate($count, $rule_params, $datetime = true)
    {
        $correct_params = [];
        if (!$datetime) {
            $date_validator = new waDateValidator();
        }
        if ($count == 2) {
            $start_date_correct = $rule_params[0];
            $end_date_correct = $rule_params[1];
            if ($datetime) {
                $start_date_correct = waDateTime::parse('Y-m-d H:i:s', $rule_params[0]);
                $end_date_correct = waDateTime::parse('Y-m-d H:i:s', $rule_params[1]);
            } elseif (!$date_validator->isValid($start_date_correct) || !$date_validator->isValid($end_date_correct)) {
                $start_date_correct = $end_date_correct = false;
            }
            if ($start_date_correct && $end_date_correct) {
                $start_datetime = new DateTime($start_date_correct);
                $end_datetime = new DateTime($end_date_correct);
                if ($start_datetime > $end_datetime) {
                    $correct_params = [$end_date_correct, $start_date_correct];
                } else {
                    $correct_params = [$start_date_correct, $end_date_correct];
                }
            }
        } elseif ($count == 1) {
            $date_correct = $rule_params[0];
            if ($datetime) {
                $date_correct = waDateTime::parse('Y-m-d H:i:s', $rule_params[0]);
            } elseif (!$date_validator->isValid($date_correct)) {
                $date_correct = false;
            }
            if ($date_correct) {
                $correct_params[] = $date_correct;
            }
        }

        return $correct_params;
    }

    protected static function getFilterFeatures()
    {
        $feature_model = new shopFeatureModel();
        $features = $feature_model->select('*, CONCAT("feature_", id) AS `rule_type`')->where('`type` != "text" AND `type` != "divider" AND `type` NOT LIKE "2d.%" AND `type` NOT LIKE "3d.%" AND `parent_id` IS NULL')->fetchAll('rule_type');

        foreach ($features as &$feature) {
            $feature['replaces_previous'] = empty($feature['multiple']);
            $feature['display_type'] = 'feature';
            $feature['rule_type'] = 'feature_' . $feature['id'];
        }
        unset($feature);

        return $features;
    }

    protected function getSetsWithGroups()
    {
        $set_model = new shopSetModel();
        $sets = $set_model->select('`id`, `group_id`, `name`, `type`, `sort`')->fetchAll('id');

        $set_group_model = new shopSetGroupModel();
        $groups = $set_group_model->getAll('id');

        foreach($groups as &$group) {
            $group['sets'] = [];
        }
        unset($group);

        $result = [];
        foreach($sets as $set) {
            $set = [
                    'is_group' => false,
                    'set_id' => $set['id'],
                ] + $set;
            if (empty($set['group_id']) || empty($groups[$set['group_id']])) {
                $set['group_id'] = null;
                $result[] = $set;
            } else {
                $groups[$set['group_id']]['sets'][] = $set;
            }
        }

        foreach($groups as $group) {
            $group = [
                    'is_group' => true,
                    'group_id' => $group['id'],
                ] + $group;
            $result[] = $group;
        }

        $sort = array_column($result, 'sort');
        array_multisort($sort, SORT_ASC, $result);

        return $result;
    }

    /**
     * @param string $entity = 'filter' | 'rules'
     * @return mixed|shopFilterModel|shopPresentationModel
     * @throws waException
     */
    protected function getModel($entity = 'filter')
    {
        if (empty($models[$entity])) {
            switch ($entity) {
                case 'filter';
                    $models[$entity] = new shopFilterModel();
                    break;
                case 'rules';
                    $models[$entity] = new shopFilterRulesModel();
                    break;
                default:
                    throw new waException('Unknown entity ' . $entity);
            }
        }

        return $models[$entity];
    }
}
