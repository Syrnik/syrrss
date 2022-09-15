<?php
/**
 * Тупой копипаст из Yandexmarket
 *
 * @author serge
 */
declare(strict_types=1);

/**
 * @Controller frontend
 */
class shopSyrrssPluginFrontendActions extends waActions
{
    /**
     * @ControllerAction frontend/feed
     * @return void
     * @throws waException
     */
    public function feedAction()
    {
        /** @var shopSyrrssPlugin $plugin */
        $plugin = wa()->getPlugin('syrrss');

        $profile_helper = new shopImportexportHelper('syrrss');

        list($path, $profile_id) = $plugin->getInfoByHash(waRequest::param('hash'));
        
        if ($profile_id) {
            $profile = $profile_helper->getConfig($profile_id);
            
            if (!$profile) {
                throw new waException('File not found', 404);
            }
            
            $lifetime = ifset($profile['config']['lifetime'], 0);
            if ($lifetime && (!file_exists($path) || (time() - filemtime($path) > $lifetime))) {
                waRequest::setParam('profile_id', $profile_id);

                $runner = new shopSyrrssPluginRunController();
                $_POST['processId'] = null;

                $moved = false;
                $ready = false;
                do {
                    ob_start();
                    if (empty($_POST['processId'])) {
                        $_POST['processId'] = $runner->processId;
                    } else {
                        sleep(1);
                    }
                    if ($ready) {
                        $_POST['cleanup'] = true;
                        $moved = true;
                    }
                    $runner->execute();
                    $out = ob_get_clean();
                    $result = json_decode($out, true);
                    $ready = !empty($result) && is_array($result) && ifempty($result['ready']);
                } while (!$ready || !$moved);
                //TODO check errors
            }
        }

        waFiles::readFile($path, waRequest::get('download') ? 'feed.xml' : null);
    }
}
