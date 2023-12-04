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
    'name'           => 'RSS товаров',
    'description'    => 'Экспорт всех или указанное количество новых товаров в RSS-ленту',
    'vendor'         => 670917,
    'version'        => '3.0.0',
    'importexport'   => 'profiles',
    'export_profile' => true,
    'frontend'       => true,
    'locale'         => ['ru_RU'],
    'icon'           => 'img/feed.png',
    'img'            => 'img/feed.png',
    'icons'          => [16 => 'img/feed.png']
);
