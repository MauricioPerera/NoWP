/**
 * HTTP Client with retry logic and exponential backoff
 */

import type { APIClientConfig, RequestConfig } from './types';

export class HTTPClient {
  private baseURL: string;
  private token?: string;
  private maxRetries: number;
  private retryDelay: number;

  constructor(config: APIClientConfig) {
    this.baseURL = config.baseURL.replace(/\/$/, '');
    this.token = config.token;
    this.maxRetries = config.maxRetries ?? 3;
    this.retryDelay = config.retryDelay ?? 1000;
  }

  setToken(token: string | undefined): void {
    this.token = token;
  }

  async request<T>(
    method: string,
    path: string,
    data?: any,
    config?: RequestConfig
  ): Promise<T> {
    const url = this.buildURL(path, config?.params);
    const headers = this.buildHeaders(config?.headers);

    let lastError: Error | null = null;

    for (let attempt = 0; attempt <= this.maxRetries; attempt++) {
      try {
        const response = await fetch(url, {
          method,
          headers,
          body: data ? JSON.stringify(data) : undefined,
          signal: config?.signal,
        });

        if (!response.ok) {
          const error = await this.handleErrorResponse(response);
          
          // Don't retry client errors (4xx)
          if (response.status >= 400 && response.status < 500) {
            throw error;
          }
          
          throw error;
        }

        return await response.json();
      } catch (error) {
        lastError = error as Error;

        // Don't retry if aborted or client error
        if (
          error instanceof Error &&
          (error.name === 'AbortError' || 
           (error as any).status >= 400 && (error as any).status < 500)
        ) {
          throw error;
        }

        // Last attempt, throw error
        if (attempt === this.maxRetries) {
          throw error;
        }

        // Wait with exponential backoff
        await this.sleep(this.retryDelay * Math.pow(2, attempt));
      }
    }

    throw lastError;
  }

  async get<T>(path: string, config?: RequestConfig): Promise<T> {
    return this.request<T>('GET', path, undefined, config);
  }

  async post<T>(path: string, data?: any, config?: RequestConfig): Promise<T> {
    return this.request<T>('POST', path, data, config);
  }

  async put<T>(path: string, data?: any, config?: RequestConfig): Promise<T> {
    return this.request<T>('PUT', path, data, config);
  }

  async delete<T>(path: string, config?: RequestConfig): Promise<T> {
    return this.request<T>('DELETE', path, undefined, config);
  }

  private buildURL(path: string, params?: Record<string, any>): string {
    const url = `${this.baseURL}${path}`;
    
    if (!params) {
      return url;
    }

    const searchParams = new URLSearchParams();
    Object.entries(params).forEach(([key, value]) => {
      if (value !== undefined && value !== null) {
        searchParams.append(key, String(value));
      }
    });

    const query = searchParams.toString();
    return query ? `${url}?${query}` : url;
  }

  private buildHeaders(customHeaders?: Record<string, string>): Record<string, string> {
    const headers: Record<string, string> = {
      'Content-Type': 'application/json',
      'Accept': 'application/json',
    };

    if (this.token) {
      headers['Authorization'] = `Bearer ${this.token}`;
    }

    return { ...headers, ...customHeaders };
  }

  private async handleErrorResponse(response: Response): Promise<Error> {
    let message = `HTTP ${response.status}: ${response.statusText}`;
    let errorData: any = null;

    try {
      errorData = await response.json();
      message = errorData.message || message;
    } catch {
      // Response is not JSON
    }

    const error = new Error(message) as any;
    error.status = response.status;
    error.data = errorData;
    
    return error;
  }

  private sleep(ms: number): Promise<void> {
    return new Promise(resolve => setTimeout(resolve, ms));
  }
}
