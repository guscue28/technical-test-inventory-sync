<div class="panel">
    <div class="panel-heading">
        <i class="icon-bar-chart"></i> Inventory Audit Panel
    </div>

    <div class="panel-body">
        <script type="text/javascript">
            // Configuración desde PrestaShop
            window.InventoryConfig = {
                API: {
                    BASE_URL: 'http://localhost:8000/api',
                    ENDPOINTS: {
                        INVENTORY_LOGS: 'inventory-logs',
                        PRODUCTS: 'products',
                        STATISTICS: 'inventory-logs/statistics'
                    }
                },
                UI: {
                    ITEMS_PER_PAGE: 10
                },
                FEATURES: {
                    AUTO_REFRESH: false
                }
            };
        </script>

        <h2>🎯 Inventory Audit Panel - Test</h2>
        <p>Si ves este mensaje, el módulo de PrestaShop está funcionando correctamente.</p>

        <div class="alert alert-info">
            <strong>Estado:</strong> Módulo cargado exitosamente ✅
        </div>

        <div class="row">
            <div class="col-md-4">
                <div class="panel">
                    <div class="panel-heading">Products</div>
                    <div class="panel-body">
                        <button class="btn btn-primary" onclick="loadProducts()">Load Products</button>
                        <div id="products-list">Click the button to load products</div>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="panel">
                    <div class="panel-heading">Inventory Logs</div>
                    <div class="panel-body">
                        <button class="btn btn-primary" onclick="loadLogs()">Load Logs</button>
                        <div id="logs-list">Click the button to load logs</div>
                    </div>
                </div>
            </div>
        </div>

        <script>
            function loadProducts() {
                document.getElementById('products-list').innerHTML = 'Loading products...';
                // Aquí iría la llamada AJAX
                setTimeout(function() {
                    document.getElementById('products-list').innerHTML = 'Products loaded successfully!';
                }, 1000);
            }

            function loadLogs() {
                document.getElementById('logs-list').innerHTML = 'Loading logs...';
                // Aquí iría la llamada AJAX
                setTimeout(function() {
                    document.getElementById('logs-list').innerHTML = 'Logs loaded successfully!';
                }, 1000);
            }
        </script>
    </div>
</div>
