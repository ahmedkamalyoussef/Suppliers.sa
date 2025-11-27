// Fixed export button code - copy this into your component

<button
  onClick={async () => {
    try {
      // For CSV, the backend handles the download automatically
      // So we just call the method and let it handle everything
      const result = await apiService.exportAnalytics("csv");
      
      // The downloadAnalyticsCSV method already handles the file download
      // So we just show success message
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

// Alternative: If you want to handle the download manually
<button
  onClick={async () => {
    try {
      // Get the token for authentication
      const token = localStorage.getItem("supplier_token");
      const tokenType = localStorage.getItem("token_type") || "Bearer";

      // Make direct fetch request
      const response = await fetch("http://localhost:8000/api/supplier/analytics/export?format=csv", {
        method: "GET",
        headers: {
          "Authorization": `${tokenType} ${token}`,
          "Accept": "text/csv",
        },
        credentials: "include",
      });

      if (!response.ok) {
        throw new Error("Failed to download CSV");
      }

      // Get filename from Content-Disposition header
      const contentDisposition = response.headers.get('Content-Disposition');
      let filename = 'analytics_export.csv';
      if (contentDisposition) {
        const filenameMatch = contentDisposition.match(/filename="(.+)"/);
        if (filenameMatch && filenameMatch[1]) {
          filename = filenameMatch[1];
        }
      }

      // Convert response to blob
      const blob = await response.blob();
      
      // Create download link
      const url = window.URL.createObjectURL(blob);
      const link = document.createElement("a");
      link.href = url;
      link.download = filename;
      
      // Trigger download
      document.body.appendChild(link);
      link.click();
      
      // Cleanup
      setTimeout(() => {
        document.body.removeChild(link);
        URL.revokeObjectURL(url);
      }, 100);

      // Show success message
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

// Third option: Simple version using the fixed apiService
<button
  onClick={async () => {
    try {
      // Just call the method - it handles everything
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
