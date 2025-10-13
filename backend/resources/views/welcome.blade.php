<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Inventory Sync Module API</title>
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            margin: 0;
            padding: 20px;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .container {
            background: white;
            padding: 40px;
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.2);
            max-width: 800px;
            width: 100%;
        }
        h1 {
            color: #333;
            text-align: center;
            margin-bottom: 30px;
            font-size: 2.5em;
        }
        .subtitle {
            text-align: center;
            color: #666;
            font-size: 1.2em;
            margin-bottom: 40px;
        }
        .endpoint-section {
            margin-bottom: 30px;
        }
        .endpoint-section h3 {
            color: #4a5568;
            border-bottom: 2px solid #e2e8f0;
            padding-bottom: 10px;
            margin-bottom: 15px;
        }
        .endpoint {
            background: #f7fafc;
            padding: 12px;
            margin: 8px 0;
            border-radius: 8px;
            border-left: 4px solid #667eea;
        }
        .method {
            font-weight: bold;
            color: #667eea;
        }
        .url {
            font-family: 'Courier New', monospace;
            background: #e2e8f0;
            padding: 2px 6px;
            border-radius: 4px;
            margin: 0 8px;
        }
        .description {
            color: #666;
            margin-left: 20px;
        }
        .status {
            text-align: center;
            margin-top: 30px;
            padding: 20px;
            background: #f0fff4;
            border-radius: 8px;
            border: 1px solid #9ae6b4;
        }
        .status-badge {
            display: inline-block;
            background: #48bb78;
            color: white;
            padding: 5px 15px;
            border-radius: 20px;
            font-weight: bold;
            margin-right: 10px;
        }
        .footer {
            text-align: center;
            margin-top: 30px;
            color: #666;
            font-size: 0.9em;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>🚀 Inventory Sync Module API</h1>
        <p class="subtitle">Critical Inventory Synchronization System with ACID Transactions</p>

        <div class="status">
            <span class="status-badge">✅ ONLINE</span>
            <strong>API Status:</strong> All systems operational | Version 1.0.0
        </div>

        <div class="endpoint-section">
            <h3>📦 Product Management</h3>
            <div class="endpoint">
                <span class="method">GET</span><span class="url">/api/products</span>
                <div class="description">List all products with pagination</div>
            </div>
            <div class="endpoint">
                <span class="method">GET</span><span class="url">/api/products/{id}</span>
                <div class="description">Get specific product details</div>
            </div>
            <div class="endpoint">
                <span class="method">POST</span><span class="url">/api/products</span>
                <div class="description">Create new product with stock initialization</div>
            </div>
            <div class="endpoint">
                <span class="method">PATCH/PUT</span><span class="url">/api/products/{id}</span>
                <div class="description">Update product information and stock levels</div>
            </div>
            <div class="endpoint">
                <span class="method">DELETE</span><span class="url">/api/products/{id}</span>
                <div class="description">Delete product with cascade audit log cleanup</div>
            </div>
        </div>

        <div class="endpoint-section">
            <h3>📊 Audit & Logging</h3>
            <div class="endpoint">
                <span class="method">GET</span><span class="url">/api/inventory-logs</span>
                <div class="description">Complete inventory change history with timestamps</div>
            </div>
        </div>

        <div class="endpoint-section">
            <h3>🔧 System Health</h3>
            <div class="endpoint">
                <span class="method">GET</span><span class="url">/api/health</span>
                <div class="description">API health check and system status</div>
            </div>
        </div>

        <div class="footer">
            <p><strong>Technical Features:</strong></p>
            <p>✅ ACID Transaction Support | ✅ Complete Audit Trail | ✅ RESTful API Design | ✅ Input Validation</p>
            <p>✅ Error Handling | ✅ Structured JSON Responses | ✅ Scalable Architecture</p>
            <br>
            <p>Generated at {{ now()->format('Y-m-d H:i:s') }}</p>
        </div>
    </div>
</body>
</html>
