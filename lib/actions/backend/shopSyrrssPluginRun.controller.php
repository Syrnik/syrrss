<?php
/**
 * @copyright Serge Rodovnichenko <serge@syrnik.com>
 * @license http://www.webasyst.com/terms/#eula Webasyst Commercial
 * @noinspection PhpComposerExtensionStubsInspection, HttpUrlsUsage, DuplicatedCode
 */
declare(strict_types=1);

/**
 * RSS feed creation
 *
 * @package webasyst.shop.plugin.syrrss.controller
 */
class shopSyrrssPluginRunController extends waLongActionController
{

    /** @var DOMDocument */
    private $rss;

    /** @var shopProductsCollection */
    private $collection;

    /**
     * @return void
     */
    protected function preExecute()
    {
        parent::preExecute();
        $this->getResponse()->addHeader('Content-type', 'application/json')->sendHeaders();
    }

    /**
     * @return void
     * @throws waException|DOMException
     */
    protected function init()
    {
        $Profile = new shopImportexportHelper(shopSyrrssPlugin::PLUGIN_ID);

        /** @var shopConfig $Config */
        $Config = wa('shop')->getConfig();
        $AppSettings = new waAppSettingsModel();

        /** @var shopSyrrssPlugin $Plugin */
        $Plugin = waSystem::getInstance()->getPlugin(shopSyrrssPlugin::PLUGIN_ID);

        try {

            if (waSystem::getInstance()->getEnv() == 'backend') {
                $profile_config = $this->getProfileOptionsFromRequest();
                $profile_id = $Profile->setConfig($profile_config);
                $Plugin->getHash($profile_id);
            } else {

                $profile = $this->getProfile();
                $profile_id = $profile['id'];
                $profile_config = $profile['config'];
            }

            $this->data = array_merge($this->data, array(
                'domain'             => $profile_config['domain'],
                'export_unavailable' => $profile_config["export_zero_stock"],
                'hash'               => $profile_config['hash'],
                'memory'             => memory_get_peak_usage(),
                'memory_avg'         => memory_get_usage(),
                'offset'             => array('offers' => 0),
                'path'               => array('offers' => shopSyrrssPlugin::path($profile_id . ".xml")),
                'primary_currency'   => $Config->getCurrency(),
                'processed_count'    => 0,
                'timestamp'          => time(),
                'total_written'      => 0,
                'utm'                => "",
                'image_size'         => $profile_config['image_size'] ?? "210x0",
                'use_https'          => boolval($profile_config['use_https'] ?? true),
                'images_counter'     => [
                    'type'  => $profile_config['images_count_type'],
                    'value' => intval(max(1, (int)$profile_config['images_count_value']))
                ]
            ));

            $this->data['schema'] = $this->data['use_https'] ? 'https://' : 'http://';

            if ($AppSettings->get('shop', "ignore_stock_count", 0)) $this->data["export_unavailable"] = 1;

            if (isset($profile_config["utm"]["source"]) && isset($profile_config["utm"]["medium"]) && isset($profile_config["utm"]["campaign"])) {
                foreach (array("source", "medium", "campaign") as $param) {
                    $profile_config["utm"][$param] = trim($profile_config["utm"][$param]);
                    if (empty($profile_config["utm"][$param])) {
                        unset($profile_config["utm"][$param]);
                    }
                }
                if (!empty($profile_config["utm"]))
                    $this->data["utm"] = http_build_query(array_map('rawurlencode', $profile_config["utm"]));
            }

            $this->data["count"] = $this->getCollection()->count();
            $this->data["max_products"] = intval($profile_config["max_products"]) > 0 ? intval($profile_config["max_products"]) : $this->data["count"];

            $this->initRouting();

            $route = wa()->getRouteUrl('shop/frontend', array(), true);
            $route = preg_replace('|^https?://|', $this->data['schema'], $route);

            $this->rss = $this->initRss(
                $profile_config['channel_name'],
                $route,
                $profile_config["channel_description"],
                "SyrRSS plugin for Shop-Script " . $Plugin->getVersion()
            );

        } catch (waException $e) {
            echo json_encode(array('error' => $e->getMessage()));
        }
    }

    /**
     * @return bool
     * @throws waException|DOMException
     */
    protected function step(): bool
    {
        static $product_collection;

        if (!$product_collection) {
            $product_collection = $this->getCollection()->getProducts('*,images,summary,id,name,price,currency,create_datetime,frontend_url', $this->data["processed_count"], 200, false);
            if (!$product_collection) {
                $this->data["processed_count"] = $this->data["count"];
            }
        }

        $step = 0;
        $product = array_shift($product_collection);

        while (($step < 50) && $product && $this->data["max_products"] > $this->data["total_written"]) {

            if (($product["price"] > 0) && ($this->data["export_unavailable"] || ($product["count"] === null) || ($product["count"] > 0))) {
                $this->addItem($product);
                $this->data["total_written"]++;
            }

            $this->data['processed_count']++;
            $step++;
            $product = array_shift($product_collection);
        }

        return true;
    }

