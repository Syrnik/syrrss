<?php
/**
 * @copyright  Serge Rodovnichenko <serge@syrnik.com>, 2014-2023
 * @license http://www.webasyst.com/terms/#eula Webasyst Commercial
 */

declare(strict_types=1);

/**
 * Description of shopSyrrssPluginBackendSetup
 * @ControllerAction backend/setup
 * @method shopConfig getConfig()
 */
class shopSyrrssPluginBackendSetupAction extends waViewAction
{

    /** @var shopSyrrssPlugin */
    protected $plugin;

    /** @var shopImportexportHelper Profile helper */
    private $Profile;

    /** @var waRouting */
    private $Routing;

    /** @var shopConfig */
    private $ShopConfig;

    /** @var waAppSettingsModel */
    private $AppSettings;

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

        $image_sizes_list = $this->getImageSizeList();

        $this->view->assign('primary_currency', $this->ShopConfig->getCurrency());
        $this->view->assign(compact("app_settings", "current_domain", "info", "profile", "profiles", "settlements", "image_sizes_list"));
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
            "channel_description" => _wp("Новые товары"),
            "image_size"          => "210x0",
            "use_https"           => "1",
            "images_count_type"   => "max", // max, all, none
            "images_count_value"  => "1"
        );

        if (!($profile["config"]["channel_name"] ?? null)) {
            $profile["config"]["channel_name"] = sprintf(_wp("Новые товары в магазине %s"), $this->ShopConfig->getGeneralSettings("name"));
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

    /**
     * Список размеров для отображения в шаблоне настроек
     *
     * @return array
     */
    protected function getImageSizeList(): array
    {
        $sizes = $this->getConfig()->getImageSizes() ?: [];

        $sizes = array_map(function ($size) {
            $item = ['value' => $size];
            $sides = explode('x', $size);
            $width = $sides[0];
            $height = $sides[1] ?? null;

            if (is_string($width)) $width = trim($width);
            if (is_string($height)) $height = trim($height);

            try {
                if (null === $height) {
                    $item['title'] = _wp('Макс. (Ширина, Высота)') . " = $width px";
                } elseif (!$width && $height) {
                    $item['title'] = _wp('Ширина = авто, Высота = ') . "$height px";
                } elseif ($width && !$height) {
                    $item['title'] = sprintf_wp('Ширина = %d px, Высота = авто', $width);
                } elseif ($width == $height) {
                    $item['title'] = sprintf_wp('Квадратная обрезка: %dx%d px', $width, $height);
                } else {
                    $item['title'] = sprintf_wp('Прямоугольная обрезка %dx%d px', $width, $height);
                }
            } catch (waException $e) {
                $item['title'] = $item['value'];
            }

            return $item;
        }, $sizes);

        array_unshift($sizes, ['value' => '210x0', 'title' => 'Рекомендуемый размер для RSS']);

        return $sizes;
    }

    /**
     * @return void
     * @throws waException
     */
    protected function preExecute()
    {
        parent::preExecute();
        $this->plugin = wa()->getPlugin(shopSyrrssPlugin::PLUGIN_ID);
    }
}
