/**
 * Inventory Audit Panel - API Handler
 * Handles all API communications with jQuery AJAX
 */

window.InventoryAPI = (function ($) {
  "use strict";

  const config = window.InventoryConfig;
  let requestCache = new Map();
  let activeRequests = new Map();

  /**
   * Generate cache key from URL and parameters
   */
  function generateCacheKey(url, params) {
    const paramString = params ? JSON.stringify(params) : "";
    return `${url}:${paramString}`;
  }

  /**
   * Check if cached data is still valid
   */
  function isCacheValid(cacheItem) {
    if (!cacheItem) return false;
    return Date.now() - cacheItem.timestamp < config.PERFORMANCE.CACHE_DURATION;
  }

  /**
   * Generic AJAX request handler with retry logic
   */
  function makeRequest(options) {
    const {
      url,
      method = "GET",
      data = null,
      params = null,
      useCache = true,
      timeout = config.API.TIMEOUT,
    } = options;

    return new Promise((resolve, reject) => {
      // Build full URL
      const fullUrl = config.API.BASE_URL + url;

      // Check cache first for GET requests
      if (method === "GET" && useCache) {
        const cacheKey = generateCacheKey(fullUrl, params || data);
        const cached = requestCache.get(cacheKey);
        if (isCacheValid(cached)) {
          if (config.DEBUG.LOG_API_CALLS) {
            console.log("API Cache Hit:", fullUrl);
          }
          resolve(cached.data);
          return;
        }
      }

      // Check if request is already in progress
      const requestKey = generateCacheKey(fullUrl, params || data);
      if (activeRequests.has(requestKey)) {
        activeRequests.get(requestKey).then(resolve).catch(reject);
        return;
      }

      // Create the promise and store it
      const requestPromise = performRequest();
      activeRequests.set(requestKey, requestPromise);

      requestPromise
        .then((response) => {
          activeRequests.delete(requestKey);
          resolve(response);
        })
        .catch((error) => {
          activeRequests.delete(requestKey);
          reject(error);
        });

      function performRequest(attempt = 1) {
        return new Promise((resolveAttempt, rejectAttempt) => {
          if (config.DEBUG.LOG_API_CALLS) {
            console.log(`API Call (attempt ${attempt}):`, method, fullUrl);
          }

          // Prepare AJAX options
          const ajaxOptions = {
            url: fullUrl,
            method: method,
            timeout: timeout,
            dataType: "json",
            contentType: "application/json",
            beforeSend: function (xhr) {
              // Add any authentication headers here if needed
              xhr.setRequestHeader("X-Requested-With", "XMLHttpRequest");
            },
          };

          // Add data based on request method
          if (method === "GET" && params) {
            ajaxOptions.data = params;
          } else if (method !== "GET" && data) {
            ajaxOptions.data = JSON.stringify(data);
          }

          // Make the AJAX request
          $.ajax(ajaxOptions)
            .done(function (response) {
              // Cache successful GET requests
              if (method === "GET" && useCache) {
                const cacheKey = generateCacheKey(fullUrl, params || data);
                requestCache.set(cacheKey, {
                  data: response,
                  timestamp: Date.now(),
                });
              }

              resolveAttempt(response);
            })
            .fail(function (xhr, status, error) {
              const errorInfo = {
                status: xhr.status,
                statusText: xhr.statusText,
                responseText: xhr.responseText,
                originalError: error,
                attempt: attempt,
              };

              // Log error if debugging
              if (config.DEBUG.LOG_API_CALLS) {
                console.error("API Error:", errorInfo);
              }

              // Retry logic for certain errors
              if (
                attempt < config.API.RETRY_ATTEMPTS &&
                shouldRetry(xhr.status)
              ) {
                setTimeout(() => {
                  performRequest(attempt + 1)
                    .then(resolveAttempt)
                    .catch(rejectAttempt);
                }, config.API.RETRY_DELAY * attempt);
              } else {
                rejectAttempt(errorInfo);
              }
            });
        });
      }
    });
  }

  /**
   * Determine if request should be retried based on status code
   */
  function shouldRetry(statusCode) {
    return (
      statusCode >= 500 ||
      statusCode === 0 ||
      statusCode === 408 ||
      statusCode === 429
    );
  }

  /**
   * Process API error and return user-friendly message
   */
  function processError(error) {
    let message = config.MESSAGES.ERRORS.UNKNOWN_ERROR;

    if (error.status === 0) {
      message = config.MESSAGES.ERRORS.NETWORK_ERROR;
    } else if (error.status === 408) {
      message = config.MESSAGES.ERRORS.TIMEOUT_ERROR;
    } else if (error.status >= 400 && error.status < 500) {
      message = config.MESSAGES.ERRORS.VALIDATION_ERROR;
    } else if (error.status >= 500) {
      message = config.MESSAGES.ERRORS.API_ERROR;
    }

    // Try to extract error message from response
    try {
      const response = JSON.parse(error.responseText);
      if (response.message) {
        message = response.message;
      }
    } catch (e) {
      // Response is not valid JSON, use default message
    }

    return {
      message: message,
      status: error.status,
      details: error,
    };
  }

  // Public API methods
  return {
    /**
     * Test API connection
     */
    testConnection: function () {
      return makeRequest({
        url: config.API.ENDPOINTS.HEALTH_CHECK,
        useCache: false,
      });
    },

    /**
     * Get inventory logs with filters
     */
    getInventoryLogs: function (
      filters = {},
      page = 1,
      perPage = config.UI.ITEMS_PER_PAGE
    ) {
      const params = {
        ...filters,
        page: page,
        per_page: perPage,
      };

      // Remove empty parameters
      Object.keys(params).forEach((key) => {
        if (
          params[key] === "" ||
          params[key] === null ||
          params[key] === undefined
        ) {
          delete params[key];
        }
      });

      return makeRequest({
        url: config.API.ENDPOINTS.INVENTORY_LOGS,
        params: params,
      });
    },

    /**
     * Get inventory statistics
     */
    getInventoryStatistics: function (dateFrom = null, dateTo = null) {
      const params = {};
      if (dateFrom) params.date_from = dateFrom;
      if (dateTo) params.date_to = dateTo;

      return makeRequest({
        url: config.API.ENDPOINTS.INVENTORY_LOGS + "/statistics",
        params: params,
      });
    },

    /**
     * Update product stock
     */
    updateProductStock: function (
      productId,
      newStock,
      userSource = "audit_panel"
    ) {
      return makeRequest({
        url: `${config.API.ENDPOINTS.PRODUCTS}/${productId}/stock`,
        method: "PATCH",
        data: {
          stock: newStock,
          user_source: userSource,
        },
        useCache: false,
      });
    },

    /**
     * Get product details
     */
    getProduct: function (productId) {
      return makeRequest({
        url: `${config.API.ENDPOINTS.PRODUCTS}/${productId}`,
      });
    },

    /**
     * Get all products
     */
    getAllProducts: function () {
      return makeRequest({
        url: config.API.ENDPOINTS.PRODUCTS,
      });
    },

    /**
     * Get product logs
     */
    getProductLogs: function (productId, limit = 20) {
      return makeRequest({
        url: `${config.API.ENDPOINTS.PRODUCTS}/${productId}/logs`,
        params: { limit: limit },
      });
    },

    /**
     * Export logs
     */
    exportLogs: function (filters = {}, format = "csv") {
      const params = {
        ...filters,
        format: format,
      };

      // Remove empty parameters
      Object.keys(params).forEach((key) => {
        if (
          params[key] === "" ||
          params[key] === null ||
          params[key] === undefined
        ) {
          delete params[key];
        }
      });

      // For CSV export, we need to handle it differently as it returns a file
      if (format === "csv") {
        return this.exportCsv(params);
      }

      return makeRequest({
        url: config.API.ENDPOINTS.INVENTORY_LOGS + "/export",
        params: params,
        useCache: false,
      });
    },

    /**
     * Export CSV (special handling for file download)
     */
    exportCsv: function (params) {
      return new Promise((resolve, reject) => {
        const queryString = $.param(params);
        const url = `${config.API.BASE_URL}${config.API.ENDPOINTS.INVENTORY_LOGS}/export?${queryString}`;

        // Create temporary link for download
        const link = document.createElement("a");
        link.href = url;
        link.download = `inventory_logs_${
          new Date().toISOString().split("T")[0]
        }.csv`;
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);

        resolve({ success: true, message: "CSV export started" });
      });
    },

    /**
     * Create new product
     */
    createProduct: function (productData) {
      return makeRequest({
        url: config.API.ENDPOINTS.PRODUCTS,
        method: "POST",
        data: productData,
        useCache: false,
      });
    },

    /**
     * Update existing product
     */
    updateProduct: function (productId, productData) {
      return makeRequest({
        url: `${config.API.ENDPOINTS.PRODUCTS}/${productId}`,
        method: "PUT",
        data: productData,
        useCache: false,
      });
    },

    /**
     * Delete product
     */
    deleteProduct: function (productId) {
      return makeRequest({
        url: `${config.API.ENDPOINTS.PRODUCTS}/${productId}`,
        method: "DELETE",
        useCache: false,
      });
    },

    /**
     * Clear cache
     */
    clearCache: function () {
      requestCache.clear();
      if (config.DEBUG.LOG_API_CALLS) {
        console.log("API cache cleared");
      }
    },

    /**
     * Get cache info
     */
    getCacheInfo: function () {
      return {
        size: requestCache.size,
        entries: Array.from(requestCache.keys()),
      };
    },

    /**
     * Process error for UI consumption
     */
    processError: processError,

    /**
     * Check if API is available (simple connectivity test)
     */
    isApiAvailable: function () {
      return this.testConnection()
        .then(() => true)
        .catch(() => false);
    },
  };
})(jQuery);
