<?php

class shopSettingsImagesAction extends waViewAction
{
    /**
     * @var array
     */
    protected $settings;

    public function execute()
    {
        $this->settings = $this->getConfig()->getOption(null);
        if (waRequest::getMethod() == 'post') {
            $this->save($this->settings);
            $this->view->assign('saved', 1);
        }
        $this->settings['image_sizes'] = array(
            'system' => $this->formatSizes($this->getConfig()->getImageSizes('system')),
            'custom' => $this->formatSizes((array)$this->settings['image_sizes'])
        );
        $this->view->assign('settings', $this->settings);
    }

    protected function formatSizes($sizes)
    {
        $result = array();
        foreach ($sizes as $size) {
            $size_info = shopImage::parseSize((string)$size);
            $type   = $size_info['type'];
            $width  = $size_info['width'];
            $height = $size_info['height'];
            if ($type == 'max' || $type == 'crop' || $type == 'width') {
                $result[] = array($type => $width);
            } else if ($type == 'height') {
                $result[] = array($type => $height);
            } elseif ($type == 'rectangle') {
                $result[] = array('rectangle' => array($width, $height));
            }
        }
        return $result;
    }

    protected function checkSize($size)
    {
        $size = (int)$size;
        if ($size <= 0) {
            return false;
        }
        if ($this->settings['image_thumbs_on_demand'] && $size > $this->settings['image_max_size']) {
            return $this->settings['image_max_size'];
        }
        return $size;
    }

    public function save(&$settings)
    {
        $config = $this->getConfig();
        $settings['image_sharpen'] = waRequest::post('image_sharpen') ? 1 : 0;
        $settings['image_save_original'] = waRequest::post('image_save_original') ? 1 : 0;
        $settings['image_thumbs_on_demand'] = waRequest::post('image_thumbs_on_demand') ? 1 : 0;
        
        if ($settings['image_thumbs_on_demand']) {
            $settings['image_max_size'] = waRequest::post('image_max_size', 1000, waRequest::TYPE_INT);
            $big_size = $config->getImageSize('big');
            if ($settings['image_max_size'] < $big_size) {
                $settings['image_max_size'] = $big_size;
            }
        }

        // delete sizes
        if ($delete = waRequest::post('delete', array(), waRequest::TYPE_ARRAY_INT)) {
            foreach ($delete as $k) {
                if (isset($settings['image_sizes'][$k])) {
                    unset($settings['image_sizes'][$k]);
                }
            }
        }

        // sizes
        if ($types = waRequest::post('size_type', array())) {
            $sizes = waRequest::post('size', array());
            $width = waRequest::post('width', array());
            $height = waRequest::post('height', array());
            foreach ($types as $k => $type) {
                if ($type == 'rectangle') {
                    $w = $this->checkSize($width[$k]);
                    $h = $this->checkSize($height[$k]);
                    if ($w && $h) {
                        $settings['image_sizes'][] = $w.'x'.$h;
                    }
                } else {
                    $size = $this->checkSize($sizes[$k]);
                    if (!$size) {
                        continue;
                    }
                    switch ($type) {
                        case 'crop':
                            $settings['image_sizes'][] = $size.'x'.$size;
                            break;
                        case 'height':
                            $settings['image_sizes'][] = '0x'.$size;
                            break;
                        case 'width':
                            $settings['image_sizes'][] = $size.'x0';
                            break;
                        case 'max':
                            $settings['image_sizes'][] = $size;
                            break;
                    }
                }
            }
        }

        $settings['image_sizes'] = array_values((array)$settings['image_sizes']);
        $config_file = $config->getConfigPath('config.php');
        
        $settings['image_save_quality'] = waRequest::post('image_save_quality', '', waRequest::TYPE_STRING_TRIM);
        if ($settings['image_save_quality'] == '') {
            $settings['image_save_quality'] = 90;
        } else {
            $settings['image_save_quality'] = (float) $settings['image_save_quality'];
            if ($settings['image_save_quality'] < 0) {
                $settings['image_save_quality'] = 0;
            }
            if ($settings['image_save_quality'] > 100) {
                $settings['image_save_quality'] = 100;
            }
            $settings['image_save_quality'] = str_replace(',', '.', $settings['image_save_quality']);
        }
        
        waUtils::varExportToFile($settings, $config_file);
    }
}
