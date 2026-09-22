import { Stack } from 'expo-router';
import { colors } from '../../../src/theme';

/**
 * Sin este layout propio, Expo Router no agrupa index/[id] bajo un único
 * nombre de ruta "work-orders" — quedaban sueltos y se filtraban como
 * íconos extra en la barra de tabs, aunque en (tabs)/_layout.tsx están
 * marcados con href: null. headerShown en false porque estas pantallas ya
 * traen su propio <PageHeader showBack /> (a diferencia de units/tires,
 * que usan el header nativo del Stack).
 */
export default function WorkOrdersLayout() {
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
