<?php
/**
 * @author Serge Rodovnichenko <sergerod@gmail.com>
 *
 * @license http://www.webasyst.com/terms/#eula Webasyst Commercial
 * @version 1.0.0
 */

/**
 * RSS feed creation
 *
 * @package webasyst.shop.plugin.syrrss.controller
 */
class shopSyrrssPluginRunController extends waLongActionController
{
    
    /** @var SimpleXmlElement */
    private $rss;
    
    /** @var shopProductsCollection */
    private $collection;

    protected function preExecute()
    {
        parent::preExecute();
        
        $this->getResponse()->addHeader('Content-type', 'application/json')->sendHeaders();
    }

    protected function init()
    {
        $Profile = new shopImportexportHelper('syrrss');
        $Config = waSystem::getInstance()->getConfig();
        
        /** @var shopSyrrssPlugin */
        $Plugin = waSystem::getInstance()->getPlugin('syrrss');

        try {
            
            if(waSystem::getInstance()->getenv('backend')) {
                $profile_config = $this->getProfileOptionsFromRequest();
                $profile_id = $Profile->setConfig($profile_config);
                $Plugin->getHash($profile_id);
            } else {
                
                $profile = $this->getProfile();
                $profile_id = $profile['id'];
                $profile_config = $profile['config'];
            }
            
            $this->data = array_merge($this->data, array(
                'domain'=>$profile_config['domain'],
//                'export'=>$profile_config['export'],
                'hash'=>$profile_config['hash'],
                'offset' => array('offers'=>0),
                'path' => array('offers' => shopSyrrssPlugin::path($profile_id . ".xml")),
                'processed_count' => 0,
                'timestamp' => time(),
                'memory' => memory_get_peak_usage(),
                'memory_avg' => memory_get_usage()
            ));
            
            $this->data["count"] = $this->getCollection()->count();
            
            $this->initRouting();
            
            $this->rss = $this->initRss();
            
            $this->rss->channel->title = $this->xmlEntities($profile_config['channel_name']);
            $this->rss->channel->link = preg_replace('@^https@', 'http', wa()->getRouteUrl('shop/frontend', array(), true));
            $this->rss->channel->description = $this->xmlEntities($profile_config["channel_description"]);

        } catch (waException $e) {
            echo json_encode(array('error'=>$e->getMessage()));
        }
    }
    
    protected function step()
    {
        static $product_collection;
        
        if(!$product_collection) {
            $product_collection = $this->getCollection()->getProducts('*', $this->data["processed_count"], 200, FALSE);
            if(!$product_collection) {
                $this->data["processed_count"] = $this->data["count"];
            }
        }
        
        $step = 0;
        $product = array_shift($product_collection);
        
        while(($step < 50) && $product) {
            
            $this->addItem($product);

            $this->data['processed_count']++;
            $step++;
            $product = array_shift($product_collection);
        }
        
        return TRUE;
    }
    
    protected function finish($filename)
    {
        $result = !!$this->getRequest()->post('cleanup');
        
        
        try {
            if ($result) {
                $file = $this->getTempPath();
                if (file_exists($file)) {
                    waFiles::move($file, $this->data['path']['offers']);
                }
                $this->validate();
            }
        } catch (Exception $ex) {
            $this->error($ex->getMessage());
        }

        $this->info();
        
        return $result;
    }
    
    protected function isDone()
    {
        
        if($this->data["processed_count"] < $this->data["count"]) {
            return FALSE;
        }
        
        return TRUE;
    }
    
    protected function info()
    {
        $interval = empty($this->data["timestamp"]) ? 0 : time() - $this->data['timestamp'];

        $response = array(
            'time'       => sprintf('%d:%02d:%02d', floor($interval / 3600), floor($interval / 60) % 60, $interval % 60),
            'processId'  => $this->processId,
            'progress'   => sprintf("%0.3f%%", 100.0 * $this->data["processed_count"] / $this->data["count"]),
            'ready'      => $this->isDone(),
            'count'      => empty($this->data['count']) ? false : $this->data['count'],
            'memory'     => sprintf('%0.2fMByte', $this->data['memory'] / 1048576),
            'memory_avg' => sprintf('%0.2fMByte', $this->data['memory_avg'] / 1048576),
        );
        
        if($this->isDone()) {
            $response["report"] = $this->report(); // . $this->validateReport();
        }
        
        echo json_encode($response);
    }
    
    private function getProfileOptionsFromRequest()
    {
        $hash = shopImportexportHelper::getCollectionHash();
        
        return array_merge(waRequest::post('config'), array('hash'=>$hash["hash"]));
        
    }
    
    protected function restore()
    {
        $this->loadRss();
        $this->initRouting();
        $this->collection = null;
    }

    protected function save()
    {
        if ($this->rss) {
            $this->rss->asXML($this->getTempPath());
        }
    }

    private function getProfile()
    {
        $Profile = new shopImportexportHelper('syrrss');

        $profile_id = waRequest::param('profile_id');

        if(!$profile_id) {
            throw new waException("Invalid profile", 404);
        }

        $profile = $Profile->getConfig($profile_id);
        if(!$profile) {
            throw new waException("Invalid profile", 404);
        }
        
        return $profile;
    }
    
