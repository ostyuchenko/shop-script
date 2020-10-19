<?php
/**
 * /products/<id>/sku/
 * Product editor, sku tab.
 */
class shopProdSkuAction extends waViewAction
{
    public function execute()
    {
        $product = $this->getProduct();
        $features = $this->getFeaturesSettings($product);

        // Feature values saved for skus: sku_id => feature code => value
        $product_features_model = new shopProductFeaturesModel();
        $skus_features_values = $product_features_model->getValuesMultiple($features, $product['id'], array_keys($product['skus']));

        $type_model = new shopTypeModel();
        $product_types = $type_model->getTypes(true);

        $features_selectable_model = new shopProductFeaturesSelectableModel();
        $selected_selectable_feature_ids = $features_selectable_model->getProductFeatureIds($product['id']);

        $stocks = $this->getStocks();

        $formatted_features = $this->formatFeatures($features);
        $formatted_product = $this->formatProduct($product, $formatted_features, $selected_selectable_feature_ids, $skus_features_values);
        $formatted_selectable_features = $this->formatSelectableFeatures($formatted_features, $selected_selectable_feature_ids);

        $frontend_urls = shopProdGeneralAction::getFrontendUrls($product)[0];

        $this->view->assign([
            'product'                       => $product,
            'product_types'                 => $product_types,
            'currencies'                    => $this->getCurrencies(),
            'stocks'                        => $stocks,
            'frontend_urls'                 => $frontend_urls,

            'product_sku_types'             => $this->getProductSkuTypes(),
            'new_modification'              => $this->getEmptyModification($product, $formatted_features),
            'new_sku'                       => $this->getEmptySku(),

            'formatted_product'             => $formatted_product,
            'formatted_features'            => $formatted_features,
            'formatted_selectable_features' => $formatted_selectable_features,
        ]);

        $this->setLayout(new shopBackendProductsEditSectionLayout([
            'product' => $product,
        ]));
    }

    protected function getProduct()
    {
        $product_id = waRequest::param('id', '', 'int');
        if ($product_id) {
            $product_model = new shopProductModel();
            $product_data = $product_model->getById($product_id);
        }
        if (empty($product_data)) {
            throw new waException(_w("Unknown product"), 404);
        }
        return new shopProduct($product_data);
    }

    protected function getFeaturesSettings(shopProduct $product)
    {
        $feature_model = new shopFeatureModel();

        // Features attached to product type
        $features = $feature_model->getByType($product->type_id, 'code');
        foreach ($features as $code => $feature) {
            $features[$code]['internal'] = true;
        }

        // Features attached to product directly, but not its type
        $codes = array_diff_key($product->features, $features);
        if ($codes) {
            $features += $feature_model->getByField('code', array_keys($codes), 'code');
        }

        // Fetch values for selectable features
        $selectable_features = array();
        foreach ($features as $code => $feature) {
            $features[$code]['feature_id'] = intval($feature['id']);
            if (!empty($feature['selectable'])) {
                $selectable_features[$code] = $feature;
            }
        }
        $selectable_features = $feature_model->getValues($selectable_features);
        foreach ($selectable_features as $code => $feature) {
            if (isset($features[$code]) && isset($feature['values'])) {
                $features[$code]['values'] = $feature['values'];
            }
        }

        return $features;
    }

