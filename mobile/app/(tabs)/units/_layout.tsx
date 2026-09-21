import { Stack } from 'expo-router';
import { colors, type } from '../../../src/theme';

export default function UnitsLayout() {
  return (
    <Stack
      screenOptions={{
        headerShown: true,
        headerStyle: { backgroundColor: colors.sidebar },
        headerTintColor: colors.ink,
        headerTitleStyle: { fontSize: type.subtitle, fontWeight: '700' },
        headerBackTitle: 'Volver',
        contentStyle: { backgroundColor: colors.page },
      }}
    >
      <Stack.Screen name="index" options={{ title: 'Unidades' }} />
      <Stack.Screen name="[id]" options={{ title: 'Unidad' }} />
    </Stack>
  );
}
