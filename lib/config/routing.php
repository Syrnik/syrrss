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
    "rssfeed/<hash:[0-9a-z]{8}-[0-9a-z]{4}-[0-9a-z]{4}-[0-9a-z]{4}-[0-9a-z]{12}>.xml" => array(
        "plugin" => "syrrss",
        "module" => "frontend",
        "action" => "feed"
    )
);