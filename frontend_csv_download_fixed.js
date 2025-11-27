// Frontend CSV Download Code - Updated to work with your this.request pattern

// Method 1: If you can modify the this.request method
async exportAnalytics(format: "csv" | "json" = "csv"): Promise<any> {
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

// Method 2: Separate CSV download method
async downloadAnalyticsCSV(): Promise<void> {
  try {
    const response = await fetch('/api/supplier/analytics/export?format=csv', {
      method: 'GET',
      headers: {
        'Content-Type': 'application/json',
        'Authorization': 'Bearer ' + this.getAuthToken() // or wherever you get the token
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
    return Promise.resolve(); // Return void promise
  } catch (error) {
    console.error('Error downloading CSV:', error);
    return Promise.reject(error);
  }
}

// Method 3: If you can't modify the this.request method, override it
async exportAnalytics(format: "csv" | "json" = "csv"): Promise<any> {
  const url = `/api/supplier/analytics/export?format=${format}`;
  
  if (format === "csv") {
    // Direct fetch for CSV
    const response = await fetch(url, {
      method: 'GET',
      headers: {
        'Content-Type': 'application/json',
        'Authorization': 'Bearer ' + this.getAuthToken()
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
async exportAnalyticsWithCSVSupport(format: "csv" | "json" = "csv"): Promise<any> {
  if (format === "csv") {
    return this.downloadAnalyticsCSV();
  }
  return this.exportAnalytics(format);
}

// Usage examples:
// 1. exportAnalytics("csv") - downloads CSV file
// 2. exportAnalytics("json") - returns JSON data
// 3. downloadAnalyticsCSV() - directly downloads CSV
