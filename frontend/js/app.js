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

    // Load saved filters
    loadSavedFilters();

    // Initial data load
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
      loadInventoryData(true);
    });

    // Quick actions
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

    // Modal close buttons
    $(".modal-close").on("click", function () {
      InventoryApp.closeModals();
    });
    $(".modal").on("click", function (e) {
      if (e.target === this) {
        closeModals();
      }
    });

    // Error retry button
    $("#retryLoad").on("click", function () {
      loadInventoryData(true);
    });

    // Table sorting
    $(".logs-table th.sortable").on("click", function () {
      const column = $(this).data("sort");
      sortTable(column);
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
      .getInventoryLogs(currentFilters, currentPage, 10)
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
      })
      .finally(function () {
        isLoading = false;
        showLoading(false);
      });
  }

  /**
   * Apply filters and reload data
   */
  function applyFilters() {
    // Collect filter values
    currentFilters = {
      product_id: $("#productId").val() || null,
      date_from: $("#dateFrom").val() || null,
      date_to: $("#dateTo").val() || null,
      user_source: $("#userSource").val() || null,
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

    // Load filtered data
    loadInventoryData(true);

    showNotification(config.MESSAGES.SUCCESS.FILTERS_APPLIED, "success");
  }

  /**
   * Clear all filters
   */
  function clearFilters() {
    $("#filtersForm")[0].reset();
    currentFilters = {};
    currentPage = 1;

    // Clear saved filters
    if (config.FILTERS.PERSISTENT_FILTERS) {
      localStorage.removeItem(config.STORAGE_KEYS.FILTERS);
    }

    loadInventoryData(true);
    showNotification("Filters cleared", "info");
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
                    <td>
                        <span class="badge badge-gray">${escapeHtml(
                          log.user_source
                        )}</span>
                    </td>
                    <td class="text-center">
                        <button class="btn btn-small" onclick="InventoryApp.viewProductDetails(${
                          log.product_id
                        })">
                            View
                        </button>
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

    totalPages = pagination.total;
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

    // Get today's changes (this would need additional API call in real implementation)
    $("#todayChanges").text("--");
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

    showNotification: function (message, type = "info") {
      console.log(`${type.toUpperCase()}: ${message}`);
      // Notification system will be enhanced in ui.js
    },
  };
})(jQuery);

// Alias for shorter access
window.App = window.InventoryApp;
