<?php
/**
 * @copyright  Serge Rodovnichenko <serge@syrnik.com>
 * @license http://www.webasyst.com/terms/#eula Webasyst Commercial
 */

declare(strict_types=1);

/**
 * Description of shopSyrrssPluginBackendSetup
 * @ControllerAction backend/setup
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

    /** @var shopSyrrssPlugin */
    protected $plugin;

    /**
     * @param $params
     * @throws waException
     */
    public function __construct($params = null)
    {
        parent::__construct($params);
        $this->Profile = new shopImportexportHelper(shopSyrrssPlugin::PLUGIN_ID);
        $this->Routing = waSystem::getInstance()->getRouting();
        $this->ShopConfig = waSystem::getInstance("shop")->getConfig();
        $this->AppSettings = new waAppSettingsModel();
    }

    protected function preExecute()
    {
        parent::preExecute();
        $this->plugin = wa()->getPlugin(shopSyrrssPlugin::PLUGIN_ID);
    }

    /**
     * @return void
     * @throws waException
     */
    public function execute()
    {
        $settlements = $this->getSettlements();
        $profile = $this->getProfile();
        $profiles = $this->Profile->getList();
        $current_domain = $profile["config"]["domain"];
        $profile["config"]["domain"] = $this->setRoutes($current_domain);
        $info = $this->getXmlFileInfo($profile);
        $app_settings = [
            'ignore_stock_count' => $this->AppSettings->get("shop", "ignore_stock_count", 0)
        ];

        $this->view->assign('primary_currency', $this->ShopConfig->getCurrency());
        $this->view->assign(compact("app_settings", "current_domain", "info", "profile", "profiles", "settlements"));
    }

    /**
     * Возвращает массив поселений
     *
     * @return array
     */
    private function getSettlements(): array
    {
        $settlements = array();
        $domain_routes = $this->Routing->getByApp("shop");

        foreach ($domain_routes as $domain => $routes) {
            foreach ($routes as $route) {
                $settlements[] = $domain . '/' . $route['url'];
            }
        }

        return $settlements;
    }

    /**
     *
     * @return array
     * @throws waException
     */
    private function getProfile(): array
    {
        $profile = $this->Profile->getConfig();
        $profile["config"] += array(
            "domain"              => "",
            "export_zero_stock"   => 0,
            "hash"                => "",
            "lifetime"            => 0,
            "max_products"        => 15,
            "channel_description" => _wp("New products"),
            "image_size"          => "210x0",
            "use_https"           => "1"
        );

        if (!($profile["config"]["channel_name"] ?? null)) {
            $profile["config"]["channel_name"] = sprintf(_wp("Newest products in %s store"), $this->ShopConfig->getGeneralSettings("name"));
        }

        return $profile;
    }

    /**
     * @param string $current_domain
     * @return string
     */
    private function setRoutes(string $current_domain = ""): string
    {
        $domain_routes = $this->Routing->getByApp("shop");

        foreach ($domain_routes as $domain => $routes) {
            foreach ($routes as $route) {
                $settlement = $domain . '/' . $route['url'];

                if (($settlement == $current_domain) || ($current_domain === '')) {
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
     * @param array $profile Массив с профилем
     * @return array Информация о файле с фидом
     * @throws waException
     */
    private function getXmlFileInfo(array $profile): array
    {
        $info = array('mtime' => null, 'exists' => null, "url" => null);

        if (!empty($profile["id"])) {
            $feed_file = shopSyrrssPlugin::path("{$profile["id"]}.xml");
            $info["exists"] = file_exists($feed_file);

            if ($info["exists"]) {
                $info["mtime"] = filemtime($feed_file);
                $info["url"] = $this->Routing->getUrl(
                    "shop/frontend/feed",
                    [
                        "plugin" => shopSyrrssPlugin::PLUGIN_ID,
                        "hash"   => $this->plugin->getHash((int)$profile["id"])
                    ], true);
            }
        }

        return $info;
    }
}