    /**
     * @param $filename
     * @return bool
     * @throws waException
     */
    protected function finish($filename): bool
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
//            $this->error($ex->getMessage());
        }

        $this->info();

        return $result;
    }

    /**
     * @return boolean
     * @see waLongActionController::isDone()
     */
    protected function isDone(): bool
    {

        if (($this->data["processed_count"] < $this->data["count"]) && ($this->data["total_written"] < $this->data["max_products"]))
            return false;

        return true;
    }

    /**
     * @return void
     * @throws waException
     */
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

        if ($this->isDone()) {
            $response["report"] = $this->report(); // . $this->validateReport();
        }

        echo json_encode($response);
    }

    /**
     * Данные из формы
     *
     * @return array
     */
    private function getProfileOptionsFromRequest(): array
    {
        $hash = shopImportexportHelper::getCollectionHash();

        return array_merge(array('export_zero_stock' => 0), waRequest::post('config'), array('hash' => $hash["hash"]));
    }

    /**
     * @return void
     * @throws waException
     */
    protected function restore()
    {
        $this->loadRss();
        $this->initRouting();
        $this->collection = null;
    }

    /**
     * @return void
     * @throws waException
     */
    protected function save()
    {
        if ($this->rss) $this->rss->save($this->getTempPath());
    }

    /**
     * @return array
     * @throws waException
     */
    private function getProfile(): array
    {
        $Profile = new shopImportexportHelper(shopSyrrssPlugin::PLUGIN_ID);

        $profile_id = waRequest::param('profile_id');

        if (!$profile_id) {
            throw new waException("Invalid profile", 404);
        }

        $profile = $Profile->getConfig($profile_id);
        if (!$profile) {
            throw new waException("Invalid profile", 404);
        }

        return $profile;
    }

    /**
     * @return void
     * @throws waException
     */
    private function initRouting()
    {
        $routing = wa()->getRouting();
        $app_id = $this->getAppId();
        $domain_routes = $routing->getByApp($app_id);
        $success = false;
        foreach ($domain_routes as $domain => $routes) {
            foreach ($routes as $route) {
                if ($domain . '/' . $route['url'] == $this->data['domain']) {
                    $routing->setRoute($route, $domain);
                    waRequest::setParam($route);
                    $this->data['base_url'] = parse_url(preg_replace('|https?://|', $this->data['schema'], $domain), PHP_URL_HOST);
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
     * @return shopProductsCollection
     * @throws waException
     * @internal param string $hash
     */
    private function getCollection(): shopProductsCollection
    {
        if (!$this->collection) {
            $options = array(
                'frontend' => true,
                'params'   => array(
                    'sort'  => 'create_datetime',
                    'order' => 'desc'
                )
            );

            $hash = $this->data['hash'];
            if ($hash == '*') {
                $hash = '';
            }

            // Чтобы отключить настройку сортировки отсутствующих и недоступных товаров
            // Почему это через параметр запроса-то???!!!
            // Неужели нельзя в $options передавать?
            waRequest::setParam('drop_out_of_stock', 0);

            $this->collection = new shopProductsCollection($hash, $options);
        }
        return $this->collection;
    }

    /**
     * @param string $title_str
     * @param string $link_str
     * @param string $description_str
     * @param string $generator_str
     * @param array $options
     * @return DOMDocument
     * @throws DOMException
     * @noinspection PhpSameParameterValueInspection
     */
    private function initRss(string $title_str, string $link_str, string $description_str, string $generator_str, array $options = []): DOMDocument
    {
        $options = array_merge(['yaturbo' => false], $options);

        $dom = new DOMDocument('1.0', 'UTF-8');
        $rss = $dom->createElement('rss');
        $dom->appendChild($rss);
        $rss->setAttribute('version', '2.0');

        $channel = $dom->createElement('channel');
        $rss->appendChild($channel);

        $title = $dom->createElement('title');
        $title->appendChild(new DOMText($title_str));
        $channel->appendChild($title);

        $link = $dom->createElement('link');
        $link->appendChild(new DOMText($link_str));
        $channel->appendChild($link);

        $description = $dom->createElement('description');
        $description->appendChild(new DOMText($description_str));
        $channel->appendChild($description);

        $generator = $dom->createElement('generator');
        $generator->appendChild(new DOMText($generator_str));
        $channel->appendChild($generator);

        return $dom;
    }

    /**
     * @return string
     * @throws waException
     */
    private function getTempPath(): string
    {
        $file = $this->processId . '.xml';
        return waSystem::getInstance()->getTempPath('plugins/syrrss/', 'shop') . $file;
    }

    /**
     *
     * @param string|null $path
     * @throws waException
     */
    private function loadRss(string $path = null)
    {
        if (!$path) $path = $this->getTempPath();

        if (!$this->rss) {
            $this->rss = new DOMDocument();
            if (!$this->rss->load($path)) {
                waLog::log('Error loading ' . $path);
                throw new waException("Error while read saved XML");
            }
        }
    }

    /**
     * Добавляет item в channel
     *
     * @param array $product
     * @throws DOMException|waException
     * @throws Exception
     */
    private function addItem(array $product)
    {
        $size = $this->data['image_size'];
        $image_tag = "";
        $create_date = new DateTime($product["create_datetime"]);

        $channel = $this->rss->getElementsByTagName('channel')->item(0);
        $item = $this->rss->createElement('item');

        $item->appendChild($this->rss->createElement('title', strip_tags($product['name'])));
        $item->appendChild($this->rss->createElement('link', $this->productUrl($product)));
        $item->appendChild($this->rss->createElement('pubDate', $create_date->format('r')));

        if (('none' !== $this->data['images_counter']['type']) && ($images = $product['images'] ?? null) && is_array($images)) {
            $img_cnt = 0;
            foreach ($images as $image) {
                if ($image) {
                    $image_tag .= '<img src="' .
                        $this->data['schema'] .
                        ($this->data['base_url'] ?: 'localhost') .
                        shopImage::getUrl($image, $size) .
                        '" alt="' .
                        htmlentities($product["name"], ENT_QUOTES, 'UTF-8') .
                        '">';

                    if (('max' === $this->data['images_counter']['type']) && (++$img_cnt >= $this->data['images_counter']['value']))
                        break;
                }
            }
        }

        // No need to add empty description tag if there's no images nor summary
        if (!empty($image_tag) || !empty($product["summary"])) {
            $el_description = $this->rss->createElement('description');

            $description = "$image_tag<p>" .
                htmlentities(strip_tags($product["summary"]), ENT_QUOTES, 'UTF-8') .
                '</p>' .
                $this->getItemPrice($product);

            $el_description->appendChild($this->rss->createCDATASection($description));
            $item->appendChild($el_description);
        }
        $channel->appendChild($item);
    }

    /**
     *
     * @param array $product
     * @return string
     */
    private function productUrl(array $product): string
    {
        $url = preg_replace_callback('@([^\w_/-?%&]+)@i', function ($a) {
            return rawurlencode(reset($a));
        }, $product['frontend_url']);

        if ($this->data['utm']) {
            $url .= (strpos($url, '?') ? '&' : '?') . $this->data['utm'];
        }

        return $this->data['schema'] . ($this->data['base_url'] ?: 'localhost') . $url;
    }

    /**
     * @return void
     * @throws waException
     */
    private function validate()
    {
        $libxml_internal_errors = libxml_use_internal_errors(true);
        $this->loadRss($this->data['path']['offers']);

        if (!$this->rss) {

            $this->data["error"] = array();
            $err = array();

            foreach (libxml_get_errors() as $error) {
                $this->data["error"][] = array(
                    "level"   => "error",
                    "message" => "#$error->code [$error->line:$error->column] $error->message"
                );
                $err[] = "#$error->code [$error->line:$error->column] $error->message";
            }

//            $this->error(implode("\n\t", $err));
            libxml_clear_errors();
        }

        libxml_use_internal_errors($libxml_internal_errors);
    }

    /**
     * @return string
     * @throws waException
     */
    protected function report(): string
    {
        $report = '<div class="successmsg">';
        $report .= sprintf('<i class="icon16 yes"></i>%s ', _wp('Exported'));

        $report .= htmlentities(_wp("%d product", "%d products", $this->data["total_written"]), ENT_QUOTES, "utf-8");

        if (!empty($this->data['timestamp'])) {
            $interval = time() - $this->data['timestamp'];
            $interval = sprintf(_wp('%02d ч. %02d мин. %02d сек.'), floor($interval / 3600), floor($interval / 60) % 60, $interval % 60);
            $report .= ' ' . sprintf(_wp('(время работы: %s)'), $interval);
        }
        $report .= '</div>';

        return $report;
    }

    /**
     * @param array $product
     * @return string
     * @throws waException
     */
    private function getItemPrice(array $product): string
    {

        if (isset($product["price"]) && isset($product["currency"])) {
            return "<p>" .
                _wp("Price:") .
                " " .
                waCurrency::format("%{s}", $product["price"], $this->data["primary_currency"]) .
                "</p>";
        }

        return "";
    }

}
