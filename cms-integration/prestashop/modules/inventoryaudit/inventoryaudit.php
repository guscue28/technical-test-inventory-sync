<?php
/**
 * Inventory Audit Panel - PrestaShop Module
 * Critical Inventory Synchronization Module
 *
 * @author Your Name
 * @copyright 2024
 * @license GPL v2 or later
 */

if (!defined('_PS_VERSION_')) {
    exit;
}

class InventoryAudit extends Module
{
    /**
     * Module configuration
     */
    private $config = array();

    /**
     * Constructor
     */
    public function __construct()
    {
        $this->name = 'inventoryaudit';
        $this->tab = 'administration';
        $this->version = '1.0.0';
        $this->author = 'Your Name';
        $this->need_instance = 0;
        $this->ps_versions_compliancy = array('min' => '1.7', 'max' => _PS_VERSION_);
        $this->bootstrap = true;

        parent::__construct();

        $this->displayName = $this->l('Inventory Audit Panel');
        $this->description = $this->l('Critical Inventory Synchronization Module for PrestaShop');
        $this->confirmUninstall = $this->l('Are you sure you want to uninstall the Inventory Audit Panel?');

        // Load configuration
        $this->loadConfiguration();
    }

    /**
     * Install module
     */
    public function install()
    {
        if (Shop::isFeatureActive()) {
            Shop::setContext(Shop::CONTEXT_ALL);
        }

        return parent::install() &&
            $this->installTab() &&
            $this->registerHook('displayAdminProductsExtra') &&
            $this->registerHook('actionProductUpdate') &&
            $this->registerHook('displayBackOfficeHeader') &&
            $this->createConfiguration();
    }

    /**
     * Uninstall module
     */
    public function uninstall()
    {
        return parent::uninstall() &&
            $this->uninstallTab() &&
            $this->deleteConfiguration();
    }

    /**
     * Install admin tab
     */
    public function installTab()
    {
        $tab = new Tab();
        $tab->active = 1;
        $tab->class_name = 'AdminInventoryAudit';
        $tab->name = array();
        foreach (Language::getLanguages(true) as $lang) {
            $tab->name[$lang['id_lang']] = 'Inventory Audit';
        }
        $tab->id_parent = (int)Tab::getIdFromClassName('AdminCatalog');
        $tab->module = $this->name;
        return $tab->add();
    }

    /**
     * Uninstall admin tab
     */
    public function uninstallTab()
    {
        $idTab = (int)Tab::getIdFromClassName('AdminInventoryAudit');
        if ($idTab) {
            $tab = new Tab($idTab);
            return $tab->delete();
        }
        return false;
    }

    /**
     * Create module configuration
     */
    public function createConfiguration()
    {
        Configuration::updateValue('INVENTORY_AUDIT_API_URL', 'http://localhost:8000/api');
        Configuration::updateValue('INVENTORY_AUDIT_ITEMS_PER_PAGE', 50);
        Configuration::updateValue('INVENTORY_AUDIT_AUTO_REFRESH', 1);
        Configuration::updateValue('INVENTORY_AUDIT_REFRESH_INTERVAL', 300000);
        Configuration::updateValue('INVENTORY_AUDIT_ENABLED', 1);

        return true;
    }

    /**
     * Delete module configuration
     */
    public function deleteConfiguration()
    {
        Configuration::deleteByName('INVENTORY_AUDIT_API_URL');
        Configuration::deleteByName('INVENTORY_AUDIT_ITEMS_PER_PAGE');
        Configuration::deleteByName('INVENTORY_AUDIT_AUTO_REFRESH');
        Configuration::deleteByName('INVENTORY_AUDIT_REFRESH_INTERVAL');
        Configuration::deleteByName('INVENTORY_AUDIT_ENABLED');

        return true;
    }

    /**
     * Load configuration
     */
    private function loadConfiguration()
    {
        $this->config = array(
            'api_url' => Configuration::get('INVENTORY_AUDIT_API_URL'),
            'items_per_page' => (int)Configuration::get('INVENTORY_AUDIT_ITEMS_PER_PAGE'),
            'auto_refresh' => (bool)Configuration::get('INVENTORY_AUDIT_AUTO_REFRESH'),
            'refresh_interval' => (int)Configuration::get('INVENTORY_AUDIT_REFRESH_INTERVAL'),
            'enabled' => (bool)Configuration::get('INVENTORY_AUDIT_ENABLED')
        );
    }