    protected function formatProduct($product, $features, $selected_selectable_feature_ids, $skus_features_values)
    {
        $skus = [];

        $getPhotos = function($product) {
            $result = [];

            $_images = $product->getImages('thumb');

            foreach ($_images as $_image) {
                $result[$_image["id"]] = [
                    "id" => $_image["id"],
                    "url" => $_image["url_thumb"],
                    "description" => $_image["description"]
                ];
            }

            return $result;
        };
        $photos = $getPhotos($product);

        // BADGES
        $badge_id = null;
        $badges = shopProductModel::badges();

        foreach($badges as $_badge_id => &$badge) {
            $badge["id"] = $_badge_id;
        }

        $badge_example_html = "<div class=\"badge\" style=\"background-color: #a1fcff;\"><span>" . _w("YOUR TEXT") . "</span></div>";

        $badges[""] = [
            "id" => "",
            "name" => _w("Custom"),
            "code" => $badge_example_html,
            "code_model" => $badge_example_html
        ];

        if ($product["badge"] === "") {
            $product["badge"] = null;
        }

        if (!empty($product["badge"])) {
            $badges[""]["code"] = $badges[""]["code_model"] = $product["badge"];
            $badge_id =  (empty($badges[$product["badge"]]) ? "" : $product["badge"]);
        }

        // Features that are rendered as checklists for product and allow multiple selection,
        // for SKUs must be rendered as a single select (no multiple selection).
        // This loop corrects for that.
        $_corrected_features = [];
        foreach ($features as $feature) {
            $_corrected_features[] = self::formatModificationFeature($feature);
        }

        foreach ($product['skus'] as $modification) {
            // TODO: its a new options of product
            $modification["status"] = "enabled";
            $modification["available"] = (boolean)$modification["available"];

            // Добавляем информацию о частной фотке модификации
            $modification["photo"] = null;
            if ( !empty($modification["image_id"]) ) {
                if ($modification["id"] === $product["sku_id"]) {
                    $product["image_id"] = $modification["image_id"];
                }
                if ( !empty($photos[$modification["image_id"]]) ) {
                    $modification["photo"] = $photos[$modification["image_id"]];
                }
            }

            // SELECTABLE_FEATURES
            $_features = [];
            $_selectable_features = [];
            if ( !empty($features) ) {
                $_features_values = ifset($skus_features_values, $modification['id'], []);
                $_formatted_features = self::formatFeaturesValues($_corrected_features, $_features_values);
                foreach ($_formatted_features as $feature) {
                    if ( !empty($feature["available_for_sku"]) ) {
                        if (in_array($feature["id"], $selected_selectable_feature_ids)) {
                            $_selectable_features[] = $feature;
                        } else {
                            $_features[] = $feature;
                        }
                    }
                }
            }

            $modification["features"] = $_features;
            $modification["features_selectable"] = $_selectable_features;

            // MODIFICATIONS
            if ($modification['sku']) {
                if (empty($skus[$modification['sku']])) {
                    $skus[$modification['sku']] = [
                        'sku' => $modification['sku'],
                        'name' => $modification['name'],
                        'sku_id' => null,
                        'modifications' => [],
                    ];
                }
                $skus[$modification['sku']]['modifications'][] = $modification;

                if ($product["sku_id"] === $modification['id']) {
                    $skus[$modification['sku']]["sku_id"] = $modification['id'];
                }

            } else {
                $_id = uniqid($modification['id'], true);
                $skus[$_id] = [
                    'sku' => $modification['sku'],
                    'name' => $modification['name'],
                    'sku_id' => ($product["sku_id"] === $modification['id'] ? $modification['id'] : null),
                    'modifications' => [$modification],
                ];
            }
        }

        $photo = ( !empty($photos) ? $photos[$product["image_id"]] : null );
        $_normal_mode = ($product["sku_count"] > 1);

        return [
            "id"              => $product["id"],
            "name"            => $product["name"],
            "badges"          => array_values($badges),
            "badge_id"        => $badge_id,
            "sku_id"          => $product["sku_id"],
            "sku_type"        => $product["sku_type"],
            "currency"        => $product["currency"],
            "skus"            => array_values($skus),
            "image_id"        => $product["image_id"],
            "photo"           => $photo,
            "photos"          => array_values($photos),

            // Feature values saved for product: feature code => value (format depends on feature type)
            "features" => self::formatFeaturesValues($features, $product['features']),

            // front-side options
            "normal_mode"        => $_normal_mode,
            "normal_mode_switch" => $_normal_mode
        ];
    }

