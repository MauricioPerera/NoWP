/**
 * Content Module
 */

import type { HTTPClient } from '../http-client';
import type { Content, PaginatedResponse, RequestConfig } from '../types';

export interface ContentFilters {
  type?: 'post' | 'page' | 'custom';
  status?: 'draft' | 'published' | 'archived';
  authorId?: number;
  locale?: string;
  search?: string;
  page?: number;
  perPage?: number;
}

export interface CreateContentData {
  title: string;
  slug?: string;
  content: string;
  excerpt?: string;
  type?: 'post' | 'page' | 'custom';
  status?: 'draft' | 'published' | 'archived';
  locale?: string;
  customFields?: Record<string, any>;
}

export interface UpdateContentData extends Partial<CreateContentData> {}

export class ContentModule {
  constructor(private http: HTTPClient) {}

  async list(filters?: ContentFilters, config?: RequestConfig): Promise<PaginatedResponse<Content>> {
    return this.http.get<PaginatedResponse<Content>>('/api/contents', {
      ...config,
      params: filters,
    });
  }

  async get(id: number, config?: RequestConfig): Promise<Content> {
    return this.http.get<Content>(`/api/contents/${id}`, config);
  }

  async getBySlug(slug: string, config?: RequestConfig): Promise<Content> {
    return this.http.get<Content>(`/api/contents/slug/${slug}`, config);
  }

  async create(data: CreateContentData, config?: RequestConfig): Promise<Content> {
    return this.http.post<Content>('/api/contents', data, config);
  }

  async update(id: number, data: UpdateContentData, config?: RequestConfig): Promise<Content> {
    return this.http.put<Content>(`/api/contents/${id}`, data, config);
  }

  async delete(id: number, config?: RequestConfig): Promise<void> {
    await this.http.delete(`/api/contents/${id}`, config);
  }

  async getVersions(id: number, config?: RequestConfig): Promise<any[]> {
    return this.http.get<any[]>(`/api/contents/${id}/versions`, config);
  }

  async restore(id: number, versionId: number, config?: RequestConfig): Promise<Content> {
    return this.http.post<Content>(`/api/contents/${id}/restore/${versionId}`, undefined, config);
  }
}