    /**
     * Configuration form
     */
    public function getContent()
    {
        $output = null;

        if (Tools::isSubmit('submit' . $this->name)) {
            $apiUrl = strval(Tools::getValue('INVENTORY_AUDIT_API_URL'));
            $itemsPerPage = (int)Tools::getValue('INVENTORY_AUDIT_ITEMS_PER_PAGE');
            $autoRefresh = (bool)Tools::getValue('INVENTORY_AUDIT_AUTO_REFRESH');
            $refreshInterval = (int)Tools::getValue('INVENTORY_AUDIT_REFRESH_INTERVAL');
            $enabled = (bool)Tools::getValue('INVENTORY_AUDIT_ENABLED');

            if (!$apiUrl || empty($apiUrl) || !Validate::isUrl($apiUrl)) {
                $output .= $this->displayError($this->l('Invalid API URL'));
            } elseif ($itemsPerPage < 1 || $itemsPerPage > 200) {
                $output .= $this->displayError($this->l('Items per page must be between 1 and 200'));
            } else {
                Configuration::updateValue('INVENTORY_AUDIT_API_URL', $apiUrl);
                Configuration::updateValue('INVENTORY_AUDIT_ITEMS_PER_PAGE', $itemsPerPage);
                Configuration::updateValue('INVENTORY_AUDIT_AUTO_REFRESH', $autoRefresh);
                Configuration::updateValue('INVENTORY_AUDIT_REFRESH_INTERVAL', $refreshInterval);
                Configuration::updateValue('INVENTORY_AUDIT_ENABLED', $enabled);

                $this->loadConfiguration();
                $output .= $this->displayConfirmation($this->l('Settings updated'));
            }
        }

        return $output . $this->displayForm();
    }

    /**
     * Display configuration form
     */
    public function displayForm()
    {
        // Get default language
        $defaultLang = (int)Configuration::get('PS_LANG_DEFAULT');

        // Init Fields form array
        $fieldsForm[0]['form'] = array(
            'legend' => array(
                'title' => $this->l('Settings'),
            ),
            'input' => array(
                array(
                    'type' => 'text',
                    'label' => $this->l('API Base URL'),
                    'name' => 'INVENTORY_AUDIT_API_URL',
                    'size' => 50,
                    'required' => true,
                    'desc' => $this->l('Base URL for the Inventory API (e.g., http://localhost:8000/api)')
                ),
                array(
                    'type' => 'text',
                    'label' => $this->l('Items Per Page'),
                    'name' => 'INVENTORY_AUDIT_ITEMS_PER_PAGE',
                    'size' => 10,
                    'required' => true,
                    'desc' => $this->l('Number of items to display per page (1-200)')
                ),
                array(
                    'type' => 'switch',
                    'label' => $this->l('Auto Refresh'),
                    'name' => 'INVENTORY_AUDIT_AUTO_REFRESH',
                    'is_bool' => true,
                    'desc' => $this->l('Enable automatic data refresh'),
                    'values' => array(
                        array(
                            'id' => 'active_on',
                            'value' => true,
                            'label' => $this->l('Enabled')
                        ),
                        array(
                            'id' => 'active_off',
                            'value' => false,
                            'label' => $this->l('Disabled')
                        )
                    ),
                ),
                array(
                    'type' => 'text',
                    'label' => $this->l('Refresh Interval (ms)'),
                    'name' => 'INVENTORY_AUDIT_REFRESH_INTERVAL',
                    'size' => 10,
                    'desc' => $this->l('Auto refresh interval in milliseconds (e.g., 300000 for 5 minutes)')
                ),
                array(
                    'type' => 'switch',
                    'label' => $this->l('Enable Module'),
                    'name' => 'INVENTORY_AUDIT_ENABLED',
                    'is_bool' => true,
                    'desc' => $this->l('Enable or disable the entire module'),
                    'values' => array(
                        array(
                            'id' => 'active_on',
                            'value' => true,
                            'label' => $this->l('Enabled')
                        ),
                        array(
                            'id' => 'active_off',
                            'value' => false,
                            'label' => $this->l('Disabled')
                        )
                    ),
                ),
            ),
            'submit' => array(
                'title' => $this->l('Save'),
                'class' => 'btn btn-default pull-right'
            )
        );

        $helper = new HelperForm();

        // Module, token and currentIndex
        $helper->module = $this;
        $helper->name_controller = $this->name;
        $helper->token = Tools::getAdminTokenLite('AdminModules');
        $helper->currentIndex = AdminController::$currentIndex . '&configure=' . $this->name;

        // Language
        $helper->default_form_language = $defaultLang;
        $helper->allow_employee_form_lang = $defaultLang;

        // Title and toolbar
        $helper->title = $this->displayName;
        $helper->show_toolbar = true;
        $helper->toolbar_scroll = true;
        $helper->submit_action = 'submit' . $this->name;
        $helper->toolbar_btn = array(
            'save' => array(
                'desc' => $this->l('Save'),
                'href' => AdminController::$currentIndex . '&configure=' . $this->name . '&save' . $this->name .
                    '&token=' . Tools::getAdminTokenLite('AdminModules'),
            ),
            'back' => array(
                'href' => AdminController::$currentIndex . '&token=' . Tools::getAdminTokenLite('AdminModules'),
                'desc' => $this->l('Back to list')
            )
        );

        // Load current values
        $helper->fields_value['INVENTORY_AUDIT_API_URL'] = $this->config['api_url'];
        $helper->fields_value['INVENTORY_AUDIT_ITEMS_PER_PAGE'] = $this->config['items_per_page'];
        $helper->fields_value['INVENTORY_AUDIT_AUTO_REFRESH'] = $this->config['auto_refresh'];
        $helper->fields_value['INVENTORY_AUDIT_REFRESH_INTERVAL'] = $this->config['refresh_interval'];
        $helper->fields_value['INVENTORY_AUDIT_ENABLED'] = $this->config['enabled'];

        return $helper->generateForm($fieldsForm);
    }

