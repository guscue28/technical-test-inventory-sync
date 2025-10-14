# PrestaShop Integration Guide

## Inventory Audit Module Implementation

This guide provides comprehensive instructions for implementing the Inventory Audit Module as a native PrestaShop module, enabling seamless inventory management directly within the PrestaShop admin interface.

## 📋 Prerequisites

### System Requirements

- **PrestaShop**: 8.0 or higher (tested on 8.1.7)
- **PHP**: 8.1 or higher
- **Laravel Backend**: Running Inventory Sync API
- **Database**: MySQL 8.0 or higher
- **Web Server**: Apache/Nginx with mod_rewrite enabled

### Backend API Requirements

- Inventory Sync Module API running on `http://127.0.0.1:8000/api`
- CORS configuration allowing PrestaShop domain
- API endpoints accessible and functional

## 🚀 Installation

### Step 1: Copy Module Files

Copy the complete module structure to PrestaShop:

```bash
# Copy module to PrestaShop modules directory
cp -r cms-integration/prestashop/modules/inventoryaudit /path/to/prestashop/modules/

# Set proper permissions
chmod -R 755 /path/to/prestashop/modules/inventoryaudit
chmod -R 644 /path/to/prestashop/modules/inventoryaudit/**/*.php
```

### Step 2: Module Directory Structure

The module follows PrestaShop conventions:

```
modules/inventoryaudit/
├── inventoryaudit.php                    # Main module class
├── controllers/
│   └── admin/
│       └── AdminInventoryAuditController.php  # Admin controller
├── views/
│   ├── css/
│   │   ├── styles.css                    # Main styles
│   │   ├── responsive.css                # Responsive styles
│   │   └── prestashop-fixes.css          # PrestaShop-specific fixes
│   ├── js/
│   │   ├── config.js                     # Configuration
│   │   ├── api.js                        # API handling
│   │   └── app.js                        # Main application
│   └── templates/
│       └── admin/
│           ├── panel.tpl                 # Main panel template
│           └── inventory-panel.html      # Frontend UI
└── config.xml                           # Module configuration
```

### Step 3: Install Module in PrestaShop Admin

1. **Access PrestaShop Admin Panel**

   - Navigate to `Modules > Module Manager`
   - Click `Upload a module`
   - Or install directly from `Modules > Module Manager > Installed modules`

2. **Install Inventory Audit Module**

   - Find "Inventory Audit" in the modules list
   - Click `Install`
   - Configure module settings

3. **Verify Installation**
   - Navigate to `Advanced Parameters > Administration`
   - Look for "Inventory Audit" menu item

## ⚙️ Configuration

### Step 1: API Configuration

Update API settings in the module configuration:

```php
// In inventoryaudit.php
public function getConfig() {
    return [
        'api_url' => 'http://127.0.0.1:8000/api',  // Update as needed
        'items_per_page' => 10,
        'auto_refresh' => false,
        'refresh_interval' => 300000
    ];
}
```

### Step 2: Backend CORS Configuration

Ensure Laravel backend allows PrestaShop requests:

```php
// In backend/config/cors.php
'allowed_origins' => [
    'http://localhost:8888',    // PrestaShop development URL
    'http://127.0.0.1:8888',    // Alternative local URL
    'https://your-prestashop-domain.com', // Production URL
],
```

### Step 3: PrestaShop Specific Adjustments

The module includes PrestaShop-specific CSS fixes:

```css
/* prestashop-fixes.css handles: */
- Modal sizing and positioning
- Color scheme integration
- Layout spacing adjustments
- Form input styling
- Button styling consistency
```

## 🎨 User Interface Integration

### Admin Menu Integration

The module adds a new menu item under "Advanced Parameters":

```php
// Admin tab configuration
$this->tabs = [
    [
        'name' => 'Inventory Audit',
        'class_name' => 'AdminInventoryAudit',
        'parent_class_name' => 'AdminAdvancedParameters',
        'module' => $this->name,
    ]
];
```

### Interface Features

1. **Responsive Design**

   - Full-width panel integration
   - Mobile-friendly interface
   - PrestaShop theme compliance

2. **Dual-Tab Navigation**

   - Products tab: View and manage product inventory
   - Logs tab: View audit trail and changes

3. **Advanced Filtering**

   - Date range filtering
   - Product ID filtering
   - User source filtering
   - Real-time search

4. **Modal Dialogs**
   - Create new products
   - Edit existing products
   - Consistent PrestaShop styling

## 🔌 API Integration

### Configuration Management

The module passes configuration from PrestaShop to frontend JavaScript:

```smarty
{* In panel.tpl *}
<script type="text/javascript">
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
        // ... more configuration
    }
};
</script>
```

### AJAX Proxy (Optional)

For enhanced security, implement AJAX proxy methods:

```php
// In AdminInventoryAuditController.php
public function ajaxProcessApiCall() {
    $endpoint = Tools::getValue('endpoint');
    $method = Tools::getValue('method', 'GET');
    $data = Tools::getValue('data');

    // Validate and proxy API calls
    // Return JSON response
}
```