    protected function formatFeatures($features)
    {
        $result = array();

        $setUnits = function(&$feature, $units) {
            if (!empty($units)) {
                $_is_first = true;
                foreach ($units as $unit) {
                    if ($_is_first) {
                        if (empty($feature["default_unit"])) {
                            $feature["default_unit"] = $unit["value"];
                        }
                        $_is_first = false;
                    }

                    $_unit = [
                        "name" => $unit["title"],
                        "value" => $unit["value"]
                    ];

                    if ($_unit["value"] === $feature["default_unit"]) {
                        $feature["active_unit"] = $_unit;
                    }

                    $feature["units"][] = $_unit;
                }
            }
        };

        foreach ($features as $feature) {
            $feature["available_for_sku"] = (bool)$feature["available_for_sku"];
            $feature["visible_in_frontend"] = ($feature["status"] === "public");
            $feature["selectable"] = (bool)$feature["selectable"];
            $feature["multiple"] = (bool)$feature["multiple"];

            // TODO
            $feature["render_type"] = null;
            $feature["units"] = [];
            $feature["active_option"] = null;
            $feature["default_unit"] = ifset($feature, "default_unit", null);
            $feature["options"] = [];

            if ($feature["selectable"]) {
                if ($feature["multiple"]) {
                    $units = shopDimension::getUnits($feature["type"]);
                    $setUnits($feature, $units);

                    $feature["render_type"] = "checkbox";
                    foreach ($feature["values"] as $value_id => $value) {
                        $feature["options"][] = [
                            "name" => (string)$value,
                            "value" => (string)$value
                        ];
                    }
                    $feature["can_add_value"] = true;

                } else {
                    $units = shopDimension::getUnits($feature["type"]);
                    $setUnits($feature, $units);

                    $feature["render_type"] = "select";
                    $feature["options"][] = [
                        "name" => _w("Not defined"),
                        "value" => ""
                    ];

                    foreach ($feature["values"] as $value_id => $value) {
                        $feature["options"][] = [
                            "name" => (string)$value,
                            "value" => (string)$value
                        ];
                    }
                    $feature["active_option"] = reset($feature["options"]);
                    $feature["can_add_value"] = true;
                }
            } else {
                if ((strpos($feature["type"],'2d') === 0) || (strpos($feature["type"],'3d') === 0)) {
                    $feature["render_type"] = "field";
                    $_type = substr($feature["type"],3);
                    if (strpos($_type,'dimension') === 0) {
                        $units = shopDimension::getUnits($_type);
                        $setUnits($feature, $units);

                        $d = intval($feature["type"]);
                        for ($i = 0; $i < $d; $i++) {
                            $feature["options"][] = [
                                "name"  => "",
                                "value" => ""
                            ];
                        }
                    } else {
                        for ($i=0; $i < intval($feature["type"]); $i++) {
                            $feature["options"][] = [
                                "name" => "",
                                "value" => ""
                            ];
                        }
                    }

                } elseif (strpos($feature["type"],'dimension') === 0) {
                    $feature["render_type"] = "field";
                    $units = shopDimension::getUnits($feature["type"]);
                    $setUnits($feature, $units);

                    $feature["options"] = [
                        [
                            "name" => "",
                            "value" => ""
                        ]
                    ];

                } elseif (strpos($feature["type"],'range') === 0) {
                    $units = shopDimension::getUnits($feature["type"]);
                    $setUnits($feature, $units);

                    if ($feature["type"] == 'range.date') {
                        $feature["render_type"] = "range.date";
                        $feature["options"]     = [
                            [
                                "name"  => "",
                                "value" => ""
                            ],
                            [
                                "name"  => "",
                                "value" => ""
                            ]
                        ];
                    } elseif ($feature["type"] == 'range.volume') {
                        $feature["render_type"] = "range.volume";
                        $feature["options"] = [
                            [
                                "name"  => "",
                                "value" => ""
                            ],
                            [
                                "name"  => "",
                                "value" => ""
                            ]
                        ];
                    } else {
                        $feature["render_type"] = "range";
                        $feature["options"] = [
                            [
                                "name"  => "",
                                "value" => ""
                            ],
                            [
                                "name"  => "",
                                "value" => ""
                            ]
                        ];
                    }

                } elseif (strpos($feature["type"],'text') === 0) {
                    $feature["render_type"] = "textarea";
                    $feature["options"] = [
                        [
                            "name" => "",
                            "value" => ""
                        ]
                    ];

                } elseif (strpos($feature["type"],'color') === 0) {
                    $feature["render_type"] = "color";
                    $feature["options"] = [
                        [
                            "name" => _w("color name"),
                            "value" => "",
                            "code" => ""
                        ]
                    ];

                } elseif (strpos($feature["type"],'boolean') === 0) {
                    $feature["render_type"] = "select";
                    $feature["options"] = [
                        [
                            "name" => _w("Not defined"),
                            "value" => ""
                        ],
                        [
                            "name" => _w("Yes"),
                            "value" => "1"
                        ],
                        [
                            "name" => _w("No"),
                            "value" => "0"
                        ]
                    ];
                    $feature["active_option"] = reset($feature["options"]);
                    $feature["can_add_value"] = false;

                } elseif (strpos($feature["type"],'divider') === 0) {
                    $feature["render_type"] = "divider";
                    $feature["options"] = [
                        [
                            "name" => $feature["code"],
                            "value" => "-"
                        ]
                    ];

                } elseif (strpos($feature["type"],'date') === 0) {
                    $feature["render_type"] = "field.date";
                    $feature["options"] = [
                        [
                            "name" => "",
                            "value" => ""
                        ]
                    ];

                } else {
                    $feature["render_type"] = "field";
                    $feature["options"] = [
                        [
                            "name" => "",
                            "value" => ""
                        ]
                    ];
                }
            }

            unset($feature["builtin"]);
            unset($feature["count"]);
            unset($feature["feature_id"]);
            unset($feature["parent_id"]);
            unset($feature["status"]);
            unset($feature["values"]);
            $result[] = $feature;
        }

        return $result;
    }

