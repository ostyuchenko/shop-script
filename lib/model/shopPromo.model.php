<?php

class shopPromoModel extends waModel
{
    const STATUS_ACTIVE = 'active';
    const STATUS_PLANNED = 'planned';
    const STATUS_COMPLETED = 'completed';

    protected $table = 'shop_promo';

    public function getPromo($id)
    {
        $promo = $this->getById($id);

        if (!empty($promo)) {
            $promo['image_url'] = shopHelper::getPromoImageUrl($promo['id'], $promo['ext']);

            $promo_routes_model = new shopPromoRoutesModel();
            $routes = $promo_routes_model->getByField('promo_id', $promo['id'], 'storefront');
            $promo['routes'] = $routes;

            $promo_rules_model = new shopPromoRulesModel();
            $rules = $promo_rules_model->getByField('promo_id', $promo['id'], true);
            $promo['rules'] = $rules;
        }

        return $promo;
    }

    public function delete($promo_id)
    {
        $promo = $this->getPromo($promo_id);

        if (empty($promo)) {
            return true;
        }

        $this->deleteById($promo_id);
        (new shopPromoRoutesModel())->deleteByField(['promo_id' => $promo_id]);
        (new shopPromoRulesModel())->deleteByField(['promo_id' => $promo_id]);
        (new shopPromoOrdersModel())->deleteByField(['promo_id' => $promo_id]);

        if (!empty($promo['ext']) && !empty($promo['image_url'])) {
            $promo_image_path = wa('shop')->getDataPath('promos/'.$promo['id'].'.'.$promo['ext'], true, 'shop', false);
            if (file_exists($promo_image_path)) {
                try {
                    waFiles::delete($promo_image_path);
                } catch (Exception $e) {

                }
            }
        }
    }

    public function getList($params = [], &$total_count = null)
    {
        $offset = $limit = null;
        // LIMIT
        if (isset($params['offset']) || isset($params['limit'])) {
            $offset = (int)ifset($params['offset'], 0);
            $limit = (int)ifset($params['limit'], shopConfig::ROWS_PER_PAGE);
        }

        $joins = $cond = $vars = [];

        if (!empty($params['storefront']) && is_scalar($params['storefront'])) {
            $storefront = $params['storefront'];
            $storefronts = [
                shopPromoRoutesModel::FLAG_ALL,
                rtrim($storefront, '/') . '/',
                rtrim($storefront, '/'),
            ];
            $vars['storefronts'] = $storefronts;
            $joins['promo_routes'] = 'shop_promo_routes AS r ON p.id = r.promo_id';
            $cond[] = "r.storefront IN (:storefronts)";
        }

        if (!empty($params['rule_type'])) {
            $rule_types = (array)$params['rule_type'];
            $vars['rule_types'] = $rule_types;
            $joins[] = 'shop_promo_rules AS rl ON p.id = rl.promo_id';
            $cond[] = "rl.rule_type IN (:rule_types)";
        }

        $vars['datetime'] = date('Y-m-d H:i:s');

        $order_by = 'ORDER BY p.id ASC';

        $status = ifempty($params, 'status', null);
        if ($status === self::STATUS_ACTIVE) {
            $cond[] = "(p.start_datetime IS NULL OR p.start_datetime <= :datetime)";
            $cond[] = "(p.finish_datetime IS NULL OR p.finish_datetime >= :datetime)";
            if (empty($joins['promo_routes'])) {
                $joins['promo_routes'] = 'shop_promo_routes AS r ON p.id = r.promo_id';
            }
            $order_by = "ORDER BY MIN(IF(r.storefront = '%all%', 100500, r.sort)) ASC, p.id ASC";
        } elseif ($status === self::STATUS_PLANNED) {
            $cond[] = "p.start_datetime IS NOT NULL AND p.start_datetime > :datetime";
            $order_by = "ORDER BY p.start_datetime ASC";
        } elseif ($status === self::STATUS_COMPLETED) {
            $cond[] = "p.finish_datetime IS NOT NULL AND p.finish_datetime < :datetime";
            $order_by = "ORDER BY p.finish_datetime DESC";
        }

        if (!empty($params['ignore_paused'])) {
            $cond[] = "(p.enabled != 0)";
        }

        // Order completed promos
        if ($status === self::STATUS_COMPLETED && !empty($params['sort']['field'])) {
            $fields_for_sort = ['title', 'start_datetime', 'finish_datetime', 'orders_count'];
            if (in_array($params['sort']['field'], $fields_for_sort)) {
                $sort_field = $params['sort']['field'];
            }
            $direction = ifempty($params, 'sort', 'direction', 'desc');
            $direction = strtolower($direction) == 'desc' ? 'DESC' : 'ASC';

            if (!empty($sort_field)) {
                if ($sort_field == 'orders_count') {
                    $select_addition = ", COUNT(po.order_id) as orders_count";
                    $joins['promo_orders'] = [
                        'type'      => 'LEFT JOIN',
                        'condition' => 'shop_promo_orders as po ON po.promo_id = p.id'
                    ];
                }
                $order_by = "ORDER BY {$sort_field} {$direction}";
            }
        }

        $join = '';
        foreach ($joins as $j) {
            $join_type = is_array($j) && !empty($j['type']) ? $j['type'] : 'JOIN';
            $join_condition = is_array($j) && !empty($j['condition']) ? $j['condition'] : $j;
            $join .= $join_type .' '.$join_condition."\n";
        }

        if ($cond) {
            $cond = 'WHERE '.join(' AND ', $cond);
        } else {
            $cond = '';
        }

        // Count rows setting
        if(!isset($params['count_results']) && func_num_args() > 1) {
            $params['count_results'] = true;
        }
        if (empty($params['count_results'])) {
            $select = "SELECT p.*";
        } else if ($params['count_results'] === 'only') {
            $select = "SELECT count(*)";
        } else {
            $select = "SELECT SQL_CALC_FOUND_ROWS p.*";
        }

        if (!empty($select_addition)) {
            $select .= $select_addition;
        }

        $sql = "{$select}
                FROM {$this->table} AS p
                {$join}
                {$cond}
                GROUP BY p.id
                {$order_by}";

        // LIMIT
        if ($offset || $limit) {
            $sql .= " LIMIT $offset, $limit";
        }

        $db_result = $this->query($sql, $vars);

        if (empty($params['count_results'])) {
            $promos = $db_result->fetchAll('id');
            $this->workupPromos($promos, $params);
            return $promos;
        } elseif ($params['count_results'] === 'only') {
            $total_count = $db_result->fetchField();
            return $total_count;
        } else {
            $total_count = $this->query('SELECT FOUND_ROWS()')->fetchField();
            $promos = $db_result->fetchAll('id');
            $this->workupPromos($promos, $params);
            return $promos;
        }
    }

