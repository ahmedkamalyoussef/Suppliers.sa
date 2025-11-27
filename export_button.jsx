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

// Option 2: Manual fetch version
<button
  onClick={async () => {
    try {
      const token = localStorage.getItem("supplier_token");
      const tokenType = localStorage.getItem("token_type") || "Bearer";

      const response = await fetch("http://localhost:8000/api/supplier/analytics/export?format=csv", {
        method: "GET",
        headers: {
          "Authorization": tokenType + " " + token,
          "Accept": "text/csv"
        },
        credentials: "include"
      });

      if (!response.ok) {
        throw new Error("Failed to download CSV");
      }

      const contentDisposition = response.headers.get("Content-Disposition");
      let filename = "analytics_export.csv";
      if (contentDisposition) {
        const filenameMatch = contentDisposition.match(/filename="(.+)"/);
        if (filenameMatch && filenameMatch[1]) {
          filename = filenameMatch[1];
        }
      }

      const blob = await response.blob();
      const url = window.URL.createObjectURL(blob);
      const link = document.createElement("a");
      link.href = url;
      link.download = filename;
      
      document.body.appendChild(link);
      link.click();
      
      setTimeout(() => {
        document.body.removeChild(link);
        URL.revokeObjectURL(url);
      }, 100);

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
