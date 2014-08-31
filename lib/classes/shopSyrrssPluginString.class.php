<?php
/**
 * This file is a part of "RSS Feed of Products" plugin for ShopScript 5
 *
 * @author Serge Rodovnichenko <sergerod@gmail.com>
 *
 * @license http://www.webasyst.com/terms/#eula Webasyst Commercial
 * @version 1.0.0
 */

/**
 * String functions
 *
 * @package webasyst.shop.plugin.syrrss
 */
class shopSyrrssPluginString
{
    /**
     * Generate a random UUID (v4)
     * 
     * @see http://www.ietf.org/rfc/rfc4122.txt
     * @return string
     */
    public static function uuid()
    {
        if(class_exists("waString") && method_exists("waString", "uuid")) {
            return waString::uuid();
        }

        $uuid = sprintf('%04x%04x-%04x-%04x-%04x-%04x%04x%04x', // 32 bits for "time_low"
            mt_rand(0, 0xffff), mt_rand(0, 0xffff), // 16 bits for "time_mid"
            mt_rand(0, 0xffff), // 16 bits for "time_hi_and_version",
            // four most significant bits holds version number 4
            mt_rand(0, 0x0fff) | 0x4000, // 16 bits, 8 bits for "clk_seq_hi_res",
            // 8 bits for "clk_seq_low",
            // two most significant bits holds zero and one for variant DCE1.1
            mt_rand(0, 0x3fff) | 0x8000, // 48 bits for "node"
            mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff));

        return $uuid;
    }
}
