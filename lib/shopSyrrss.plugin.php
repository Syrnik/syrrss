<?php
/**
 * This file is a part of "RSS Feed of Products" plugin for ShopScript
 *
 * @author Serge Rodovnichenko <serge@syrnik.com>
 *
 * @license http://www.webasyst.com/terms/#eula Webasyst Commercial
 * @version 1.0.3
 */

declare(strict_types=1);

/**
 * Main plugin class
 */
class shopSyrrssPlugin extends shopPlugin
{

    const PLUGIN_ID = 'syrrss';

    /**
     *
     * @param string $file
     * @return string
     * @throws waException
     */
    public static function path(string $file = 'rss.xml'): string
    {
        return waSystem::getInstance()->getDataPath('plugins/syrrss/' . $file, false, 'shop');
    }

    /**
     *
     * @param int $profile_id
     * @return string|null
     * @throws waException
     */
    public function getHash(int $profile_id = 0): ?string
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
            $updated = false;
            if ((count($uuid) == 1) && isset($uuid[0])) {
                $uuid[$profile_id] = $uuid[0];
                $updated = true;
            } elseif (!isset($uuid[$profile_id])) {
                $uuid[$profile_id] = waString::uuid();
                $updated = true;
            }
            if ($updated) {
                $this->saveSettings(array('uuid' => $uuid));
            }
        }

        return $uuid[$profile_id] ?? null;
    }

    /**
     * @param string $hash
     * @return array
     * @throws waException
     * @noinspection DuplicatedCode
     */
    public function getInfoByHash(string $hash): array
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
                $path = self::path($profile_id . '.xml');
            }
        }
        return array($path, $profile_id);
    }

}
