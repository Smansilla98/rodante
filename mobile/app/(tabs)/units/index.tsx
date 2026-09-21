import { useCallback, useEffect, useState } from 'react';
import { FlatList, Pressable, RefreshControl, StyleSheet, Text, View } from 'react-native';
import { useRouter } from 'expo-router';
import { api, ApiError } from '../../../src/api/client';
import type { FleetUnit } from '../../../src/api/types';
import { colors, radius, space, touchTarget, type } from '../../../src/theme';
import { UnitStatusBadge } from '../../../src/ui/StatusBadge';
import { EmptyState, ErrorState, LoadingState } from '../../../src/ui/primitives';

export default function UnitsListScreen() {
  const router = useRouter();
  const [units, setUnits] = useState<FleetUnit[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [refreshing, setRefreshing] = useState(false);

  const load = useCallback(async () => {
    setError(null);
    try {
      setUnits(await api.units());
    } catch (e) {
      setError(e instanceof ApiError ? e.message : 'No se pudieron cargar las unidades');
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
  if (units.length === 0) return <EmptyState title="Sin unidades" />;

  return (
    <FlatList
      style={styles.root}
      data={units}
      keyExtractor={(u) => String(u.id)}
      contentContainerStyle={{ padding: space.lg, gap: space.sm }}
      refreshControl={
        <RefreshControl
          refreshing={refreshing}
          onRefresh={() => {
            setRefreshing(true);
            void load();
          }}
          tintColor={colors.primary}
        />
      }
      renderItem={({ item }) => (
        <Pressable
          style={({ pressed }) => [styles.row, pressed && styles.rowPressed]}
          onPress={() => router.push(`/(tabs)/units/${item.id}`)}
          accessibilityRole="button"
          accessibilityLabel={`Unidad ${item.plate}, ${item.type?.name ?? 'sin tipo'}${
            item.base?.name ? `, base ${item.base.name}` : ''
          }`}
          accessibilityHint="Abre el detalle de la unidad"
        >
          <View style={{ flex: 1 }}>
            <Text style={styles.rowTitle}>{item.plate}</Text>
            <Text style={styles.rowSub}>
              {item.type?.name ?? 'Unidad'}
              {item.base?.name ? ` · ${item.base.name}` : ''}
            </Text>
            {item.type?.has_odometer ? (
              <Text style={styles.rowMeta}>{item.current_odometer.toLocaleString()} km</Text>
            ) : null}
          </View>
          <UnitStatusBadge status={item.status} />
        </Pressable>
      )}
    />
  );
}

const styles = StyleSheet.create({
  root: { flex: 1, backgroundColor: colors.page },
  row: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
    minHeight: touchTarget.row,
    backgroundColor: colors.card,
    borderRadius: radius.lg,
    borderWidth: 1,
    borderColor: colors.line,
    padding: space.md,
    gap: space.sm,
  },
  rowPressed: { backgroundColor: colors.card2 },
  rowTitle: { color: colors.ink, fontSize: type.bodyStrong, fontWeight: '700' },
  rowSub: { color: colors.muted, fontSize: type.caption, marginTop: 4 },
  rowMeta: { color: colors.inkSoft, fontSize: type.caption, marginTop: 2 },
});
