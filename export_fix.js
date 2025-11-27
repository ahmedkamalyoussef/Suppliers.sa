// Simple JavaScript solution for exportAnalytics fix
// Copy this code directly into your existing ApiService class

// Replace the existing exportAnalytics method:
async exportAnalytics(format = "csv") {
  if (format === "csv") {
    return this.downloadAnalyticsCSV();
  } else {
    return this.request(
      "/api/supplier/analytics/export?format=" + format,
      { method: "GET" },
      true
    );
  }
}

// Add this private method to your ApiService class:
async downloadAnalyticsCSV() {
  var token = localStorage.getItem("supplier_token");
  var tokenType = localStorage.getItem("token_type") || "Bearer";

  if (!token) {
    throw new Error("No auth token found");
  }

  var response = await fetch(this.baseURL + "/api/supplier/analytics/export?format=csv", {
    method: "GET",
    headers: {
      "Authorization": tokenType + " " + token,
      "Accept": "text/csv"
    },
    credentials: "include"
  });

  if (!response.ok) {
    var errorData = await response.json().catch(function() { return {}; });
    throw new Error(errorData.message || "Failed to download CSV");
  }

  var contentDisposition = response.headers.get("Content-Disposition");
  var filename = "analytics_export.csv";
  if (contentDisposition) {
    var filenameMatch = contentDisposition.match(/filename="(.+)"/);
    if (filenameMatch && filenameMatch[1]) {
      filename = filenameMatch[1];
    }
  }

  var blob = await response.blob();
  var url = window.URL.createObjectURL(blob);
  var a = document.createElement("a");
  a.href = url;
  a.download = filename;
  document.body.appendChild(a);
  a.click();
  window.URL.revokeObjectURL(url);
  document.body.removeChild(a);
  
  return { success: true, filename: filename };
}

// Usage example:
/*
const apiService = new ApiService();

// CSV download:
const csvResult = await apiService.exportAnalytics("csv");
console.log(csvResult); // { success: true, filename: "analytics_export_2025-11-27_08-44-59.csv" }

// JSON response:
const jsonData = await apiService.exportAnalytics("json");
console.log(jsonData); // { profile_completion: 100, response_rate: 100, ... }
*/
