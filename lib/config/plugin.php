<?php
/**
 * This file is a part of "RSS Feed of Products" plugin for ShopScript 5
 * 
 * @author Serge Rodovnichenko <sergerod@gmail.com>
 *
 * @license http://www.webasyst.com/terms/#eula Webasyst Commercial
 * @version 1.0.0
 */

return array(
    'name' => _wp('RSS Feed of products'),
    'description' => _wp('Export all or several newest products to the RSS feed'),
    'vendor'=>670917,
    'version'=>'1.0.0',
    'importexport' => 'profiles',
    'export_profile' => TRUE,
    'frontend'    => TRUE,
    'locale' => array('ru_RU'),
    'icon' => 'rss'
);
