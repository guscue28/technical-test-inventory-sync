<!-- Agregar CSS de correcciones específico para PrestaShop -->
<link rel="stylesheet" href="{$module_dir}views/css/prestashop-fixes.css" type="text/css" media="all">

<div class="inventory-panel-wrapper prestashop-integration">
        <script type="text/javascript">
            // Configuración desde PrestaShop para el frontend
            window.InventoryConfig = {
                API: {
                    BASE_URL: 'http://127.0.0.1:8000/api',
                    ENDPOINTS: {
                        INVENTORY_LOGS: 'inventory-logs',
                        PRODUCTS: 'products',
                        STATISTICS: 'inventory-logs/statistics',
                        UPDATE_STOCK: 'products'
                    },
                    TIMEOUT: 30000
                },
                UI: {
                    ITEMS_PER_PAGE: {$items_per_page|default:10|intval},
                    DATE_FORMAT: 'YYYY-MM-DD',
                    TIME_FORMAT: 'HH:mm:ss',
                    DATETIME_FORMAT: 'YYYY-MM-DD HH:mm:ss',
                    AUTO_REFRESH_INTERVAL: {$refresh_interval|default:300000|intval}
                },
                FEATURES: {
                    AUTO_REFRESH: {if $auto_refresh|default:false}true{else}false{/if},
                    KEYBOARD_SHORTCUTS: true,
                    EXPORT_CSV: true,
                    REAL_TIME_UPDATES: false
                },
                FILTERS: {
                    PERSISTENT_FILTERS: true,
                    MAX_DATE_RANGE: 365
                },
                STORAGE_KEYS: {
                    FILTERS: 'inventory_filters',
                    PREFERENCES: 'inventory_preferences'
                },
                MESSAGES: {
                    ERRORS: {
                        NETWORK: 'Network connection error',
                        NO_DATA: 'No data available',
                        INVALID_DATA: 'Invalid data format'
                    }
                },
                DEBUG: {
                    LOG_API_CALLS: false,
                    SHOW_PERFORMANCE: false
                },
                PERFORMANCE: {
                    CACHE_DURATION: 300000,
                    DEBOUNCE_DELAY: 300
                }
            };
        </script>

    <!-- Panel Content -->
    <div id="inventory-audit-content">
        {include file="./inventory-panel.html"}
    </div>
</div>
