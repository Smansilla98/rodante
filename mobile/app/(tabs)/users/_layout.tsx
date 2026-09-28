import { Stack } from 'expo-router';
import { colors, type } from '../../../src/theme';

export default function UsersLayout() {
  return (
    <Stack
      screenOptions={{
        headerShown: true,
        headerStyle: { backgroundColor: colors.sidebar },
        headerTintColor: colors.ink,
        headerTitleStyle: { fontSize: type.subtitle, fontWeight: '700' },
        contentStyle: { backgroundColor: colors.page },
      }}
    >
      <Stack.Screen name="index" options={{ title: 'Usuarios' }} />
      <Stack.Screen name="form" options={{ title: 'Usuario' }} />
    </Stack>
  );
}
