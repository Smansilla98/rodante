import { useCallback, useEffect, useState } from 'react';
import { FlatList, Pressable, RefreshControl, StyleSheet, Text, View } from 'react-native';
import { useRouter, type Href } from 'expo-router';
import { api, ApiError } from '../../../src/api/client';
import type { DirectoryUser } from '../../../src/api/types';
import { roleLabel } from '../../../src/auth/permissions';
import { colors, radius, space, touchTarget, type } from '../../../src/theme';
import { EmptyState, ErrorState, LoadingState, PrimaryButton } from '../../../src/ui/primitives';

export default function UsersScreen() {
  const router = useRouter();
  const [users, setUsers] = useState<DirectoryUser[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [refreshing, setRefreshing] = useState(false);

  const load = useCallback(async () => {
    setError(null);
    try {
      setUsers(await api.users());
    } catch (e) {
      setError(e instanceof ApiError ? e.message : 'No se pudieron cargar los usuarios');
    } finally {
      setLoading(false);
      setRefreshing(false);
    }
  }, []);

  useEffect(() => {
    void load();
  }, [load]);

  if (loading) return <LoadingState />;
  if (error) return <ErrorState message={error} onRetry={load} />;

  return (
    <FlatList
      style={styles.root}
      data={users}
      keyExtractor={(item) => String(item.id)}
      contentContainerStyle={{ padding: space.lg, gap: space.sm }}
      refreshControl={
        <RefreshControl refreshing={refreshing} onRefresh={() => { setRefreshing(true); void load(); }} tintColor={colors.primary} />
      }
      ListHeaderComponent={
        <PrimaryButton title="Nuevo usuario" icon="person-add-outline" onPress={() => router.push('/(tabs)/users/form' as Href)} block />
      }
      ListEmptyComponent={<EmptyState title="Sin usuarios" />}
      renderItem={({ item }) => (
        <Pressable
          style={({ pressed }) => [styles.row, pressed && styles.pressed]}
          onPress={() => router.push(`/(tabs)/users/form?userId=${item.id}` as Href)}
          accessibilityRole="button"
          accessibilityLabel={`${item.name}, ${roleLabel(item.role)}`}
        >
          <View style={{ flex: 1 }}>
            <Text style={styles.name}>{item.name}</Text>
            <Text style={styles.meta}>
              {item.username} · {roleLabel(item.role)}
              {item.is_active ? '' : ' · inactivo'}
            </Text>
          </View>
        </Pressable>
      )}
    />
  );
}

const styles = StyleSheet.create({
  root: { flex: 1, backgroundColor: colors.page },
  row: {
    minHeight: touchTarget.row,
    backgroundColor: colors.card,
    borderRadius: radius.lg,
    borderWidth: 1,
    borderColor: colors.line,
    padding: space.md,
    justifyContent: 'center',
  },
  pressed: { backgroundColor: colors.card2 },
  name: { color: colors.ink, fontSize: type.bodyStrong, fontWeight: '700' },
  meta: { color: colors.muted, marginTop: 2 },
});
