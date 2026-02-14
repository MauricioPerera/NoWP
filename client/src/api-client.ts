/**
 * Main API Client
 */

import { HTTPClient } from './http-client';
import { AuthModule } from './modules/auth';
import { ContentModule } from './modules/content';
import { MediaModule } from './modules/media';
import { UsersModule } from './modules/users';
import type { APIClientConfig } from './types';

export class APIClient {
  private http: HTTPClient;
  
  public auth: AuthModule;
  public content: ContentModule;
  public media: MediaModule;
  public users: UsersModule;

  constructor(config: APIClientConfig) {
    this.http = new HTTPClient(config);
    
    this.auth = new AuthModule(this.http, config.onTokenRefresh);
    this.content = new ContentModule(this.http);
    this.media = new MediaModule(this.http);
    this.users = new UsersModule(this.http);
  }

  setToken(token: string | undefined): void {
    this.http.setToken(token);
  }

  getHTTPClient(): HTTPClient {
    return this.http;
  }
}