    protected function workupPromos(&$promos, $params)
    {
        $promo_ids = array_keys($promos);

        if (!empty($params['with_routes'])) {
            $promo_routes_model = new shopPromoRoutesModel();
            $promo_routes = $promo_routes_model->getByField('promo_id', $promo_ids, true);
            foreach ($promo_routes as $route) {
                if (!empty($promos[$route['promo_id']])) {
                    $promos[$route['promo_id']]['routes'][$route['storefront']] = $route;
                }
            }
        }

        if (!empty($params['with_rules'])) {
            $promo_rules_model = new shopPromoRulesModel();
            $promo_rules = $promo_rules_model->getByField('promo_id', $promo_ids, 'id');
            foreach ($promo_rules as $rule) {
                $promos[$rule['promo_id']]['rules'][$rule['id']] = $rule;
            }
        }

        foreach ($promos as &$promo) {
            $promo['id'] = (int)$promo['id'];

            if (!empty($params['with_images'])) {
                $promo['image_url'] = shopHelper::getPromoImageUrl($promo['id'], $promo['ext']);
            }
        }
        unset($promo);
    }

    public function getByStorefront($storefront, $type='link', $enable_status = null)
    {
        if (!$storefront) {
            return array();
        }

        $sql = "SELECT p.*, r.sort
                FROM {$this->table} AS p
                    JOIN shop_promo_routes AS r
                        ON p.id = r.promo_id
                WHERE r.storefront IN (:storefronts)
                    AND type = :type
                    :enable
                    AND (p.start_datetime IS NULL OR p.start_datetime <= :datetime)
                    AND (p.finish_datetime IS NULL OR p.finish_datetime >= :datetime)
                ORDER BY IF(r.storefront = '%all%', 100500, r.sort) ASC, p.id ASC";

        if ($enable_status === null) {
            $sql = str_replace(':enable', '', $sql);
        } else if ($enable_status) {
            $sql = str_replace(':enable', 'AND p.enabled > 0', $sql);
        } else {
            $sql = str_replace(':enable', 'AND p.enabled <= 0', $sql);
        }

        $storefronts = array(
            rtrim($storefront, '/') . '/',
            rtrim($storefront, '/')
        );

        $vars = [
            'storefronts' => $storefronts,
            'type'        => $type,
            'datetime'    => date('Y-m-d H:i:s'),
        ];

        $result = $this->query($sql, $vars)->fetchAll('id');

        $vars['storefronts'] = shopPromoRoutesModel::FLAG_ALL;

        $result_all = array_diff_key($this->query($sql, $vars)->fetchAll('id'), $result);
        if ($result_all) {

            $max_sort = 0;
            foreach($result as $row) {
                $max_sort = max($max_sort, $row['sort']);
            }

            $values = array();
            foreach($result_all as $row) {
                $max_sort++;
                $row['sort'] = $max_sort;
                $values[] = "('{$row['id']}', '".$this->escape($storefront)."', '{$max_sort}')";
                $result[$row['id']] = $row;
            }

            $sql = "INSERT IGNORE INTO shop_promo_routes (promo_id, storefront, sort) VALUES ".join(',', $values);
            $this->exec($sql);
        }

        return $result;
    }

    public function getDisabled($type='link')
    {
        $sql = "SELECT *
                FROM {$this->table}
                WHERE type=? AND enabled <= 0
                ORDER BY id";
        return $this->query($sql, array($type))->fetchAll('id');
    }

    public function countDisabled($type='link')
    {
        $sql = "SELECT COUNT(*)
                FROM {$this->table}
                WHERE type=? AND enabled <= 0";
        return $this->query($sql, array($type))->fetchField();
    }

    public function countByStatus($filter=[])
    {
        $now = date('Y-m-d H:i:s');
        $status = "IF(start_datetime IS NOT NULL AND start_datetime > '{$now}',
            'planned',
            IF(finish_datetime IS NOT NULL AND finish_datetime < '{$now}',
                'finished',
                'active'
            )
        )";

        $where = $this->getWhereByField($filter);

        $sql = "
            SELECT {$status} AS `status`, COUNT(*) AS `count`
            FROM {$this->table}
            WHERE {$where}
            GROUP BY {$status}
        ";

        $result = $this->query($sql)->fetchAll('status', true) + [
            'active'   => 0,
            'planned'  => 0,
            'finished' => 0,
        ];
        $result['total'] = array_sum($result);
        return $result;
    }
}
