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
 * Main plugin
 *
 * @package webasyst.shop.plugin.syrrss
 */
class shopSyrrssPlugin extends shopPlugin
{
    
    const PLUGIN_ID = 'syrrss';

    /**
     * 
     * @param string $file
     * @return string
     */
    public static function path($file = 'rss.xml')
    {
        $path = waSystem::getInstance()->getDataPath('plugins/syrrss/' . $file, false, 'shop', true);
        return $path;
    }

    /**
     *
     * @param int $profile_id
     * @return string
     */
    public function getHash($profile_id = 0)
    {
        $uuid = $this->getSettings('uuid');
        if (!is_array($uuid)) {
            if ($uuid) {
                $uuid = array(
                    0 => $uuid,
                );
            } else {
                $uuid = array();
            }
        }

        if ($profile_id) {
            $updated = FALSE;
            if ((count($uuid) == 1) && isset($uuid[0])) {
                $uuid[$profile_id] = $uuid[0];
                $updated = TRUE;
            } elseif (!isset($uuid[$profile_id])) {
                $uuid[$profile_id] = waString::uuid();
                $updated = TRUE;
            }
            if ($updated) {
                $this->saveSettings(array('uuid' => $uuid));
            }
        }

        return ifset($uuid[$profile_id]);
    }

    public function getInfoByHash($hash)
    {
        $path = null;
        $uuid = $this->getSettings('uuid');
        $profile_id = null;
        if (!is_array($uuid)) {
            if ($uuid == $hash) {
                $path = self::path();
            }
        } else {
            if ((count($uuid) > 1) && isset($uuid[0])) {
                unset($uuid[0]);
            }
            $profile_id = array_search($hash, $uuid);
            if ($profile_id !== false) {
                $path = self::path($profile_id.'.xml');
            }
        }
        return array($path, $profile_id);
    }

}
