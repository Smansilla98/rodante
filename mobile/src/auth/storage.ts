import * as SecureStore from 'expo-secure-store';
import AsyncStorage from '@react-native-async-storage/async-storage';

/**
 * Rodante usa un único token Sanctum (Bearer, 30 días) — no hay refresh token
 * en el backend (`POST /api/v1/auth/token`, ver TokenController). Guardamos
 * solo ese token + el usuario devuelto en el login.
 */
const TOKEN_KEY = 'rodante_token';
const USER_KEY = 'rodante_user';

async function getItem(key: string): Promise<string | null> {
  try {
    return await SecureStore.getItemAsync(key);
  } catch {
    return AsyncStorage.getItem(key);
  }
}

async function setItem(key: string, value: string): Promise<void> {
  try {
    await SecureStore.setItemAsync(key, value);
  } catch {
    await AsyncStorage.setItem(key, value);
  }
}

async function deleteItem(key: string): Promise<void> {
  try {
    await SecureStore.deleteItemAsync(key);
  } catch {
    await AsyncStorage.removeItem(key);
  }
}

export async function getToken(): Promise<string | null> {
  return getItem(TOKEN_KEY);
}

export async function setToken(token: string): Promise<void> {
  await setItem(TOKEN_KEY, token);
}

export async function getStoredUserRaw(): Promise<string | null> {
  return getItem(USER_KEY);
}

export async function setStoredUserRaw(json: string): Promise<void> {
  await setItem(USER_KEY, json);
}

export async function clearToken(): Promise<void> {
  await deleteItem(TOKEN_KEY);
  await deleteItem(USER_KEY);
}
