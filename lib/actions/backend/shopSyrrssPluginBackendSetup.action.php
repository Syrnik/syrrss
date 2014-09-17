<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

/**
 * Description of shopSyrrssPluginBackendSetup
 *
 * @author serge
 */
class shopSyrrssPluginBackendSetupAction extends waViewAction
{

    /** @var shopImportexportHelper Profile helper */
    private $Profile;

    /** @var waRouting */
    private $Routing;
    
    /** @var shopConfig */
    private $ShopConfig;
    
    /** @var waAppSettingsModel */
    private $AppSettings;

    public function __construct($params = null)
    {
        parent::__construct($params);
        $this->Profile = new shopImportexportHelper(shopSyrrssPlugin::PLUGIN_ID);
        $this->Routing = waSystem::getInstance()->getRouting();
        $this->ShopConfig = waSystem::getInstance("shop")->getConfig();
        $this->AppSettings = new waAppSettingsModel();
    }

    public function execute()
    {
        $settlements = $this->getSettlements();
        $profile = $this->getProfile();
        $profiles = $this->Profile->getList();
        $current_domain = $profile["config"]["domain"];
        $profile["config"]["domain"] = $this->setRoutes($current_domain);
        $info = $this->getXmlFileInfo($profile);
        $app_settings = array(
            'ignore_stock_count' => $this->AppSettings->get("shop", "ignore_stock_count", 0)
        );
        
        $this->view->assign('primary_currency', $this->ShopConfig->getCurrency());
        $this->view->assign(compact("app_settings", "current_domain", "info", "profile", "profiles", "settlements"));
    }

    /**
     * Возвращает массив поселений
     *
     * @return array
     */
    private function getSettlements()
    {

        $settlements = array();
        $domain_routes = $this->Routing->getByApp("shop");

        foreach($domain_routes as $domain => $routes) {
            foreach($routes as $route) {
                $settlements[] = $domain . '/' . $route['url'];
            }
        }

        return $settlements;
    }

    /**
     *
     * @return array
     */
    private function getProfile()
    {
        $profile = $this->Profile->getConfig();
        $profile["config"] += array(
            "domain" => "",
            "export_zero_stock" => 0,
            "hash" => "",
            "lifetime" => 0,
            "max_products" => 15,
            "channel_description" => _wp("New products")
        );
        
        if(!isset($profile["config"]["channel_name"]) || empty($profile["config"]["channel_name"])) {
            $profile["config"]["channel_name"] = sprintf(_wp("Newest products in %s store"), $this->ShopConfig->getGeneralSettings("name"));
        }

        return $profile;
    }

    private function setRoutes($current_domain = "")
    {
        $domain_routes = $this->Routing->getByApp("shop");

        foreach($domain_routes as $domain => $routes) {
            foreach($routes as $route) {
                $settlement = $domain . '/' . $route['url'];

                if(($settlement == $current_domain) || ($current_domain === '')) {
                    $current_domain = $settlement;
                    $this->Routing->setRoute($route, $domain);
                    waRequest::setParam($route);
                }
            }
        }

        return $current_domain;
    }
    
    /**
     * 
     * @param array $profile массив с профилем
     * @return array Информация о файле с фидом
     */
    private function getXmlFileInfo($profile)
    {
        $info = array('mtime' => NULL, 'exists' => NULL, "url" => NULL);

        if(!empty($profile["id"])) {
            $feed_file = shopSyrrssPlugin::path("{$profile["id"]}.xml");
            $info["exists"] = file_exists($feed_file);

            if($info["exists"]) {
                $info["mtime"] = filemtime($feed_file);
                $info["url"] = $this->Routing->getUrl("shop/frontend/feed", array("plugin" => shopSyrrssPlugin::PLUGIN_ID, "hash" => $this->plugin()->getHash($profile["id"])), TRUE);
            }
        }

        return $info;
    }
    
    /**
     * Singletons suck!
     * 
     * @return shopSyrrssPlugin
     */
    private function plugin()
    {
        static $plugin;
        if(!$plugin) {
            $plugin = wa()->getPlugin(shopSyrrssPlugin::PLUGIN_ID);
        }
        return $plugin;
    }

}
