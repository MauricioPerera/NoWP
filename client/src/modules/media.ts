/**
 * Media Module
 */

import type { HTTPClient } from '../http-client';
import type { Media, PaginatedResponse, RequestConfig } from '../types';

export interface MediaFilters {
  mimeType?: string;
  uploadedBy?: number;
  page?: number;
  perPage?: number;
}

export class MediaModule {
  constructor(private http: HTTPClient) {}

  async list(filters?: MediaFilters, config?: RequestConfig): Promise<PaginatedResponse<Media>> {
    return this.http.get<PaginatedResponse<Media>>('/api/media', {
      ...config,
      params: filters,
    });
  }

  async get(id: number, config?: RequestConfig): Promise<Media> {
    return this.http.get<Media>(`/api/media/${id}`, config);
  }

  async upload(file: File | Blob, config?: RequestConfig): Promise<Media> {
    const formData = new FormData();
    formData.append('file', file);

    const url = `${(this.http as any).baseURL}/api/media/upload`;
    const headers: Record<string, string> = {};
    
    if ((this.http as any).token) {
      headers['Authorization'] = `Bearer ${(this.http as any).token}`;
    }

    const response = await fetch(url, {
      method: 'POST',
      headers,
      body: formData,
      signal: config?.signal,
    });

    if (!response.ok) {
      const error = await response.json().catch(() => ({}));
      throw new Error(error.message || `Upload failed: ${response.statusText}`);
    }

    return response.json();
  }

  async delete(id: number, config?: RequestConfig): Promise<void> {
    await this.http.delete(`/api/media/${id}`, config);
  }
}
