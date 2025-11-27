// Fixed export button - copy this directly into your React component

// Option 1: Simple version (recommended)
<button
  onClick={async () => {
    try {
      await apiService.exportAnalytics("csv");
      alert(t("dashboardAnalytics.exportSuccess"));
    } catch (error) {
      console.error("Export failed:", error);
      alert(t("dashboardAnalytics.exportError"));
    }
  }}
  className="px-4 py-2 border border-gray-300 text-gray-600 rounded-lg hover:bg-gray-50 font-medium text-sm whitespace-nowrap cursor-pointer flex items-center"
>
  <i className="ri-download-line mr-2"></i>
  {t("dashboardAnalytics.exportButton")}
</button>
