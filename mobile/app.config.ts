import { ExpoConfig, ConfigContext } from 'expo/config';

const APP_ENV = process.env.APP_ENV ?? 'development';

/**
 * URL de producción (Railway): https://rodant-production.up.railway.app
 * Override con EXPO_PUBLIC_API_URL si hace falta apuntar a otro entorno.
 */
const apiUrls: Record<string, string> = {
  development: process.env.EXPO_PUBLIC_API_URL ?? 'http://127.0.0.1:8000/api/v1',
  staging: process.env.EXPO_PUBLIC_API_URL ?? 'https://rodant-production.up.railway.app/api/v1',
  production:
    process.env.EXPO_PUBLIC_API_URL ?? 'https://rodant-production.up.railway.app/api/v1',
};

/**
 * TODO: no existe todavía un proyecto EAS real para Rodante.
 * Correr `eas init` (o `eas build:configure`) y setear EAS_PROJECT_ID (.env / EAS secret)
 * con el projectId generado. Hasta entonces queda vacío a propósito — nunca se
 * fabrica un UUID que parezca real.
 */
const easProjectId = (process.env.EAS_PROJECT_ID ?? '').trim();

export default ({ config }: ConfigContext): ExpoConfig => ({
  ...config,
  name: 'Rodante',
  slug: 'rodante',
  version: '1.0.0',
  orientation: 'portrait',
  scheme: 'rodante',
  userInterfaceStyle: 'dark',
  icon: './assets/icon.png',
  ios: {
    supportsTablet: true,
    bundleIdentifier: 'com.rodante.app',
    infoPlist: {
      UIBackgroundModes: ['remote-notification'],
      ITSAppUsesNonExemptEncryption: false,
    },
  },
  android: {
    package: 'com.rodante.app',
    softwareKeyboardLayoutMode: 'resize',
    adaptiveIcon: {
      foregroundImage: './assets/android-icon-foreground.png',
      backgroundColor: '#0f141c',
    },
  },
  plugins: [
    'expo-router',
    'expo-secure-store',
    'expo-font',
    [
      'expo-splash-screen',
      {
        backgroundColor: '#0f141c',
        image: './assets/splash-icon.png',
      },
    ],
    [
      'expo-notifications',
      {
        color: '#c8102e',
      },
    ],
  ],
  extra: {
    appEnv: APP_ENV,
    apiUrl: apiUrls[APP_ENV] ?? apiUrls.development,
    ...(easProjectId
      ? {
          eas: {
            projectId: easProjectId,
          },
        }
      : {}),
  },
  experiments: {
    typedRoutes: true,
  },
});