    private function initRouting()
    {
        $routing = wa()->getRouting();
        $app_id = $this->getAppId();
        $domain_routes = $routing->getByApp($app_id);
        $success = false;
        foreach ($domain_routes as $domain => $routes) {
            foreach ($routes as $route) {
                if ($domain.'/'.$route['url'] == $this->data['domain']) {
                    $routing->setRoute($route, $domain);
                    waRequest::setParam($route);
                    $this->data['base_url'] = parse_url('http://'.preg_replace('@https?://@', '', $domain), PHP_URL_HOST);
                    $success = true;
                    break;
                }
            }
        }
        if (!$success) {
            throw new waException('Error while select routing');
        }
//        $app_settings_model = new waAppSettingsModel();
//        $this->data['app_settings'] = array(
//            'ignore_stock_count' => $app_settings_model->get($app_id, 'ignore_stock_count', 0)
//        );
    }

    /**
     *
     * @internal param string $hash
     * @return shopProductsCollection
     */
    private function getCollection()
    {
        if (!$this->collection) {
            $options = array(
                'frontend' => true,
                'params'=>array(
                    'sort'=>'create_datetime',
                    'order'=>'desc'
                )
            );

            $hash = $this->data['hash'];
            if ($hash == '*') {
                $hash = '';
            }

            $this->collection = new shopProductsCollection($hash, $options);
        }
        return $this->collection;
    }
    
    /**
     * @return SimpleXMLElement
     */
    private function initRss()
    {
        $rss = new SimpleXMLElement('<?xml version="1.0" encoding="UTF-8"?><rss version="2.0"><channel /></rss>');
        
        return $rss;
    }

    /**
     * @param string $file
     * @return string
     */
    private function getTempPath($file = null)
    {
        if (!$file) {
            $file = $this->processId.'.xml';
        }
        return waSystem::getInstance()->getTempPath('plugins/syrrss/', 'shop').$file;
    }

    private function loadRss($path = null)
    {
        if (!$path) {
            $path = $this->getTempPath();
        }

        if (!$this->rss) {
            $this->rss = simplexml_load_file($path);
            if (!$this->rss) {
                throw new waException("Error while read saved XML");
            }
        }
    }
    
    private function addItem($product)
    {
        $item = $this->rss->channel->addChild("item");
        $item->title = $this->xmlEntities(strip_tags($product["name"]));
        $item->link = $this->productUrl($product);
        $item->description = $this->xmlEntities(strip_tags($product["summary"]));
    }
    
    /**
     * 
     * @param string $str
     * @return string
     */
    private function xmlEntities($str)
    {
        return str_replace(array('&','>','<'), array('&amp;','&gt;','&lt;'), $str);
    }
    
    /**
     * 
     * @param array $product
     * @return string
     */
    private function productUrl($product)
    {
        $url = version_compare(PHP_VERSION, '5.3.0', ">=") ? preg_replace_callback('@([^\w\d_/-\?=%&]+)@i', function($a){return rawurlencode(reset($a));}, $product['frontend_url']) : preg_replace_callback('@([^\w\d_/-\?=%&]+)@i', array(__CLASS__, '_rawurlencode'), $product['frontend_url']);
        
        return 'http://'.ifempty($this->data['base_url'], 'localhost').$url;
    }

    /**
     * Old versions of PHP sux
     * 
     * @deprecated since version 1.0.0
     * @param array $a
     * @return string
     */
    private static function _rawurlencode($a)
    {
        return rawurlencode(reset($a));
    }
    
    private function validate()
    {
        $libxml_internal_errors = libxml_use_internal_errors(TRUE);
        $this->loadRss($this->data['path']['offers']);
        
        if(!$this->rss) {
            
            $this->data["error"] = array();
            $err=array();

            foreach(libxml_get_errors() as $error) {
                $this->data["error"][] = array(
                    "level" => "error",
                    "message" => "#{$error->code} [{$error->line}:{$error->column}] {$error->message}"
                );
                $err[] = "#{$error->code} [{$error->line}:{$error->column}] {$error->message}";
            }
            
            $this->error(implode("\n\t", $err));
            libxml_clear_errors();
        }
        
        libxml_use_internal_errors($libxml_internal_errors);
    }

    /**
     * @todo Fuck out a presentation layer from the Controller to the View!
     * @return string
     */
    protected function report()
    {
        $report = '<div class="successmsg">';
        $report .= sprintf('<i class="icon16 yes"></i>%s ', _wp('Exported'));
        
        $report .= htmlentities(_wp("%d product", "%d products", $this->data["processed_count"]), ENT_QUOTES, "utf-8");
        
        if (!empty($this->data['timestamp'])) {
            $interval = time() - $this->data['timestamp'];
            $interval = sprintf(_wp('%02d hr %02d min %02d sec'), floor($interval / 3600), floor($interval / 60) % 60, $interval % 60);
            $report .= ' '.sprintf(_wp('(total time: %s)'), $interval);
        }
        $report .= '</div>';

        return $report;
    }

}
