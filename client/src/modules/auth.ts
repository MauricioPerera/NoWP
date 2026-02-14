/**
 * Authentication Module
 */

import type { HTTPClient } from '../http-client';
import type { User, AuthTokens } from '../types';

export class AuthModule {
  constructor(
    private http: HTTPClient,
    private onTokenRefresh?: (token: string) => void
  ) {}

  async login(email: string, password: string): Promise<AuthTokens> {
    const response = await this.http.post<{ token: string; expiresIn: number }>(
      '/api/auth/login',
      { email, password }
    );

    const tokens: AuthTokens = {
      token: response.token,
      expiresAt: Date.now() + response.expiresIn * 1000,
    };

    this.http.setToken(tokens.token);
    this.onTokenRefresh?.(tokens.token);

    return tokens;
  }

  async register(email: string, password: string, name: string): Promise<User> {
    return this.http.post<User>('/api/auth/register', {
      email,
      password,
      name,
    });
  }

  async me(): Promise<User> {
    return this.http.get<User>('/api/auth/me');
  }

  async logout(): Promise<void> {
    await this.http.post('/api/auth/logout');
    this.http.setToken(undefined);
  }

  async refresh(token: string): Promise<AuthTokens> {
    const response = await this.http.post<{ token: string; expiresIn: number }>(
      '/api/auth/refresh',
      { token }
    );

    const tokens: AuthTokens = {
      token: response.token,
      expiresAt: Date.now() + response.expiresIn * 1000,
    };

    this.http.setToken(tokens.token);
    this.onTokenRefresh?.(tokens.token);

    return tokens;
  }
}
