import React, { createContext, useCallback, useContext, useEffect, useMemo, useState } from 'react';
import { api, ApiError, setUnauthorizedHandler } from '../api/client';
import type { ApiUser } from '../api/types';
import { clearToken, getToken, setToken } from './storage';

type AuthState = {
  user: ApiUser | null;
  loading: boolean;
  offlineHint: string | null;
  login: (username: string, password: string, companyId?: number) => Promise<void>;
  logout: () => Promise<void>;
  refreshMe: () => Promise<void>;
};

const AuthContext = createContext<AuthState | null>(null);

export function AuthProvider({ children }: { children: React.ReactNode }) {
  const [user, setUser] = useState<ApiUser | null>(null);
  const [loading, setLoading] = useState(true);
  const [offlineHint, setOfflineHint] = useState<string | null>(null);

  const refreshMe = useCallback(async () => {
    const token = await getToken();
    if (!token) {
      setUser(null);
      return;
    }
    const me = await api.me();
    setUser(me);
  }, []);

  // Rodante no tiene refresh token: un 401 en cualquier request limpia la
  // sesión local y vuelve a /login (ver src/api/client.ts).
  useEffect(() => {
    setUnauthorizedHandler(() => setUser(null));
    return () => setUnauthorizedHandler(null);
  }, []);

  useEffect(() => {
    let cancelled = false;
    const boot = async () => {
      try {
        await Promise.race([
          refreshMe(),
          new Promise<void>((resolve) => setTimeout(resolve, 8000)),
        ]);
      } catch {
        await clearToken();
        if (!cancelled) setUser(null);
      } finally {
        if (!cancelled) setLoading(false);
      }
    };
    void boot();
    return () => {
      cancelled = true;
    };
  }, [refreshMe]);

  const login = useCallback(async (username: string, password: string, companyId?: number) => {
    setOfflineHint(null);
    try {
      const payload = await api.login(username.trim(), password, companyId);
      await setToken(payload.token);
      setUser(payload.user);
    } catch (e) {
      if (e instanceof ApiError && e.code === 'NETWORK') {
        setOfflineHint(e.message);
      }
      throw e;
    }
  }, []);

  const logout = useCallback(async () => {
    try {
      await api.logout();
    } catch {
      // Si el logout server-side falla (offline, token ya vencido) igual
      // limpiamos la sesión local.
    } finally {
      await clearToken();
      setUser(null);
    }
  }, []);

  const value = useMemo(
    () => ({ user, loading, offlineHint, login, logout, refreshMe }),
    [user, loading, offlineHint, login, logout, refreshMe],
  );

  return <AuthContext.Provider value={value}>{children}</AuthContext.Provider>;
}

export function useAuth(): AuthState {
  const ctx = useContext(AuthContext);
  if (!ctx) throw new Error('useAuth fuera de AuthProvider');
  return ctx;
}
