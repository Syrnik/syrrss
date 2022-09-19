<?php
/**
 * This file is a part of "RSS Feed of Products" plugin for ShopScript 5
 * 
 * @author Serge Rodovnichenko <serge@syrnik.com>
 *
 * @license http://www.webasyst.com/terms/#eula Webasyst Commercial
 * @version 1.0.0
 */

return array(
    'name' => _wp('RSS товаров'),
    'description' => _wp('Экспорт всех или указанное количество новых товаров в RSS-ленту'),
    'vendor'=>670917,
    'version'=>'1.0.3',
    'importexport' => 'profiles',
    'export_profile' => TRUE,
    'frontend'    => TRUE,
    'locale' => array('ru_RU'),
    'icon' => 'rss',
    'icons' => array(
        16 => 'img/feed.png'
    ),
    'shop_settings' => TRUE
);