    /**
     * Hook: Display in back office header
     */
    public function hookDisplayBackOfficeHeader()
    {
        if (!$this->config['enabled']) {
            return;
        }

        // Add CSS and JS files
        $this->context->controller->addCSS($this->_path . 'views/css/back.css');
        $this->context->controller->addJS($this->_path . 'views/js/back.js');

        // Add module configuration to JavaScript
        $jsConfig = array(
            'apiUrl' => $this->config['api_url'],
            'itemsPerPage' => $this->config['items_per_page'],
            'autoRefresh' => $this->config['auto_refresh'],
            'refreshInterval' => $this->config['refresh_interval'],
            'ajaxUrl' => $this->context->link->getAdminLink('AdminInventoryAudit'),
            'token' => Tools::getAdminTokenLite('AdminInventoryAudit')
        );

        return '<script type="text/javascript">
            var inventoryAuditConfig = ' . json_encode($jsConfig) . ';
        </script>';
    }

    /**
     * Hook: Display in product admin extra
     */
    public function hookDisplayAdminProductsExtra($params)
    {
        if (!$this->config['enabled']) {
            return;
        }

        $productId = (int)$params['id_product'];

        $this->context->smarty->assign(array(
            'product_id' => $productId,
            'api_url' => $this->config['api_url'],
            'module_dir' => $this->_path
        ));

        return $this->display(__FILE__, 'product-logs.tpl');
    }

    /**
     * Hook: Product update action
     */
    public function hookActionProductUpdate($params)
    {
        if (!$this->config['enabled']) {
            return;
        }

        $product = $params['product'];
        $productId = (int)$product->id;

        // Send stock update to API if stock changed
        // This would be implemented to synchronize with the external API
        $this->syncProductStock($productId);
    }

    /**
     * Synchronize product stock with external API
     */
    private function syncProductStock($productId)
    {
        try {
            $product = new Product($productId);

            if (!Validate::isLoadedObject($product)) {
                return false;
            }

            $stockQuantity = (int)StockAvailable::getQuantityAvailableByProduct($productId);

            // Prepare API data
            $apiData = array(
                'stock' => $stockQuantity,
                'user_source' => 'prestashop_sync'
            );

            // Make API call
            $response = $this->makeApiCall("products/{$productId}/stock", 'PATCH', $apiData);

            if ($response && isset($response['success']) && $response['success']) {
                PrestaShopLogger::addLog(
                    'Inventory Audit: Stock synchronized for product ' . $productId,
                    1, // Info level
                    null,
                    'Product',
                    $productId
                );
                return true;
            }

            return false;

        } catch (Exception $e) {
            PrestaShopLogger::addLog(
                'Inventory Audit Error: ' . $e->getMessage(),
                3, // Error level
                null,
                'Product',
                $productId
            );
            return false;
        }
    }

    /**
     * Make API call to external service
     */
    private function makeApiCall($endpoint, $method = 'GET', $data = null)
    {
        if (empty($this->config['api_url'])) {
            return false;
        }

        $url = rtrim($this->config['api_url'], '/') . '/' . ltrim($endpoint, '/');

        $ch = curl_init();

        curl_setopt_array($ch, array(
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_HTTPHEADER => array(
                'Content-Type: application/json',
                'X-Requested-With: XMLHttpRequest'
            ),
            CURLOPT_CUSTOMREQUEST => $method
        ));

        if ($method !== 'GET' && $data !== null) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        }

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);

        curl_close($ch);

        if ($error) {
            PrestaShopLogger::addLog(
                'Inventory Audit cURL Error: ' . $error,
                3
            );
            return false;
        }

        if ($httpCode >= 400) {
            PrestaShopLogger::addLog(
                'Inventory Audit API Error: HTTP ' . $httpCode,
                3
            );
            return false;
        }

        return json_decode($response, true);
    }

    /**
     * Get module configuration
     */
    public function getConfig()
    {
        return $this->config;
    }

    /**
     * Test API connection
     */
    public function testApiConnection()
    {
        return $this->makeApiCall('health');
    }
}
