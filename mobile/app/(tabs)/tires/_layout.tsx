import { Stack } from 'expo-router';
import { colors, type } from '../../../src/theme';

export default function TiresLayout() {
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
      <Stack.Screen name="index" options={{ title: 'Neumáticos' }} />
      <Stack.Screen name="new" options={{ title: 'Nuevo neumático' }} />
      <Stack.Screen name="edit" options={{ title: 'Modificar neumático' }} />
      <Stack.Screen name="[id]" options={{ title: 'Neumático' }} />
    </Stack>
  );
}
