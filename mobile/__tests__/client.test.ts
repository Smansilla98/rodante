import { apiRequest, ApiError, setUnauthorizedHandler } from '../src/api/client';
import { clearToken, getToken, setToken } from '../src/auth/storage';

function jsonResponse(body: unknown, status = 200): Response {
  return {
    ok: status >= 200 && status < 300,
    status,
    json: async () => body,
  } as unknown as Response;
}

describe('apiRequest', () => {
  beforeEach(async () => {
    await clearToken();
    setUnauthorizedHandler(null);
    (global as unknown as { fetch: jest.Mock }).fetch = jest.fn();
  });

  it('does not send an Authorization header when there is no stored token', async () => {
    const fetchMock = global.fetch as unknown as jest.Mock;
    fetchMock.mockResolvedValueOnce(jsonResponse({ ok: true }));

    await apiRequest('/me');

    const [, options] = fetchMock.mock.calls[0];
    expect(options.headers.Authorization).toBeUndefined();
  });

  it('sends a Bearer token header when a token is stored', async () => {
    await setToken('abc123');
    const fetchMock = global.fetch as unknown as jest.Mock;
    fetchMock.mockResolvedValueOnce(jsonResponse({ ok: true }));

    await apiRequest('/me');

    const [url, options] = fetchMock.mock.calls[0];
    expect(url).toContain('/me');
    expect(options.headers.Authorization).toBe('Bearer abc123');
  });

  it('returns the parsed JSON body on success', async () => {
    const fetchMock = global.fetch as unknown as jest.Mock;
    fetchMock.mockResolvedValueOnce(jsonResponse({ id: 1, name: 'Operario' }));

    const result = await apiRequest<{ id: number; name: string }>('/me');

    expect(result).toEqual({ id: 1, name: 'Operario' });
  });

  it('throws ApiError with the server message and validation errors on 422', async () => {
    const fetchMock = global.fetch as unknown as jest.Mock;
    fetchMock.mockResolvedValueOnce(
      jsonResponse({ message: 'Datos inválidos.', errors: { odometer: ['El odómetro es requerido.'] } }, 422),
    );

    await expect(apiRequest('/tires/1/measurement', { method: 'POST' })).rejects.toMatchObject({
      message: 'Datos inválidos.',
      status: 422,
      errors: { odometer: ['El odómetro es requerido.'] },
    });
  });

  it('clears the token and notifies the unauthorized handler on 401', async () => {
    await setToken('expired-token');
    const fetchMock = global.fetch as unknown as jest.Mock;
    fetchMock.mockResolvedValueOnce(jsonResponse({ message: 'Unauthenticated.' }, 401));

    const onUnauthorized = jest.fn();
    setUnauthorizedHandler(onUnauthorized);

    await expect(apiRequest('/me')).rejects.toBeInstanceOf(ApiError);
    await expect(getToken()).resolves.toBeNull();
    expect(onUnauthorized).toHaveBeenCalledTimes(1);
  });

  it('wraps a network failure in an ApiError with code NETWORK', async () => {
    const fetchMock = global.fetch as unknown as jest.Mock;
    fetchMock.mockRejectedValueOnce(new Error('Failed to fetch'));

    const err = await apiRequest('/me').catch((e) => e);

    expect(err).toBeInstanceOf(ApiError);
    expect((err as ApiError).code).toBe('NETWORK');
  });
});
