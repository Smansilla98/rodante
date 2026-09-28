import { useCallback, useEffect, useState } from 'react';
import { Pressable, RefreshControl, ScrollView, StyleSheet, Text, View } from 'react-native';
import { useRouter, type Href } from 'expo-router';
import { api, ApiError } from '../../src/api/client';
import type { DashboardPayload } from '../../src/api/types';
import { colors, radius, space, touchTarget, type } from '../../src/theme';
import { PageHeader } from '../../src/ui/PageHeader';
import { ErrorState, LoadingState } from '../../src/ui/primitives';

const BUCKETS: Array<{ key: string; label: string; filter: string; source: 'condition' | 'status' | 'recap' }> = [
  { key: 'NUEVA', label: 'Nuevo', filter: 'NUEVA', source: 'condition' },
  { key: 'NUEVA_USADA', label: 'Nuevo usado', filter: 'NUEVA_USADA', source: 'condition' },
  { key: 'USADA', label: 'Usado', filter: 'USADA', source: 'condition' },
  { key: 'RECAPADA', label: 'Recapada', filter: 'RECAPADA', source: 'condition' },
  { key: 'EN_REPARACION', label: 'A reparar', filter: 'A_REPARAR', source: 'status' },
  { key: 'DE_BAJA', label: 'Baja', filter: 'BAJA', source: 'status' },
];

export default function StockScreen() {
  const router = useRouter();
  const [data, setData] = useState<DashboardPayload | null>(null);
  const [error, setError] = useState<string | null>(null);
  const [refreshing, setRefreshing] = useState(false);

  const load = useCallback(async () => {
    setError(null);
    try {
      setData(await api.dashboard());
    } catch (e) {
      setError(e instanceof ApiError ? e.message : 'No se pudo cargar el stock');
    }
  }, []);

  useEffect(() => {
    void load();
  }, [load]);

  const countFor = (bucket: (typeof BUCKETS)[number]) => {
    if (!data) return 0;
    if (bucket.source === 'condition') return data.tires_by_condition?.[bucket.key] ?? 0;
    return data.tires_by_status[bucket.key] ?? 0;
  };

  return (
    <View style={styles.root}>
      <PageHeader title="Stock" subtitle="Tocá un grupo para ver el listado" />
      <ScrollView
        contentContainerStyle={styles.scroll}
        refreshControl={
          <RefreshControl
            refreshing={refreshing}
            onRefresh={() => {
              setRefreshing(true);
              void load().finally(() => setRefreshing(false));
            }}
            tintColor={colors.primary}
          />
        }
      >
        {data === null && !error ? <LoadingState /> : null}
        {error ? <ErrorState message={error} onRetry={load} /> : null}
        {data
          ? BUCKETS.map((bucket) => (
              <Pressable
                key={bucket.key}
                style={({ pressed }) => [styles.row, pressed && styles.rowPressed]}
                onPress={() => router.push(`/(tabs)/tires?filter=${bucket.filter}` as Href)}
                accessibilityRole="button"
                accessibilityLabel={`${countFor(bucket)} ${bucket.label}`}
              >
                <Text style={styles.value}>{countFor(bucket)}</Text>
                <Text style={styles.label}>{bucket.label}</Text>
              </Pressable>
            ))
          : null}
        {data ? (
          <Pressable
            style={({ pressed }) => [styles.row, pressed && styles.rowPressed]}
            onPress={() => router.push('/(tabs)/work-orders' as Href)}
            accessibilityRole="button"
          >
            <Text style={styles.value}>{data.recap_in_shop ?? 0}</Text>
            <Text style={styles.label}>En recapado</Text>
          </Pressable>
        ) : null}
      </ScrollView>
    </View>
  );
}

const styles = StyleSheet.create({
  root: { flex: 1, backgroundColor: colors.page },
  scroll: { padding: space.lg, paddingTop: 0, gap: space.sm },
  row: {
    minHeight: touchTarget.row,
    backgroundColor: colors.card,
    borderRadius: radius.lg,
    borderWidth: 1,
    borderColor: colors.line,
    paddingHorizontal: space.lg,
    flexDirection: 'row',
    alignItems: 'center',
    gap: space.md,
  },
  rowPressed: { backgroundColor: colors.card2 },
  value: { color: colors.ink, fontSize: type.display, fontWeight: '800', minWidth: 56 },
  label: { color: colors.ink, fontSize: type.body, fontWeight: '700' },
});
