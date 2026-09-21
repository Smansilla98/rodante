import Constants from 'expo-constants';

type Extra = {
  apiUrl?: string;
  appEnv?: string;
};

const extra = (Constants.expoConfig?.extra ?? {}) as Extra;

/** Preferir siempre EXPO_PUBLIC_* del .env (Expo Go / Metro). Fallback: producción Railway. */
export const API_URL =
  process.env.EXPO_PUBLIC_API_URL ??
  extra.apiUrl ??
  'https://rodant-production.up.railway.app/api/v1';

export const APP_ENV = process.env.APP_ENV ?? extra.appEnv ?? 'development';

if (__DEV__) {
  // eslint-disable-next-line no-console
  console.log('[Rodante] API_URL =', API_URL);
}