    // Features that are rendered as checklists for product and allow multiple selection,
    // for SKUs must be rendered as a single select (no multiple selection).
    // This loop corrects for that.
    protected function formatModificationFeature($feature)
    {
        if ($feature["render_type"] === "checkbox") {
            $feature["render_type"] = "select";

            array_unshift($feature["options"],  [
                "name" => _w("Not defined"),
                "value" => ""
            ]);

            $feature["active_option"] = reset($feature["options"]);
        }

        /*
        // Когда-то добавлять новые значения в "выбранных характеристиках" было нельзя, потом можно. Оставлю на случай если вдруг снова станет нельзя :)
        // Ты знал, ты знал.
        */
        if ($feature["render_type"] === "select") {
            $feature["can_add_value"] = false;
        }

        return $feature;
    }

    protected function formatFeaturesValues($features, $values) {
        $result = [];

        foreach ($features as $feature) {
            switch ($feature["render_type"]) {
                case "select":
                    if (isset($values[$feature["code"]])) {
                        $_feature_value = $values[$feature["code"]];

                        $_active_value = null;
                        if ($_feature_value instanceof shopBooleanValue) {
                            $_active_value = (string)$values[$feature["code"]]['value'];
                        } else {
                            $_active_value = (string)$_feature_value;
                        }

                        foreach ($feature["options"] as $_option) {
                            if ($_option["value"] === $_active_value) {
                                $feature["active_option"] = $_option;
                                break;
                            }
                        }
                    }
                    break;

                case "checkbox":
                    $_active_array = [];
                    $_is_array = false;
                    if (!empty($values[$feature["code"]])) {
                        $_feature_value = $values[$feature["code"]];
                        if (is_array($_feature_value)) {
                            foreach ($_feature_value as $_value) {
                                $_active_array[] = (string)$_value;
                            }
                            $_is_array = true;
                        } else {
                            $_active_array[] = (string)$_feature_value;
                        }
                    }

                    $_active_option = null;

                    foreach ($feature["options"] as &$option) {
                        $_is_active = in_array($option["value"], $_active_array);
                        $option["active"] = $_is_active;
                        if (!$_is_array && $_is_active) {
                            $_active_option = $option;
                        }
                    }

                    if (!$_is_array) {
                        $feature["active_option"] = ($_active_option ? $_active_option : reset( $feature["options"] ) );
                    }
                    break;

                case "textarea":
                    $_feature_value = ifset($values, $feature["code"], "");
                    $feature["value"] = $_feature_value;
                    break;

                case "field":
                    if (!empty($values[$feature["code"]])) {
                        $_feature_value = $values[$feature["code"]];

                        if ($_feature_value instanceof shopDimensionValue) {
                            // dimension: one value with measurement unit
                            $feature["options"][0]["value"] = (string)$_feature_value['value'];
                            $_unit_value = (string)$_feature_value['unit'];
                            foreach ($feature["units"] as $_unit) {
                                if ($_unit["value"] === $_unit_value) {
                                    $feature["active_unit"] = $_unit;
                                    break;
                                }
                            }

                        } else if ($_feature_value instanceof shopCompositeValue) {
                            // composite dimension (N x N x N): several values with measurement unit
                            $fields_count = 3;
                            if ('2d' === substr($feature["type"], 0, 2)) {
                                $fields_count = 2;
                            }
                            for ($i = 0; $i < $fields_count; $i++) {
                                if (isset($_feature_value[$i])) {
                                    $_subvalue = $_feature_value[$i];
                                    if ($_subvalue instanceof shopDimensionValue) {
                                        $feature["options"][$i]["value"] = (string)$_subvalue['value'];
                                    } else {
                                        $feature["options"][$i]["value"] = (string)$_subvalue;
                                    }
                                }
                            }

                            if (!empty($_feature_value['0']['unit'])) {
                                $_unit_value = (string)$_feature_value[0]['unit'];
                                foreach ($feature["units"] as $_unit) {
                                    if ($_unit["value"] === $_unit_value) {
                                        $feature["active_unit"] = $_unit;
                                        break;
                                    }
                                }
                            }

                        } else {
                            // single value without measurement unit
                            $feature["options"][0]["value"] = (string)$_feature_value;
                        }
                    }
                    break;

                case "field.date":
                    if (!empty($values[$feature["code"]])) {
                        $_feature_value = $values[ $feature["code"] ];
                        if ( $_feature_value instanceof shopDateValue ) {
                            if ( !empty($_feature_value["timestamp"]) ) {
                                $_date = date( "Y-m-d", $_feature_value["timestamp"] );
                                $feature["options"][0]["value"] = (string) $_date;
                            }
                        }
                    }
                    break;

                case "color":
                    if (!empty($values[$feature["code"]])) {
                        $_feature_value = $values[ $feature["code"] ];
                        if ($_feature_value instanceof shopColorValue) {
                            if ( !empty($_feature_value["value"]) ) {
                                $feature["options"][0]["value"] = (string)$_feature_value["value"];
                            }
                            if ( !empty($_feature_value["code"]) ) {
                                $feature["options"][0]["code"] = $_feature_value['hex'];
                            }
                        }
                    }
                    break;

                case "range":
                    if (!empty($values[$feature["code"]])) {
                        $_feature_value = $values[$feature["code"]];
                        if ($_feature_value instanceof shopRangeValue) {
                            if ( !empty($_feature_value["begin"]) ) {
                                $feature["options"][0]["value"] = (string)$_feature_value["begin"];
                            }
                            if ( !empty($_feature_value["end"]) ) {
                                $feature["options"][1]["value"] = (string)$_feature_value["end"];
                            }
                        }
                    }
                    break;

                case "range.volume":
                    if (!empty($values[$feature["code"]])) {
                        $_feature_value = $values[$feature["code"]];
                        $_unit_value = null;
                        if ($_feature_value instanceof shopRangeValue) {
                            if ( !empty($_feature_value["begin"]) && ($_feature_value["begin"] instanceof shopDimensionValue) ) {
                                $feature["options"][0]["value"] = (string)$_feature_value["begin"]["value"];
                                $_unit_value = (string)$_feature_value["begin"]['unit'];
                            }
                            if ( !empty($_feature_value["end"]) && ($_feature_value["end"] instanceof shopDimensionValue) ) {
                                $feature["options"][1]["value"] = (string)$_feature_value["end"]["value"];
                                $_unit_value = (string)$_feature_value["end"]['unit'];
                            }
                        }

                        if (!empty($_unit_value)) {
                            foreach ($feature["units"] as $_unit) {
                                if ($_unit["value"] === $_unit_value) {
                                    $feature["active_unit"] = $_unit;
                                    break;
                                }
                            }
                        }
                    }
                    break;

                case "range.date":
                    if (!empty($values[$feature["code"]])) {
                        $_feature_value = $values[$feature["code"]];
                        if ($_feature_value instanceof shopRangeValue) {
                            if (!empty($_feature_value["begin"]["timestamp"])) {
                                $_start_date = date("Y-m-d", $_feature_value["begin"]["timestamp"]);
                                $feature["options"][0]["value"] = (string)$_start_date;
                            }
                            if (!empty($_feature_value["end"]["timestamp"])) {
                                $_end_date = date( "Y-m-d", $_feature_value["end"]["timestamp"] );
                                $feature["options"][1]["value"] = (string) $_end_date;
                            }
                        }
                    }
                    break;

                default:
                    break;
            }

            $result[] = $feature;
        }

        return $result;
    }

