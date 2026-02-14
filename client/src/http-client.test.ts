import { describe, it, expect, beforeEach, vi } from 'vitest';
import { HTTPClient } from './http-client';

describe('HTTPClient', () => {
  let client: HTTPClient;

  beforeEach(() => {
    client = new HTTPClient({
      baseURL: 'https://api.example.com',
      maxRetries: 2,
      retryDelay: 10,
    });
  });

  it('should build correct URL with params', async () => {
    global.fetch = vi.fn().mockResolvedValue({
      ok: true,
      json: async () => ({ data: 'test' }),
    });

    await client.get('/test', { params: { page: 1, limit: 10 } });

    expect(fetch).toHaveBeenCalledWith(
      'https://api.example.com/test?page=1&limit=10',
      expect.any(Object)
    );
  });

  it('should include authorization header when token is set', async () => {
    client.setToken('test-token');

    global.fetch = vi.fn().mockResolvedValue({
      ok: true,
      json: async () => ({ data: 'test' }),
    });

    await client.get('/test');

    expect(fetch).toHaveBeenCalledWith(
      expect.any(String),
      expect.objectContaining({
        headers: expect.objectContaining({
          Authorization: 'Bearer test-token',
        }),
      })
    );
  });

  it('should retry on server errors', async () => {
    let attempts = 0;
    global.fetch = vi.fn().mockImplementation(async () => {
      attempts++;
      if (attempts < 3) {
        return { ok: false, status: 500, statusText: 'Server Error' };
      }
      return { ok: true, json: async () => ({ data: 'success' }) };
    });

    const result = await client.get('/test');

    expect(attempts).toBe(3);
    expect(result).toEqual({ data: 'success' });
  });

  it('should not retry on client errors', async () => {
    let attempts = 0;
    global.fetch = vi.fn().mockImplementation(async () => {
      attempts++;
      return {
        ok: false,
        status: 404,
        statusText: 'Not Found',
        json: async () => ({ message: 'Not found' }),
      };
    });

    await expect(client.get('/test')).rejects.toThrow();
    expect(attempts).toBe(1);
  });

  it('should handle POST requests with data', async () => {
    global.fetch = vi.fn().mockResolvedValue({
      ok: true,
      json: async () => ({ id: 1 }),
    });

    const data = { title: 'Test' };
    await client.post('/test', data);

    expect(fetch).toHaveBeenCalledWith(
      expect.any(String),
      expect.objectContaining({
        method: 'POST',
        body: JSON.stringify(data),
      })
    );
  });
});
