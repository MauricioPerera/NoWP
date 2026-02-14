/**
 * API Client Types
 */

export interface APIClientConfig {
  baseURL: string;
  token?: string;
  onTokenRefresh?: (token: string) => void;
  maxRetries?: number;
  retryDelay?: number;
}

export interface AuthTokens {
  token: string;
  expiresAt: number;
}

export interface User {
  id: number;
  email: string;
  name: string;
  role: 'admin' | 'editor' | 'author' | 'subscriber';
  createdAt: string;
  updatedAt: string;
}

export interface Content {
  id: number;
  title: string;
  slug: string;
  content: string;
  excerpt?: string;
  type: 'post' | 'page' | 'custom';
  status: 'draft' | 'published' | 'archived';
  authorId: number;
  locale?: string;
  customFields?: Record<string, any>;
  createdAt: string;
  updatedAt: string;
  publishedAt?: string;
}

export interface Media {
  id: number;
  filename: string;
  originalName: string;
  mimeType: string;
  size: number;
  path: string;
  url: string;
  thumbnails?: Record<string, string>;
  uploadedBy: number;
  createdAt: string;
}

export interface PaginatedResponse<T> {
  data: T[];
  meta: {
    total: number;
    page: number;
    perPage: number;
    lastPage: number;
  };
}

export interface APIError {
  message: string;
  code?: string;
  errors?: Record<string, string[]>;
}

export interface RequestConfig {
  headers?: Record<string, string>;
  params?: Record<string, any>;
  signal?: AbortSignal;
}
