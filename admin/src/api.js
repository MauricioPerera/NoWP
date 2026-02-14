/**
 * API Client for Admin Panel
 */

class API {
  constructor() {
    this.baseURL = '/api';
    this.token = localStorage.getItem('token');
  }

  setToken(token) {
    this.token = token;
    if (token) {
      localStorage.setItem('token', token);
    } else {
      localStorage.removeItem('token');
    }
  }

  async request(method, path, data = null) {
    const headers = {
      'Content-Type': 'application/json',
      'Accept': 'application/json',
    };

    if (this.token) {
      headers['Authorization'] = `Bearer ${this.token}`;
    }

    const options = {
      method,
      headers,
    };

    if (data) {
      options.body = JSON.stringify(data);
    }

    const response = await fetch(`${this.baseURL}${path}`, options);

    if (!response.ok) {
      const error = await response.json().catch(() => ({}));
      throw new Error(error.message || `HTTP ${response.status}`);
    }

    return response.json();
  }

  // Auth
  async login(email, password) {
    const result = await this.request('POST', '/auth/login', { email, password });
    this.setToken(result.token);
    return result;
  }

  async logout() {
    await this.request('POST', '/auth/logout');
    this.setToken(null);
  }

  async me() {
    return this.request('GET', '/auth/me');
  }

  // Content
  async getContents(params = {}) {
    const query = new URLSearchParams(params).toString();
    return this.request('GET', `/contents${query ? '?' + query : ''}`);
  }

  async getContent(id) {
    return this.request('GET', `/contents/${id}`);
  }

  async createContent(data) {
    return this.request('POST', '/contents', data);
  }

  async updateContent(id, data) {
    return this.request('PUT', `/contents/${id}`, data);
  }

  async deleteContent(id) {
    return this.request('DELETE', `/contents/${id}`);
  }

  // Media
  async getMedia(params = {}) {
    const query = new URLSearchParams(params).toString();
    return this.request('GET', `/media${query ? '?' + query : ''}`);
  }

  async uploadMedia(file) {
    const formData = new FormData();
    formData.append('file', file);

    const headers = {};
    if (this.token) {
      headers['Authorization'] = `Bearer ${this.token}`;
    }

    const response = await fetch(`${this.baseURL}/media/upload`, {
      method: 'POST',
      headers,
      body: formData,
    });

    if (!response.ok) {
      const error = await response.json().catch(() => ({}));
      throw new Error(error.message || 'Upload failed');
    }

    return response.json();
  }

  async deleteMedia(id) {
    return this.request('DELETE', `/media/${id}`);
  }

  // Users
  async getUsers(params = {}) {
    const query = new URLSearchParams(params).toString();
    return this.request('GET', `/users${query ? '?' + query : ''}`);
  }

  async getUser(id) {
    return this.request('GET', `/users/${id}`);
  }

  async createUser(data) {
    return this.request('POST', '/users', data);
  }

  async updateUser(id, data) {
    return this.request('PUT', `/users/${id}`, data);
  }

  async deleteUser(id) {
    return this.request('DELETE', `/users/${id}`);
  }
}

export const api = new API();