    protected function formatSelectableFeatures($features, $selected_selectable_feature_ids)
    {
        $result = [];

        foreach ($features as $_feature) {
            // range, 2d and 3d features are not supported as selectable
            $is_composite = preg_match('~^(2d|3d|range)\.~', $_feature['type']);
            if (!empty($_feature['available_for_sku']) && !$is_composite) {
                $disabled = !in_array($_feature["render_type"], ["select", "checkbox"]);
                $result[] = [
                    "id"          => $_feature["id"],
                    "name"        => $_feature["name"],
                    "disabled"    => $disabled,
                    "render_type" => $_feature["render_type"],
                    "active"      => in_array( $_feature["id"], $selected_selectable_feature_ids ),
                ];
            }
        }

        return $result;
    }

    protected function getCurrencies()
    {
        $result = [];

        $model = new shopCurrencyModel();
        $currencies = $model->getCurrencies();

        foreach ($currencies as $_currency) {
            $result[$_currency["code"]] = [
                "code" => $_currency["code"],
                "title" => $_currency["title"]
            ];
        }

        return $result;
    }

    protected function getStocks()
    {
        $stocks = shopHelper::getStocks(false);

        foreach ($stocks as $key => &$_stock) {
            $_is_virtual = isset($_stock["substocks"]);
            $_stock["id"] = $key;
            $_stock["is_virtual"] = $_is_virtual;
        }

        return $stocks;
    }

