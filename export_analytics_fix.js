// Simple JavaScript solution for exportAnalytics fix
// Add this to your existing ApiService class in api.ts

// Replace the existing exportAnalytics method with this:
async exportAnalytics(format = "csv") {
  if (format === "csv") {
    // Special handling for CSV download
    return this.downloadAnalyticsCSV();
  } else {
    // Regular JSON response
    return this.request(
      `/api/supplier/analytics/export?format=${format}`,
      { method: "GET" },
      true
    );
  }
}

// Add this private method to your ApiService class:
async downloadAnalyticsCSV() {
  const token = localStorage.getItem("supplier_token");
  const tokenType = localStorage.getItem("token_type") || "Bearer";

  if (!token) throw new Error("No auth token found");

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

  // Get filename from Content-Disposition header
  const contentDisposition = response.headers.get('Content-Disposition');
  let filename = 'analytics_export.csv';
  if (contentDisposition) {
    const filenameMatch = contentDisposition.match(/filename="(.+)"/);
    if (filenameMatch) {
      filename = filenameMatch[1];
    }
  }

  // Convert response to blob
  const blob = await response.blob();
  
  // Create download link
  const url = window.URL.createObjectURL(blob);
  const a = document.createElement('a');
  a.href = url;
  a.download = filename;
  document.body.appendChild(a);
  a.click();
  
  // Clean up
  window.URL.revokeObjectURL(url);
  document.body.removeChild(a);
  
  console.log('CSV downloaded successfully:', filename);
  return { success: true, filename };
}

// Alternative: Standalone function (if you don't want to modify the class)
export async function downloadAnalyticsCSVStandalone() {
  const API_BASE_URL = "http://localhost:8000";
  const token = localStorage.getItem("supplier_token");
  const tokenType = localStorage.getItem("token_type") || "Bearer";

  if (!token) throw new Error("No auth token found");

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

  // Get filename from Content-Disposition header
  const contentDisposition = response.headers.get('Content-Disposition');
  let filename = 'analytics_export.csv';
  if (contentDisposition) {
    const filenameMatch = contentDisposition.match(/filename="(.+)"/);
    if (filenameMatch) {
      filename = filenameMatch[1];
    }
  }

  // Convert response to blob
  const blob = await response.blob();
  
  // Create download link
  const url = window.URL.createObjectURL(blob);
  const a = document.createElement('a');
  a.href = url;
  a.download = filename;
  document.body.appendChild(a);
  a.click();
  
  // Clean up
  window.URL.revokeObjectURL(url);
  document.body.removeChild(a);
  
  console.log('CSV downloaded successfully:', filename);
  return { success: true, filename };
}

// Usage examples:
/*
// In your component (using class method):
const apiService = new ApiService();
const result = await apiService.exportAnalytics("csv");
console.log(result); // { success: true, filename: "analytics_export_2025-11-27_08-44-59.csv" }

// Using standalone function:
import { downloadAnalyticsCSVStandalone } from './your-file';
const result = await downloadAnalyticsCSVStandalone();
console.log(result); // { success: true, filename: "analytics_export_2025-11-27_08-44-59.csv" }

// JSON still works:
const jsonData = await apiService.exportAnalytics("json");
console.log(jsonData); // { profile_completion: 100, response_rate: 100, ... }
*/
