// Frontend CSV Download Code - JavaScript version

// Method 1: If you can modify the this.request method
async function exportAnalytics(format = "csv") {
  if (format === "csv") {
    // Special handling for CSV download
    return downloadAnalyticsCSV();
  } else {
    // Regular JSON response
    return this.request(
      `/api/supplier/analytics/export?format=${format}`,
      { method: "GET" },
      true
    );
  }
}

// Method 2: Separate CSV download method
async function downloadAnalyticsCSV() {
  try {
    const response = await fetch('/api/supplier/analytics/export?format=csv', {
      method: 'GET',
      headers: {
        'Content-Type': 'application/json',
        'Authorization': 'Bearer ' + getAuthToken() // or wherever you get the token
      }
    });

    if (!response.ok) {
      throw new Error('Failed to download CSV');
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
    return Promise.resolve({ success: true, filename });
  } catch (error) {
    console.error('Error downloading CSV:', error);
    return Promise.reject(error);
  }
}

// Method 3: If you can't modify the this.request method, override it
async function exportAnalyticsWithCSV(format = "csv") {
  const url = `/api/supplier/analytics/export?format=${format}`;
  
  if (format === "csv") {
    // Direct fetch for CSV
    const response = await fetch(url, {
      method: 'GET',
      headers: {
        'Content-Type': 'application/json',
        'Authorization': 'Bearer ' + getAuthToken()
      }
    });

    if (!response.ok) {
      throw new Error('Failed to download CSV');
    }

    // Get filename
    const contentDisposition = response.headers.get('Content-Disposition');
    let filename = 'analytics_export.csv';
    if (contentDisposition) {
      const filenameMatch = contentDisposition.match(/filename="(.+)"/);
      if (filenameMatch) {
        filename = filenameMatch[1];
      }
    }

    // Download file
    const blob = await response.blob();
    const downloadUrl = window.URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = downloadUrl;
    a.download = filename;
    document.body.appendChild(a);
    a.click();
    window.URL.revokeObjectURL(downloadUrl);
    document.body.removeChild(a);
    
    return { success: true, filename };
  } else {
    // Use your existing this.request for JSON
    return this.request(url, { method: "GET" }, true);
  }
}

// Method 4: Simple wrapper around your existing method
async function exportAnalyticsWithCSVSupport(format = "csv") {
  if (format === "csv") {
    return downloadAnalyticsCSV();
  }
  return exportAnalytics(format);
}

// Helper function to get auth token (replace with your actual implementation)
function getAuthToken() {
  // Replace this with your actual token retrieval logic
  return localStorage.getItem('authToken') || sessionStorage.getItem('authToken') || 'your-token-here';
}

// Usage examples:
// 1. exportAnalytics("csv") - downloads CSV file
// 2. exportAnalytics("json") - returns JSON data
// 3. downloadAnalyticsCSV() - directly downloads CSV
// 4. exportAnalyticsWithCSV("csv") - wrapper method

// If you're using a class-based approach, here's the class version:
class AnalyticsService {
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

  async downloadAnalyticsCSV() {
    try {
      const response = await fetch('/api/supplier/analytics/export?format=csv', {
        method: 'GET',
        headers: {
          'Content-Type': 'application/json',
          'Authorization': 'Bearer ' + this.getAuthToken()
        }
      });

      if (!response.ok) {
        throw new Error('Failed to download CSV');
      }

      const contentDisposition = response.headers.get('Content-Disposition');
      let filename = 'analytics_export.csv';
      if (contentDisposition) {
        const filenameMatch = contentDisposition.match(/filename="(.+)"/);
        if (filenameMatch) {
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
    } catch (error) {
      console.error('Error downloading CSV:', error);
      throw error;
    }
  }

  getAuthToken() {
    return localStorage.getItem('authToken') || sessionStorage.getItem('authToken') || 'your-token-here';
  }

  // Your existing request method
  async request(url, options, authenticated = false) {
    // Your existing implementation
    const headers = {
      'Content-Type': 'application/json',
      ...options.headers
    };

    if (authenticated) {
      headers['Authorization'] = 'Bearer ' + this.getAuthToken();
    }

    const response = await fetch(url, {
      ...options,
      headers
    });

    if (!response.ok) {
      throw new Error(`HTTP error! status: ${response.status}`);
    }

    return response.json();
  }
}
