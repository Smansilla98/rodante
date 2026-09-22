import { Stack } from 'expo-router';
import { colors } from '../../../src/theme';

/**
 * Ver work-orders/_layout.tsx — mismo motivo: sin este layout propio,
 * index/[id] no quedaban agrupados bajo "inventory-sessions" y Expo Router
 * los mostraba sueltos en la barra de tabs pese a href: null en
 * (tabs)/_layout.tsx. headerShown en false porque ya usan su propio
 * <PageHeader showBack />.
 */
export default function InventorySessionsLayout() {
  return (
    <Stack
      screenOptions={{
        headerShown: false,
        contentStyle: { backgroundColor: colors.page },
      }}
    >
      <Stack.Screen name="index" />
      <Stack.Screen name="[id]" />
    </Stack>
  );
}
