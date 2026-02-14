/**
 * Users Module
 */

import type { HTTPClient } from '../http-client';
import type { User, PaginatedResponse, RequestConfig } from '../types';

export interface UserFilters {
  role?: 'admin' | 'editor' | 'author' | 'subscriber';
  search?: string;
  page?: number;
  perPage?: number;
}

export interface CreateUserData {
  email: string;
  password: string;
  name: string;
  role?: 'admin' | 'editor' | 'author' | 'subscriber';
}

export interface UpdateUserData {
  email?: string;
  password?: string;
  name?: string;
  role?: 'admin' | 'editor' | 'author' | 'subscriber';
}

export class UsersModule {
  constructor(private http: HTTPClient) {}

  async list(filters?: UserFilters, config?: RequestConfig): Promise<PaginatedResponse<User>> {
    return this.http.get<PaginatedResponse<User>>('/api/users', {
      ...config,
      params: filters,
    });
  }

  async get(id: number, config?: RequestConfig): Promise<User> {
    return this.http.get<User>(`/api/users/${id}`, config);
  }

  async create(data: CreateUserData, config?: RequestConfig): Promise<User> {
    return this.http.post<User>('/api/users', data, config);
  }

  async update(id: number, data: UpdateUserData, config?: RequestConfig): Promise<User> {
    return this.http.put<User>(`/api/users/${id}`, data, config);
  }

  async delete(id: number, config?: RequestConfig): Promise<void> {
    await this.http.delete(`/api/users/${id}`, config);
  }
}
