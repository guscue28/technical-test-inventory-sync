/**
 * Inventory Audit Panel - Main Application
 * Critical Inventory Synchronization Module
 */

window.InventoryApp = (function ($) {
  "use strict";

  const config = window.InventoryConfig;
  const api = window.InventoryAPI;

  // Application state
  let currentData = [];
  let currentFilters = {};
  let currentPage = 1;
  let totalPages = 1;
  let isLoading = false;
  let autoRefreshTimer = null;

  // Tab state
  let activeTab = "products"; // 'products' or 'logs'
  let productsData = [];
  let productsPage = 1;
  let productsTotalPages = 1;

  /**
   * Local notification function
   */
  function showNotification(message, type = "info") {
    console.log(`${type.toUpperCase()}: ${message}`);
    // For now, just log to console. Can be enhanced later.
  }

  /**
   * Initialize the application
   */
  function init() {
    console.log("Initializing Inventory Audit Panel...");

    // Setup event handlers
    setupEventHandlers();

    // Initialize filter visibility based on default active tab
    if (activeTab === "products") {
      $("#productFilters").show();
      $("#logFilters").hide();
    } else {
      $("#productFilters").hide();
      $("#logFilters").show();
    }

    // Update button texts for the initial active tab
    updateButtonTexts();

    // Load saved filters
    loadSavedFilters();

    // Initial data load for both tabs
    loadProductsData();
    loadInventoryData();

    // Setup auto-refresh if enabled
    if (config.UI.AUTO_REFRESH_INTERVAL > 0) {
      startAutoRefresh();
    }

    // Test API connection
    testApiConnection();

    console.log("Inventory Audit Panel initialized successfully");
  }

  /**
   * Setup all event handlers
   */
  function setupEventHandlers() {
    // Filter form submission
    $("#filtersForm").on("submit", function (e) {
      e.preventDefault();
      applyFilters();
    });

    // Clear filters button
    $("#clearFilters").on("click", clearFilters);

    // Refresh button
    $("#refreshData").on("click", function () {
      InventoryApp.refreshCurrentTab();
    });

    // Quick actions
    $("#createProduct").on("click", function () {
      InventoryApp.createProduct();
    });
    $("#exportCsv").on("click", function () {
      InventoryApp.exportToCsv();
    });
    $("#viewStats").on("click", function () {
      InventoryApp.showStatistics();
    });
    $("#testApi").on("click", function () {
      InventoryApp.testApiConnection();
    });
    $("#toggleView").on("click", function () {
      InventoryApp.toggleTableView();
    });

    // Tab navigation
    $(".tab-btn").on("click", function () {
      const tabName = $(this).data("tab");
      switchTab(tabName);
    });

    // Edit product form submission
    $("#editProductForm").on("submit", handleProductFormSubmit);

    // Modal close buttons
    $(".modal-close").on("click", function () {
      InventoryApp.closeModals();
    });
    $(".modal").on("click", function (e) {
      if (e.target === this) {
        closeModals();
      }
    });

    // Error retry buttons
    $("#retryLoad").on("click", function () {
      loadInventoryData(true);
    });

    $("#retryLoadProducts").on("click", function () {
      loadProductsData(true);
    });

    // Table sorting
    $(".logs-table th.sortable").on("click", function () {
      const column = $(this).data("sort");
      sortTable(column);
    });

    // Product action buttons (using event delegation)
    $(document).on("click", ".edit-btn", function (e) {
      e.preventDefault();
      e.stopPropagation();
      const productId = $(this).data("product-id");
      InventoryApp.editProduct(productId);
    });

    $(document).on("click", ".view-btn", function (e) {
      e.preventDefault();
      e.stopPropagation();
      const productId = $(this).data("product-id");
      InventoryApp.viewProductDetails(productId);
    });

    $(document).on("click", ".delete-btn", function (e) {
      e.preventDefault();
      e.stopPropagation();
      const productId = $(this).data("product-id");
      InventoryApp.deleteProduct(productId);
    });

    // Keyboard shortcuts
    if (config.FEATURES.KEYBOARD_SHORTCUTS) {
      setupKeyboardShortcuts();
    }

    // Window events
    $(window).on("beforeunload", saveCurrentState);
    $(window).on("resize", debounce(handleResize, 300));
  }

  /**
   * Setup keyboard shortcuts
   */
  function setupKeyboardShortcuts() {
    $(document).on("keydown", function (e) {
      // Skip if user is typing in an input field
      if ($(e.target).is("input, textarea, select")) {
        return;
      }

      switch (e.key.toLowerCase()) {
        case config.SHORTCUTS.REFRESH:
          e.preventDefault();
          loadInventoryData(true);
          break;
        case config.SHORTCUTS.CLEAR_FILTERS:
          e.preventDefault();
          clearFilters();
          break;
        case config.SHORTCUTS.EXPORT:
          e.preventDefault();
          exportToCsv();
          break;
        case config.SHORTCUTS.FOCUS_SEARCH:
          e.preventDefault();
          $("#productId").focus();
          break;
        case config.SHORTCUTS.TOGGLE_VIEW:
          e.preventDefault();
          toggleTableView();
          break;
      }
    });
  }

  /**
   * Load inventory data from API
   */
  function loadInventoryData(forceRefresh = false) {
    if (isLoading && !forceRefresh) {
      return Promise.resolve();
    }

    isLoading = true;
    showLoading(true);
    hideError();

    // Clear cache if force refresh
    if (forceRefresh) {
      api.clearCache();
    }

    return api
      .getInventoryLogs(currentFilters, currentPage, config.UI.ITEMS_PER_PAGE)
      .then(function (response) {
        if (response.success) {
          currentData = response.data;
          updateTable(response.data);
          updatePagination(response.pagination);
          updateStatsSummary(response.pagination);

          if (response.data.length === 0) {
            showEmptyState();
          }

          showNotification(config.MESSAGES.SUCCESS.DATA_LOADED, "success");
        } else {
          throw new Error(
            "API returned error: " + (response.message || "Unknown error")
          );
        }
      })
      .catch(function (error) {
        console.error("Error loading data:", error);
        const processedError = api.processError(error);
        showError(processedError.message);
        showNotification(processedError.message, "error");

        // Update results count to show error state
        $("#resultsCount").text("Error loading data");
      })
      .finally(function () {
        isLoading = false;
        showLoading(false);
      });
  }

  /**
   * Apply filters and reload data based on active tab
   */
  function applyFilters() {
    if (activeTab === "products") {
      applyProductFilters();
    } else {
      applyLogFilters();
    }
  }

  /**
   * Apply filters for products tab
   */
  function applyProductFilters() {
    // Collect product filter values
    currentFilters = {
      search: $("#search").val() || null,
      min_stock: $("#minStock").val() || null,
      max_stock: $("#maxStock").val() || null,
    };

    // Remove empty filters
    Object.keys(currentFilters).forEach((key) => {
      if (!currentFilters[key]) {
        delete currentFilters[key];
      }
    });

    // Reset to first page
    productsPage = 1;

    // Save filters if persistence is enabled
    if (config.FILTERS.PERSISTENT_FILTERS) {
      saveFilters();
    }

    // Load filtered products
    loadProductsData(true);

    showNotification("Product filters applied", "success");
  }

  /**
   * Apply filters for logs tab
   */
  function applyLogFilters() {
    // Collect log filter values
    currentFilters = {
      search: $("#searchLogs").val() || null,
      date_from: $("#dateFrom").val() || null,
      date_to: $("#dateTo").val() || null,
    };

    // Remove empty filters
    Object.keys(currentFilters).forEach((key) => {
      if (!currentFilters[key]) {
        delete currentFilters[key];
      }
    });

    // Reset to first page
    currentPage = 1;

    // Save filters if persistence is enabled
    if (config.FILTERS.PERSISTENT_FILTERS) {
      saveFilters();
    }

    // Load filtered logs
    loadInventoryData(true);

    showNotification("Log filters applied", "success");
  }

  /**
   * Clear all filters based on active tab
   */
  function clearFilters() {
    if (activeTab === "products") {
      clearProductFilters();
    } else {
      clearLogFilters();
    }
  }

  /**
   * Clear product filters
   */
  function clearProductFilters() {
    // Reset product filter fields
    $("#search").val("");
    $("#minStock").val("");
    $("#maxStock").val("");

    currentFilters = {};
    productsPage = 1;

    // Clear saved filters
    if (config.FILTERS.PERSISTENT_FILTERS) {
      localStorage.removeItem(config.STORAGE_KEYS.FILTERS);
    }

    loadProductsData(true);
    showNotification("Product filters cleared", "info");
  }

  /**
   * Clear log filters
   */
  function clearLogFilters() {
    // Reset log filter fields
    $("#searchLogs").val("");
    $("#dateFrom").val("");
    $("#dateTo").val("");

    currentFilters = {};
    currentPage = 1;

    // Clear saved filters
    if (config.FILTERS.PERSISTENT_FILTERS) {
      localStorage.removeItem(config.STORAGE_KEYS.FILTERS);
    }

    loadInventoryData(true);
    showNotification("Log filters cleared", "info");
  }

  /**
   * Update the data table
   */
  function updateTable(data) {
    const tableBody = $("#logsTableBody");
    tableBody.empty();

    if (!data || data.length === 0) {
      tableBody.append(`
                <tr>
                    <td colspan="8" class="text-center text-muted">
                        ${config.MESSAGES.ERRORS.NO_DATA}
                    </td>
                </tr>
            `);
      return;
    }

    data.forEach(function (log) {
      const changeClass = getChangeClass(log.change_amount);
      const changeText = formatChange(log.change_amount);

      const row = `
                <tr class="fade-in">
                    <td>${log.id}</td>
                    <td>
                        <div class="text-bold">${formatDate(
                          log.created_at
                        )}</div>
                        <div class="text-small text-muted">${
                          log.created_at_human
                        }</div>
                    </td>
                    <td>
                        <div class="text-bold">${escapeHtml(
                          log.product_name
                        )}</div>
                        <div class="text-small text-muted">ID: ${
                          log.product_id
                        }</div>
                    </td>
                    <td class="text-right">${log.previous_stock.toLocaleString()}</td>
                    <td class="text-right">${log.new_stock.toLocaleString()}</td>
                    <td class="text-center">
                        <span class="${changeClass}">${changeText}</span>
                    </td>
                    <td class="text-center">
                        <div class="action-buttons">
                            <button class="btn btn-small btn-primary edit-btn" data-product-id="${
                              log.product_id
                            }" title="Edit Product">
                                ✏️
                            </button>
                            <button class="btn btn-small btn-accent view-btn" data-product-id="${
                              log.product_id
                            }" title="View Details">
                                👁️
                            </button>
                            <button class="btn btn-small btn-danger delete-btn" data-product-id="${
                              log.product_id
                            }" title="Delete Product">
                                🗑️
                            </button>
                        </div>
                    </td>
                </tr>
            `;

      tableBody.append(row);
    });
  }

  /**
   * Update pagination controls
   */
  function updatePagination(pagination) {
    if (!pagination) return;

    const paginationContainer = $("#pagination");
    paginationContainer.empty();

    // Use the last_page provided by Laravel pagination
    totalPages = pagination.last_page || 1;
    currentPage = pagination.current_page;

    // Build pagination HTML
    let paginationHtml = "";

    // Previous button
    paginationHtml += `
            <button class="pagination-btn" ${currentPage <= 1 ? "disabled" : ""}
                    onclick="InventoryApp.goToPage(${currentPage - 1})">
                ‹
            </button>
        `;

    // Page numbers
    const startPage = Math.max(1, currentPage - 2);
    const endPage = Math.min(totalPages, currentPage + 2);

    if (startPage > 1) {
      paginationHtml += `<button class="pagination-btn" onclick="InventoryApp.goToPage(1)">1</button>`;
      if (startPage > 2) {
        paginationHtml += `<span class="pagination-ellipsis">...</span>`;
      }
    }

    for (let i = startPage; i <= endPage; i++) {
      paginationHtml += `
                <button class="pagination-btn ${
                  i === currentPage ? "active" : ""
                }"
                        onclick="InventoryApp.goToPage(${i})">
                    ${i}
                </button>
            `;
    }

    if (endPage < totalPages) {
      if (endPage < totalPages - 1) {
        paginationHtml += `<span class="pagination-ellipsis">...</span>`;
      }
      paginationHtml += `<button class="pagination-btn" onclick="InventoryApp.goToPage(${totalPages})">${totalPages}</button>`;
    }

    // Next button
    paginationHtml += `
            <button class="pagination-btn" ${
              currentPage >= totalPages ? "disabled" : ""
            }
                    onclick="InventoryApp.goToPage(${currentPage + 1})">
                ›
            </button>
        `;

    // Pagination info
    paginationHtml += `
            <div class="pagination-info">
                Showing ${pagination.from || 0} to ${pagination.to || 0} of ${
      pagination.total || 0
    } entries
            </div>
        `;

    paginationContainer.html(paginationHtml);
  }

  /**
   * Go to specific page
   */
  function goToPage(page) {
    if (page < 1 || page > totalPages || page === currentPage) {
      return;
    }

    currentPage = page;
    loadInventoryData();
  }

  /**
   * Update stats summary in header
   */
  function updateStatsSummary(pagination) {
    $("#totalLogs").text(pagination.total || 0);

    // Update results count display
    const from = pagination.from || 0;
    const to = pagination.to || 0;
    const total = pagination.total || 0;

    if (total === 0) {
      $("#resultsCount").text("No results found");
    } else {
      $("#resultsCount").text(`Showing ${from} to ${to} of ${total} entries`);
    }

    // Get today's changes (fetch from API)
    const today = new Date();
    const yyyy = today.getFullYear();
    const mm = String(today.getMonth() + 1).padStart(2, "0");
    const dd = String(today.getDate()).padStart(2, "0");
    const todayStr = `${yyyy}-${mm}-${dd}`;

    api
      .getInventoryStatistics(todayStr, todayStr)
      .then(function (response) {
        if (response && response.success && response.data) {
          $("#todayChanges").text(response.data.total_logs || 0);
        } else {
          $("#todayChanges").text("0");
        }
      })
      .catch(function () {
        $("#todayChanges").text("0");
      });
  }

  /**
   * Show/hide loading overlay
   */
  function showLoading(show) {
    if (show) {
      $("#loadingOverlay").fadeIn(200);
    } else {
      $("#loadingOverlay").fadeOut(200);
    }
  }

  /**
   * Show error message
   */
  function showError(message) {
    $("#errorText").text(message);
    $("#errorMessage").slideDown(300);
  }

  /**
   * Hide error message
   */
  function hideError() {
    $("#errorMessage").slideUp(300);
  }

  /**
   * Show empty state
   */
  function showEmptyState() {
    const tableBody = $("#logsTableBody");
    tableBody.html(`
            <tr>
                <td colspan="8" class="text-center" style="padding: 3rem;">
                    <div style="opacity: 0.6;">
                        <div style="font-size: 3rem; margin-bottom: 1rem;">📭</div>
                        <div class="text-bold" style="margin-bottom: 0.5rem;">No inventory logs found</div>
                        <div class="text-muted">Try adjusting your filters or check back later</div>
                    </div>
                </td>
            </tr>
        `);
  }

  // Utility functions
  function getChangeClass(changeAmount) {
    if (changeAmount > 0) return "change-positive";
    if (changeAmount < 0) return "change-negative";
    return "change-neutral";
  }

  function formatChange(changeAmount) {
    if (changeAmount > 0) return `+${changeAmount.toLocaleString()}`;
    if (changeAmount < 0) return changeAmount.toLocaleString();
    return "0";
  }

  function formatDate(dateString) {
    const date = new Date(dateString);
    return (
      date.toLocaleDateString() +
      " " +
      date.toLocaleTimeString([], { hour: "2-digit", minute: "2-digit" })
    );
  }

  function escapeHtml(text) {
    const div = document.createElement("div");
    div.textContent = text;
    return div.innerHTML;
  }

  function debounce(func, wait) {
    let timeout;
    return function executedFunction(...args) {
      const later = () => {
        clearTimeout(timeout);
        func(...args);
      };
      clearTimeout(timeout);
      timeout = setTimeout(later, wait);
    };
  }

  // Additional utility functions...
  function saveFilters() {
    if (config.FILTERS.PERSISTENT_FILTERS) {
      localStorage.setItem(
        config.STORAGE_KEYS.FILTERS,
        JSON.stringify(currentFilters)
      );
    }
  }

  function loadSavedFilters() {
    if (config.FILTERS.PERSISTENT_FILTERS) {
      const savedFilters = localStorage.getItem(config.STORAGE_KEYS.FILTERS);
      if (savedFilters) {
        try {
          currentFilters = JSON.parse(savedFilters);
          // Populate form fields
          Object.keys(currentFilters).forEach((key) => {
            const input = $(`#${key.replace("_", "")}`);
            if (input.length) {
              input.val(currentFilters[key]);
            }
          });
        } catch (e) {
          console.warn("Failed to load saved filters:", e);
        }
      }
    }
  }

  function saveCurrentState() {
    saveFilters();
  }

  function handleResize() {
    // Handle responsive behavior
    updateTableVisibility();
  }

  /**
   * Switch between tabs
   */
  function switchTab(tabName) {
    // Update tab buttons
    $(".tab-btn").removeClass("active");
    $(`[data-tab="${tabName}"]`).addClass("active");

    // Update tab content
    $(".tab-content").removeClass("active");
    $(`#${tabName}TabContent`).addClass("active");

    // Show/hide appropriate filters
    if (tabName === "products") {
      $("#productFilters").show();
      $("#logFilters").hide();
    } else {
      $("#productFilters").hide();
      $("#logFilters").show();
    }

    // Update active tab
    activeTab = tabName;

    // Update button texts based on active tab
    updateButtonTexts();

    console.log("Switched to tab:", tabName);

    // Clear current filters when switching tabs
    clearFilters();

    // Refresh data for the active tab
    if (tabName === "products") {
      loadProductsData(true);
    } else if (tabName === "logs") {
      loadInventoryData(true);
    }
  }

  /**
   * Update button texts based on active tab
   */
  function updateButtonTexts() {
    if (activeTab === "products") {
      // Update button texts for Products tab
      $("#filtersForm button[type='submit']")
        .html('<i class="icon-search"></i> Apply Product Filters')
        .attr(
          "title",
          "Apply filters to search products by name, reference, or stock range"
        );

      $("#clearFilters")
        .html('<i class="icon-clear"></i> Clear Product Filters')
        .attr("title", "Clear all product filter fields and show all products");

      $("#refreshData")
        .html('<i class="icon-refresh"></i> Refresh Products')
        .attr("title", "Reload product data from the server");

      // Cambiar texto del label de estadísticas
      $(".stat-label").first().text("Total Products");
    } else {
      // Update button texts for Logs tab
      $("#filtersForm button[type='submit']")
        .html('<i class="icon-search"></i> Apply Log Filters')
        .attr(
          "title",
          "Apply filters to search inventory logs by date range or content"
        );

      $("#clearFilters")
        .html('<i class="icon-clear"></i> Clear Log Filters')
        .attr(
          "title",
          "Clear all log filter fields and show all inventory logs"
        );

      $("#refreshData")
        .html('<i class="icon-refresh"></i> Refresh Logs')
        .attr("title", "Reload inventory log data from the server");

      // Cambiar texto del label de estadísticas
      $(".stat-label").first().text("Total Logs");
    }
  }

  /**
   * Load products data from API with filters and pagination
   */
  function loadProductsData(forceRefresh = false) {
    if (isLoading && !forceRefresh) {
      return Promise.resolve();
    }

    isLoading = true;
    showLoading(true);
    hideProductsError();

    // Clear cache if force refresh
    if (forceRefresh) {
      api.clearCache();
    }

    return api
      .getProducts(currentFilters, productsPage, config.UI.ITEMS_PER_PAGE)
      .then(function (response) {
        if (response.success) {
          productsData = response.data;
          updateProductsTable(response.data);
          updateProductsPagination(response.pagination);
          updateProductsStats(response.pagination);

          if (response.data.length === 0) {
            showProductsEmptyState();
          }

          showNotification("Products data loaded successfully", "success");
        } else {
          throw new Error(
            "API returned error: " + (response.message || "Unknown error")
          );
        }
      })
      .catch(function (error) {
        console.error("Error loading products:", error);
        const processedError = api.processError(error);
        showProductsError(processedError.message);
        showNotification(processedError.message, "error");

        // Update products count to show error state
        $("#productsCount").text("Error loading products");
      })
      .finally(function () {
        isLoading = false;
        showLoading(false);
      });
  }

  /**
   * Update products table
   */
  function updateProductsTable(data) {
    const tableBody = $("#productsTableBody");
    tableBody.empty();

    if (!data || data.length === 0) {
      tableBody.append(`
        <tr>
          <td colspan="6" class="text-center text-muted">
            No products found
          </td>
        </tr>
      `);
      return;
    }

    data.forEach(function (product) {
      const row = `
        <tr class="fade-in">
          <td>${product.id}</td>
          <td>
            <div class="text-bold">${escapeHtml(product.name)}</div>
          </td>
          <td>
            <div class="text-muted">${escapeHtml(
              product.reference || "N/A"
            )}</div>
          </td>
          <td class="text-right">
            <span class="text-bold">${product.current_stock.toLocaleString()}</span>
          </td>
          <td>
            <div class="text-small">${formatDate(product.updated_at)}</div>
          </td>
          <td class="text-center">
            <div class="action-buttons">
              <button class="btn btn-small btn-primary edit-btn" data-product-id="${
                product.id
              }" title="Edit Product">
                ✏️
              </button>
              <button class="btn btn-small btn-accent view-btn" data-product-id="${
                product.id
              }" title="View Details">
                👁️
              </button>
              <button class="btn btn-small btn-danger delete-btn" data-product-id="${
                product.id
              }" title="Delete Product">
                🗑️
              </button>
            </div>
          </td>
        </tr>
      `;

      tableBody.append(row);
    });
  }

  /**
   * Update products pagination
   */
  function updateProductsPagination(pagination) {
    if (!pagination) return;

    const paginationContainer = $("#productsPagination");
    paginationContainer.empty();

    productsTotalPages = pagination.last_page || 1;
    productsPage = pagination.current_page;

    // Build pagination HTML
    let paginationHtml = "";

    // Previous button
    paginationHtml += `
      <button class="pagination-btn" ${productsPage <= 1 ? "disabled" : ""}
              onclick="InventoryApp.goToProductsPage(${productsPage - 1})">
          ‹
      </button>
    `;

    // Page numbers
    const startPage = Math.max(1, productsPage - 2);
    const endPage = Math.min(productsTotalPages, productsPage + 2);

    if (startPage > 1) {
      paginationHtml += `<button class="pagination-btn" onclick="InventoryApp.goToProductsPage(1)">1</button>`;
      if (startPage > 2) {
        paginationHtml += `<span class="pagination-ellipsis">...</span>`;
      }
    }

    for (let i = startPage; i <= endPage; i++) {
      paginationHtml += `
        <button class="pagination-btn ${i === productsPage ? "active" : ""}"
                onclick="InventoryApp.goToProductsPage(${i})">
            ${i}
        </button>
      `;
    }

    if (endPage < productsTotalPages) {
      if (endPage < productsTotalPages - 1) {
        paginationHtml += `<span class="pagination-ellipsis">...</span>`;
      }
      paginationHtml += `<button class="pagination-btn" onclick="InventoryApp.goToProductsPage(${productsTotalPages})">${productsTotalPages}</button>`;
    }

    // Next button
    paginationHtml += `
      <button class="pagination-btn" ${
        productsPage >= productsTotalPages ? "disabled" : ""
      }
              onclick="InventoryApp.goToProductsPage(${productsPage + 1})">
          ›
      </button>
    `;

    // Pagination info
    paginationHtml += `
      <div class="pagination-info">
          Showing ${pagination.from || 0} to ${pagination.to || 0} of ${
      pagination.total || 0
    } products
      </div>
    `;

    paginationContainer.html(paginationHtml);
  }

  /**
   * Update products stats
   */
  function updateProductsStats(pagination) {
    const total = pagination.total || 0;
    const from = pagination.from || 0;
    const to = pagination.to || 0;

    if (total === 0) {
      $("#productsCount").text("No products found");
    } else {
      $("#productsCount").text(`Showing ${from} to ${to} of ${total} products`);
    }

    $("#totalLogs").text(total); // Update header stat
  }

  /**
   * Go to specific products page
   */
  function goToProductsPage(page) {
    if (page < 1 || page > productsTotalPages || page === productsPage) {
      return;
    }

    productsPage = page;
    loadProductsData();
  }

  /**
   * Show/hide products error
   */
  function showProductsError(message) {
    $("#productsErrorText").text(message);
    $("#productsErrorMessage").slideDown(300);
  }

  function hideProductsError() {
    $("#productsErrorMessage").slideUp(300);
  }

  /**
   * Show products empty state
   */
  function showProductsEmptyState() {
    const tableBody = $("#productsTableBody");
    tableBody.html(`
      <tr>
        <td colspan="6" class="text-center" style="padding: 3rem;">
          <div style="opacity: 0.6;">
            <div style="font-size: 3rem; margin-bottom: 1rem;">📦</div>
            <div class="text-bold" style="margin-bottom: 0.5rem;">No products found</div>
            <div class="text-muted">Create your first product to get started</div>
          </div>
        </td>
      </tr>
    `);
  }

  /**
   * Load product data for editing
   */
  function loadProductForEdit(productId) {
    console.log("Loading product for edit:", productId);

    if (!productId) {
      showNotification("Invalid product ID", "error");
      return;
    }

    showLoading(true);

    api
      .getProduct(productId)
      .then(function (response) {
        console.log("Product data received:", response);
        if (response.success && response.data) {
          const product = response.data;
          populateEditForm(product);
          $("#editModalTitle").text("Edit Product");
          $("#saveButtonText").text("Save Changes");
          $("#editProductModal").fadeIn(300);
        } else {
          showNotification("Failed to load product data", "error");
        }
      })
      .catch(function (error) {
        console.error("Error loading product:", error);
        const processedError = api.processError(error);
        showNotification(processedError.message, "error");
      })
      .finally(function () {
        showLoading(false);
      });
  }

  /**
   * Open modal for creating new product
   */
  function openCreateProductModal() {
    clearEditForm();
    $("#editModalTitle").text("Create New Product");
    $("#saveButtonText").text("Create Product");
    $("#editProductModal").fadeIn(300);
  }

  /**
   * Populate edit form with product data
   */
  function populateEditForm(product) {
    console.log("=== POPULATE FORM DEBUG ===");
    console.log("Product data received:", product);
    console.log("Product ID:", product.id);
    console.log("Setting hidden field value to:", product.id);

    $("#editProductId").val(product.id);
    $("#editProductName").val(product.name);
    $("#editProductReference").val(product.reference || "");
    $("#editProductStock").val(product.current_stock || product.stock || 0);

    // Verify the value was set correctly
    const setId = $("#editProductId").val();
    console.log("Hidden field value after setting:", setId);
    console.log("Hidden field element:", $("#editProductId")[0]);
  }

  /**
   * Clear edit form
   */
  function clearEditForm() {
    console.log("=== CLEARING FORM ===");
    $("#editProductForm")[0].reset();
    $("#editProductId").val("");
    console.log("Form cleared. Hidden field value:", $("#editProductId").val());
  }

  /**
   * Handle form submission for create/edit product
   */
  function handleProductFormSubmit(event) {
    event.preventDefault();

    const productId = $("#editProductId").val();
    const formData = {
      name: $("#editProductName").val().trim(),
      reference: $("#editProductReference").val().trim(),
      current_stock: parseInt($("#editProductStock").val()) || 0,
    };

    // Validation
    if (!formData.name) {
      showNotification("Product name is required", "error");
      return;
    }

    const isEdit =
      productId !== "" && productId !== null && productId !== undefined;
    console.log("=== FORM SUBMISSION DEBUG ===");
    console.log("Product ID from form:", productId);
    console.log("Is Edit Mode:", isEdit);
    console.log("Form Data:", formData);
    console.log("ProductId type:", typeof productId);
    console.log("ProductId length:", productId ? productId.length : 0);

    showLoading(true);

    const apiCall = isEdit
      ? api.updateProduct(productId, formData)
      : api.createProduct(formData);

    console.log("API Call selected:", isEdit ? "UPDATE" : "CREATE");

    apiCall
      .then(function (response) {
        console.log("API response:", response);
        if (response.success) {
          showNotification(
            isEdit
              ? "Product updated successfully"
              : "Product created successfully",
            "success"
          );
          $("#editProductModal").fadeOut(300);

          // Refresh both tabs
          loadProductsData(true);
          loadInventoryData(true);
        } else {
          console.error("API returned error:", response);
          showNotification(response.message || "Operation failed", "error");
          if (response.errors) {
            console.error("Validation errors:", response.errors);
          }
        }
      })
      .catch(function (error) {
        console.error("Error saving product:", error);
        const processedError = api.processError(error);
        showNotification(processedError.message, "error");
      })
      .finally(function () {
        showLoading(false);
      });
  }

  /**
   * Delete product by ID
   */
  function deleteProductById(productId) {
    if (
      !confirm(
        "Are you sure you want to delete this product? This action cannot be undone."
      )
    ) {
      return;
    }

    showLoading(true);

    api
      .deleteProduct(productId)
      .then(function (response) {
        if (response.success) {
          showNotification("Product deleted successfully", "success");

          // Refresh both tabs
          loadProductsData(true);
          loadInventoryData(true);
        } else {
          showNotification(
            response.message || "Failed to delete product",
            "error"
          );
        }
      })
      .catch(function (error) {
        console.error("Error deleting product:", error);
        const processedError = api.processError(error);
        showNotification(processedError.message, "error");
      })
      .finally(function () {
        showLoading(false);
      });
  }

  function updateTableVisibility() {
    const width = $(window).width();
    const columns = $(".logs-table th, .logs-table td");

    if (width < 768) {
      // Mobile: hide some columns
      columns.filter(":nth-child(1), :nth-child(7)").hide();
    } else if (width < 1024) {
      // Tablet: show most columns
      columns.filter(":nth-child(1), :nth-child(7)").hide();
      columns.not(":nth-child(1), :nth-child(7)").show();
    } else {
      // Desktop: show all columns
      columns.show();
    }
  }

  function startAutoRefresh() {
    if (autoRefreshTimer) {
      clearInterval(autoRefreshTimer);
    }

    autoRefreshTimer = setInterval(() => {
      if (!isLoading && document.visibilityState === "visible") {
        loadInventoryData();
      }
    }, config.UI.AUTO_REFRESH_INTERVAL);
  }

  function stopAutoRefresh() {
    if (autoRefreshTimer) {
      clearInterval(autoRefreshTimer);
      autoRefreshTimer = null;
    }
  }

  // Public methods that need to be accessible from HTML
  return {
    init: init,
    goToPage: goToPage,
    loadInventoryData: loadInventoryData,
    applyFilters: applyFilters,
    clearFilters: clearFilters,

    // Placeholder methods for features to be implemented
    exportToCsv: function () {
      this.showNotification(
        "CSV export functionality will be implemented",
        "info"
      );
    },

    showStatistics: function () {
      this.showNotification("Statistics view will be implemented", "info");
    },

    testApiConnection: function () {
      const self = this;
      api
        .testConnection()
        .then(() =>
          self.showNotification("API connection test passed", "success")
        )
        .catch(() =>
          self.showNotification("API connection test failed", "error")
        );
    },

    toggleTableView: function () {
      this.showNotification(
        "Alternative view modes will be implemented",
        "info"
      );
    },

    viewProductDetails: function (productId) {
      this.showNotification(
        `Product details for ID ${productId} will be implemented`,
        "info"
      );
    },

    closeModals: function () {
      $(".modal").fadeOut(300);
    },

    closeEditModal: function () {
      $("#editProductModal").fadeOut(300);
    },

    editProduct: function (productId) {
      loadProductForEdit(productId);
    },

    createProduct: function () {
      openCreateProductModal();
    },

    deleteProduct: function (productId) {
      deleteProductById(productId);
    },

    // Tab management
    switchTab: function (tabName) {
      switchTab(tabName);
    },

    loadProductsData: function (forceRefresh = false) {
      return loadProductsData(forceRefresh);
    },

    // Refresh current active tab
    refreshCurrentTab: function () {
      if (activeTab === "products") {
        loadProductsData(true);
      } else {
        loadInventoryData(true);
      }
    },

    // Products pagination
    goToProductsPage: function (page) {
      goToProductsPage(page);
    },

    showNotification: function (message, type = "info") {
      console.log(`${type.toUpperCase()}: ${message}`);

      // Create visual notification
      const notification = $(`
        <div class="notification notification-${type}" style="
          position: fixed;
          top: 20px;
          right: 20px;
          z-index: 10001;
          padding: 15px 20px;
          border-radius: 5px;
          color: white;
          font-weight: 500;
          max-width: 300px;
          box-shadow: 0 4px 12px rgba(0,0,0,0.3);
          background: ${
            type === "error"
              ? "#dc2626"
              : type === "success"
              ? "#059669"
              : "#3b82f6"
          };
        ">${message}</div>
      `);

      $("body").append(notification);

      // Auto remove after 3 seconds
      setTimeout(() => {
        notification.fadeOut(300, () => notification.remove());
      }, 3000);
    },
  };
})(jQuery);

// Alias for shorter access
window.App = window.InventoryApp;
