import { clearToken, getToken, setStoredUserRaw, getStoredUserRaw, setToken } from '../src/auth/storage';

describe('auth storage', () => {
  afterEach(async () => {
    await clearToken();
  });

  it('returns null when no token is stored', async () => {
    await expect(getToken()).resolves.toBeNull();
  });

  it('round-trips a token through setToken/getToken', async () => {
    await setToken('my-bearer-token');
    await expect(getToken()).resolves.toBe('my-bearer-token');
  });

  it('clearToken removes both the token and the stored user', async () => {
    await setToken('my-bearer-token');
    await setStoredUserRaw(JSON.stringify({ id: 1 }));

    await clearToken();

    await expect(getToken()).resolves.toBeNull();
    await expect(getStoredUserRaw()).resolves.toBeNull();
  });
});
