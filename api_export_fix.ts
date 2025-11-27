// Export Analytics - Replace this method in your api.ts file

// Export Analytics
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

  // Separate method for CSV download
  private async downloadAnalyticsCSV(): Promise<{ success: boolean; filename: string }> {
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
