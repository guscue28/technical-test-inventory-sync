<?php
/**
 * AdminInventoryAuditController
 */

require_once _PS_MODULE_DIR_ . 'inventoryaudit/inventoryaudit.php';

class AdminInventoryAuditController extends ModuleAdminController
{
    public function __construct()
    {
        $this->bootstrap = true;
        $this->context = Context::getContext();

        parent::__construct();

        $this->toolbar_title = $this->l('Inventory Audit Panel');
    }

    public function initContent()
    {
        parent::initContent();

        // Verificar que el módulo esté activo
        if (!Module::isEnabled('inventoryaudit')) {
            $this->errors[] = $this->l('The Inventory Audit module is not enabled.');
            return;
        }

        // Obtener configuración del módulo
        $module = Module::getInstanceByName('inventoryaudit');
        $config = $module->getConfig();

        // Asignar variables para la vista
        $this->context->smarty->assign([
            'module_dir' => _MODULE_DIR_ . 'inventoryaudit/',
            'api_url' => $config['api_url'],
            'items_per_page' => $config['items_per_page'],
            'auto_refresh' => $config['auto_refresh'],
            'refresh_interval' => $config['refresh_interval'],
            'token' => $this->token,
            'ajax_url' => $this->context->link->getAdminLink('AdminInventoryAudit')
        ]);

        // Cargar los assets CSS y JS
        $this->addCSS(_MODULE_DIR_ . 'inventoryaudit/views/css/styles.css');
        $this->addCSS(_MODULE_DIR_ . 'inventoryaudit/views/css/responsive.css');
        $this->addCSS(_MODULE_DIR_ . 'inventoryaudit/views/css/prestashop-fixes.css');

        // jQuery ya viene con PrestaShop, no es necesario cargarlo
        $this->addJS(_MODULE_DIR_ . 'inventoryaudit/views/js/config.js');
        $this->addJS(_MODULE_DIR_ . 'inventoryaudit/views/js/api.js');
        $this->addJS(_MODULE_DIR_ . 'inventoryaudit/views/js/app.js');

        // Renderizar el contenido directamente en lugar de usar setTemplate
        $this->content = $this->context->smarty->fetch(_PS_MODULE_DIR_ . 'inventoryaudit/views/templates/admin/panel.tpl');

        $this->context->smarty->assign([
            'content' => $this->content,
        ]);
    }

    /**
     * Ajax process for API proxy
     */
    public function ajaxProcessGetInventoryData()
    {
        $module = Module::getInstanceByName('inventoryaudit');
        $endpoint = Tools::getValue('endpoint');
        $method = Tools::getValue('method', 'GET');
        $data = Tools::getValue('data', []);

        // Hacer llamada a la API
        $response = $this->callApi($module->getConfig()['api_url'], $endpoint, $method, $data);

        die(json_encode($response));
    }

    /**
     * Call external API
     */
    private function callApi($baseUrl, $endpoint, $method = 'GET', $data = [])
    {
        $url = rtrim($baseUrl, '/') . '/' . ltrim($endpoint, '/');

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Accept: application/json'
        ]);

        if ($method !== 'GET' && !empty($data)) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        }

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode >= 200 && $httpCode < 300) {
            return json_decode($response, true);
        }

        return [
            'success' => false,
            'error' => 'API call failed with code: ' . $httpCode
        ];
    }
}