    protected function getEmptySku()
    {
        return [
            "name"          => "",
            "sku"           => mb_strtolower(sprintf('%s_', _w("SKU"))), // Это поле является основой для группировки модификаций, для новодобавленных артикулов оно генерируется на стороне JS (добавляется индекс)
            "sku_id"        => null,
            "modifications" => [],
            "expanded"      => true,
            "render_skus"   => true
        ];
    }

    protected function getEmptyModification($product, $features)
    {
        $result = [
            "id"                  => null,
            "product_id"          => $product["id"],
            "sku"                 => null,
            "name"                => null,
            "image_id"            => null,
            "price"               => 0,
            "purchase_price"      => 0,
            "compare_price"       => 0,
            "count"               => null,

            "available"           => true,
            "status"              => "enabled",

            "features"            => [],

            // will be set at front
            "stock"               => [],
            "features_selectable" => []
        ];

        if ( !empty($features) ) {
            foreach ($features as $feature) {
                if (!empty($feature["available_for_sku"])) {
                    $result["features"][] = self::formatModificationFeature($feature);
                }
            }
        }

        return $result;
    }

    protected function getProductSkuTypes()
    {
        return [
            shopProductModel::SKU_TYPE_FLAT => [
                "id" => shopProductModel::SKU_TYPE_FLAT,
                "name" => _w("By SKU name")
            ],
            shopProductModel::SKU_TYPE_SELECTABLE => [
                "id" => shopProductModel::SKU_TYPE_SELECTABLE,
                "name" => _w("By features such as size or color")
            ],
        ];
    }
}
