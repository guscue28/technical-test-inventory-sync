/**
 * Inventory Audit Panel - Configuration
 * Critical Inventory Synchronization Module
 */

window.InventoryConfig = {
  // API Configuration
  API: {
    BASE_URL: "/api", // Change this to your API base URL
    ENDPOINTS: {
      PRODUCTS: "/products",
      INVENTORY_LOGS: "/inventory-logs",
      HEALTH_CHECK: "/health",
    },
    TIMEOUT: 30000, // 30 seconds
    RETRY_ATTEMPTS: 3,
    RETRY_DELAY: 1000, // 1 second
  },

  // UI Configuration
  UI: {
    ITEMS_PER_PAGE: 50,
    MAX_ITEMS_PER_PAGE: 100,
    AUTO_REFRESH_INTERVAL: 300000, // 5 minutes
    ANIMATION_DURATION: 300,
    DEBOUNCE_DELAY: 500,
  },

  // Table Configuration
  TABLE: {
    SORTABLE_COLUMNS: ["id", "created_at", "product_name", "user_source"],
    DEFAULT_SORT: {
      column: "created_at",
      direction: "desc",
    },
    VISIBLE_COLUMNS: {
      mobile: ["created_at", "product_name", "change_amount"],
      tablet: [
        "id",
        "created_at",
        "product_name",
        "previous_stock",
        "new_stock",
        "change_amount",
      ],
      desktop: [
        "id",
        "created_at",
        "product_name",
        "previous_stock",
        "new_stock",
        "change_amount",
        "user_source",
        "actions",
      ],
    },
  },

  // Filters Configuration
  FILTERS: {
    DEFAULT_DATE_RANGE: 30, // days
    MAX_DATE_RANGE: 365, // days
    PERSISTENT_FILTERS: true, // Save filters in localStorage
    FILTER_KEY: "inventory_audit_filters",
  },

  // Export Configuration
  EXPORT: {
    FORMATS: ["csv", "json"],
    MAX_EXPORT_RECORDS: 10000,
    CSV_DELIMITER: ",",
    CSV_HEADERS: [
      "ID",
      "Date",
      "Product ID",
      "Product Name",
      "Previous Stock",
      "New Stock",
      "Change Amount",
      "User/Source",
    ],
  },

  // Notification Configuration
  NOTIFICATIONS: {
    SUCCESS_DURATION: 3000,
    ERROR_DURATION: 5000,
    WARNING_DURATION: 4000,
    POSITION: "top-right",
  },

  // Performance Configuration
  PERFORMANCE: {
    VIRTUAL_SCROLLING_THRESHOLD: 1000,
    LAZY_LOADING: true,
    CACHE_DURATION: 300000, // 5 minutes
    PRELOAD_PAGES: 2,
  },

  // Error Messages
  MESSAGES: {
    ERRORS: {
      NETWORK_ERROR: "Network error occurred. Please check your connection.",
      API_ERROR: "API error occurred. Please try again later.",
      VALIDATION_ERROR: "Invalid input data. Please check your entries.",
      TIMEOUT_ERROR: "Request timed out. Please try again.",
      UNKNOWN_ERROR: "An unknown error occurred. Please try again.",
      NO_DATA: "No inventory logs found for the selected criteria.",
      LOAD_FAILED: "Failed to load inventory data.",
    },
    SUCCESS: {
      DATA_LOADED: "Inventory data loaded successfully.",
      EXPORT_COMPLETE: "Export completed successfully.",
      FILTERS_APPLIED: "Filters applied successfully.",
      API_TEST_PASSED: "API connection test passed.",
    },
    WARNINGS: {
      LARGE_DATASET: "Large dataset detected. Loading may take a moment.",
      UNSAVED_FILTERS: "You have unsaved filter changes.",
      EXPORT_LIMIT: "Export limited to maximum allowed records.",
    },
  },

  // Feature Flags
  FEATURES: {
    REAL_TIME_UPDATES: false,
    ADVANCED_FILTERS: true,
    EXPORT_FUNCTIONALITY: true,
    STATISTICS_VIEW: true,
    DARK_MODE: true,
    OFFLINE_MODE: false,
    KEYBOARD_SHORTCUTS: true,
  },

  // Keyboard Shortcuts
  SHORTCUTS: {
    REFRESH: "r",
    CLEAR_FILTERS: "c",
    EXPORT: "e",
    FOCUS_SEARCH: "f",
    TOGGLE_VIEW: "v",
    HELP: "h",
  },

  // Local Storage Keys
  STORAGE_KEYS: {
    FILTERS: "inventory_audit_filters",
    USER_PREFERENCES: "inventory_audit_preferences",
    CACHE: "inventory_audit_cache",
    VIEW_MODE: "inventory_audit_view_mode",
  },

  // Date/Time Configuration
  DATETIME: {
    FORMAT: "YYYY-MM-DD HH:mm:ss",
    DISPLAY_FORMAT: "MMM DD, YYYY HH:mm",
    TIMEZONE: "auto", // auto-detect or specify timezone
    RELATIVE_TIME: true, // Show "2 hours ago" instead of exact time
  },

  // Validation Rules
  VALIDATION: {
    PRODUCT_ID: {
      MIN: 1,
      MAX: 999999999,
    },
    DATE_RANGE: {
      MAX_DAYS: 365,
    },
    STOCK_VALUES: {
      MIN: 0,
      MAX: 999999,
    },
  },

  // Chart Configuration (for future statistics features)
  CHARTS: {
    COLORS: {
      PRIMARY: "#2563eb",
      SUCCESS: "#10b981",
      WARNING: "#f59e0b",
      DANGER: "#dc2626",
      GRAY: "#64748b",
    },
    ANIMATION: {
      DURATION: 750,
      EASING: "easeInOutQuart",
    },
  },

  // Development Configuration
  DEBUG: {
    ENABLED: false, // Set to true for development
    LOG_API_CALLS: false,
    LOG_PERFORMANCE: false,
    MOCK_API: false,
  },
};

// Freeze the configuration to prevent accidental modifications
if (Object.freeze) {
  Object.freeze(window.InventoryConfig);
  Object.freeze(window.InventoryConfig.API);
  Object.freeze(window.InventoryConfig.UI);
  Object.freeze(window.InventoryConfig.TABLE);
  Object.freeze(window.InventoryConfig.FILTERS);
  Object.freeze(window.InventoryConfig.EXPORT);
  Object.freeze(window.InventoryConfig.MESSAGES);
  Object.freeze(window.InventoryConfig.FEATURES);
  Object.freeze(window.InventoryConfig.SHORTCUTS);
  Object.freeze(window.InventoryConfig.STORAGE_KEYS);
  Object.freeze(window.InventoryConfig.VALIDATION);
}
