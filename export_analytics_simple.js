// JavaScript solution for exportAnalytics fix
// This is the actual code to copy into your existing ApiService class

// STEP 1: Replace the existing exportAnalytics method with this:
async exportAnalytics(format = "csv") {
  if (format === "csv") {
    return this.downloadAnalyticsCSV();
  } else {
    return this.request(
      `/api/supplier/analytics/export?format=${format}`,
      { method: "GET" },
      true
    );
  }
}

// STEP 2: Add this private method to your ApiService class:
async downloadAnalyticsCSV() {
  const token = localStorage.getItem("supplier_token");
  const tokenType = localStorage.getItem("token_type") || "Bearer";

  if (!token) {
    throw new Error("No auth token found");
  }

  const response = await fetch(`${this.baseURL}/api/supplier/analytics/export?format=csv`, {
    method: "GET",
    headers: {
      "Authorization": `${tokenType} ${token}`,
      "Accept": "text/csv",
    },
    credentials: "include",
  });

  if (!response.ok) {
    const errorData = await response.json().catch(() => ({}));
    throw new Error(errorData.message || "Failed to download CSV");
  }

  const contentDisposition = response.headers.get('Content-Disposition');
  let filename = 'analytics_export.csv';
  if (contentDisposition) {
    const filenameMatch = contentDisposition.match(/filename="(.+)"/);
    if (filenameMatch && filenameMatch[1]) {
      filename = filenameMatch[1];
    }
  }

  const blob = await response.blob();
  const url = window.URL.createObjectURL(blob);
  const a = document.createElement('a');
  a.href = url;
  a.download = filename;
  document.body.appendChild(a);
  a.click();
  window.URL.revokeObjectURL(url);
  document.body.removeChild(a);
  
  return { success: true, filename };
}

// STEP 3: Usage examples (for reference):
/*
// In your component:
const apiService = new ApiService();

// CSV download:
const csvResult = await apiService.exportAnalytics("csv");
console.log(csvResult); // { success: true, filename: "analytics_export_2025-11-27_08-44-59.csv" }

// JSON response:
const jsonData = await apiService.exportAnalytics("json");
console.log(jsonData); // { profile_completion: 100, response_rate: 100, ... }
*/

// STEP 4: Alternative standalone function (if you don't want to modify the class):
/*
export async function downloadAnalyticsCSVStandalone() {
  const API_BASE_URL = "http://localhost:8000";
  const token = localStorage.getItem("supplier_token");
  const tokenType = localStorage.getItem("token_type") || "Bearer";

  if (!token) {
    throw new Error("No auth token found");
  }

  const response = await fetch(`${API_BASE_URL}/api/supplier/analytics/export?format=csv`, {
    method: "GET",
    headers: {
      "Authorization": `${tokenType} ${token}`,
      "Accept": "text/csv",
    },
    credentials: "include",
  });

  if (!response.ok) {
    const errorData = await response.json().catch(() => ({}));
    throw new Error(errorData.message || "Failed to download CSV");
  }

  const contentDisposition = response.headers.get('Content-Disposition');
  let filename = 'analytics_export.csv';
  if (contentDisposition) {
    const filenameMatch = contentDisposition.match(/filename="(.+)"/);
    if (filenameMatch && filenameMatch[1]) {
      filename = filenameMatch[1];
    }
  }

  const blob = await response.blob();
  const url = window.URL.createObjectURL(blob);
  const a = document.createElement('a');
  a.href = url;
  a.download = filename;
  document.body.appendChild(a);
  a.click();
  window.URL.revokeObjectURL(url);
  document.body.removeChild(a);
  
  return { success: true, filename };
}
*/