## 🔧 Customization

### Theme Integration

Customize the module to match your PrestaShop theme:

1. **Color Scheme**

   ```css
   /* Update in prestashop-fixes.css */
   :root {
     --primary-color: #your-brand-color;
     --secondary-color: #your-secondary-color;
   }
   ```

2. **Layout Adjustments**
   ```css
   /* Adjust spacing and sizing */
   .inventory-panel-wrapper {
     margin: your-custom-margins;
     padding: your-custom-padding;
   }
   ```

### Functionality Extensions

1. **Add New Filters**

   ```javascript
   // In app.js - extend filter functionality
   const customFilters = {
     category_id: $("#categoryFilter").val(),
     supplier_id: $("#supplierFilter").val(),
   };
   ```

2. **Custom API Endpoints**
   ```php
   // Extend AdminInventoryAuditController
   public function ajaxProcessCustomAction() {
       // Custom functionality
   }
   ```

## 📱 Mobile Responsiveness

The module is fully responsive with breakpoints:

- **Mobile** (< 768px): Stacked layout, touch-friendly buttons
- **Tablet** (768px - 1024px): Optimized two-column layout
- **Desktop** (> 1024px): Full-width multi-column layout

## 🔒 Security Considerations

### Input Validation

```php
// All inputs are validated
$productId = (int)Tools::getValue('product_id');
$stock = (int)Tools::getValue('stock');

// CSRF protection with PrestaShop tokens
if (!Tools::isSubmit('token') || !Tools::getAdminTokenLite('AdminInventoryAudit')) {
    die('Invalid token');
}
```

### API Communication

- HTTPS recommended for production
- API authentication if required
- Input sanitization on all data

## 🚀 Performance Optimization

### Frontend Optimization

1. **Asset Loading**

   ```php
   // Optimized CSS/JS loading
   $this->addCSS(_MODULE_DIR_ . 'inventoryaudit/views/css/styles.css');
   $this->addJS(_MODULE_DIR_ . 'inventoryaudit/views/js/app.js');
   ```

2. **Caching Strategy**
   ```javascript
   // API response caching
   const cache = new Map();
   const cacheKey = `${endpoint}_${JSON.stringify(params)}`;
   ```

### Database Considerations

- Use PrestaShop's existing database connection
- Implement query caching for frequently accessed data
- Consider database indexes for custom queries

## 🐛 Troubleshooting

### Common Issues

1. **Module Not Visible**

   ```bash
   # Check file permissions
   chmod -R 755 modules/inventoryaudit

   # Clear PrestaShop cache
   rm -rf var/cache/*
   ```

2. **API Connection Issues**

   ```javascript
   // Check browser console for CORS errors
   // Verify API URL in configuration
   // Test API endpoints directly
   ```

3. **Styling Issues**

   ```css
   /* Check CSS loading order */
   /* Verify prestashop-fixes.css is loaded last */
   /* Clear browser cache (Ctrl+F5) */
   ```

4. **JavaScript Errors**
   ```javascript
   // Check for jQuery conflicts
   // Verify all JS files are loaded
   // Check browser console for errors
   ```

### Debug Mode

Enable debug mode for troubleshooting:

```php
// In inventoryaudit.php
public function __construct() {
    $this->debug = true; // Enable debug mode
    parent::__construct();
}
```

## 📊 Monitoring and Analytics

### Performance Monitoring

Track key metrics:

- Module load time
- API response times
- User interaction events
- Error rates

### Usage Analytics

Monitor module usage:

```javascript
// Track user interactions
analytics.track("inventory_action", {
  action: "stock_update",
  product_id: productId,
  timestamp: new Date(),
});
```

## 🔄 Updates and Maintenance

### Module Updates

1. **Backup Current Installation**

   ```bash
   cp -r modules/inventoryaudit modules/inventoryaudit_backup
   ```

2. **Apply Updates**

   ```bash
   # Replace module files
   # Run upgrade hooks if needed
   ```

3. **Test Functionality**
   - Verify all features work
   - Check API connectivity
   - Test responsive design

### Maintenance Tasks

- Regular cache clearing
- Monitor error logs
- Update API configurations as needed
- Review performance metrics

## 📞 Support and Documentation

### Additional Resources

- **PrestaShop Documentation**: [PrestaShop Developer Guide](https://devdocs.prestashop.com/)
- **Laravel API Documentation**: See main `README.md`
- **Frontend Documentation**: Check `frontend/` directory

### Getting Help

1. Check browser console for JavaScript errors
2. Review PrestaShop error logs
3. Test API endpoints independently
4. Verify file permissions and structure

---

**Implementation Time**: ~2-4 hours for experienced developers

**Maintenance**: Low - mostly configuration updates

**Compatibility**: PrestaShop 8.0+ with backward compatibility considerations

This integration provides a native PrestaShop experience while leveraging the powerful Inventory Sync API backend.
