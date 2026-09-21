import { useCallback, useEffect, useState } from 'react';
import { FlatList, RefreshControl, StyleSheet, Text, View } from 'react-native';
import { api, ApiError } from '../../../src/api/client';
import type { InventorySession } from '../../../src/api/types';
import { colors, space, type } from '../../../src/theme';
import { PageHeader } from '../../../src/ui/PageHeader';
import { InventorySessionStatusBadge } from '../../../src/ui/StatusBadge';
import { Card, EmptyState, ErrorState, LoadingState } from '../../../src/ui/primitives';

export default function InventorySessionsScreen() {
  const [sessions, setSessions] = useState<InventorySession[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [refreshing, setRefreshing] = useState(false);

  const load = useCallback(async () => {
    setError(null);
    try {
      const res = await api.inventorySessions(1);
      setSessions(res.data);
    } catch (e) {
      setError(e instanceof ApiError ? e.message : 'No se pudieron cargar los inventarios');
    } finally {
      setLoading(false);
      setRefreshing(false);
    }
  }, []);

  useEffect(() => {
    void load();
  }, [load]);

  return (
    <View style={styles.root}>
      <PageHeader title="Inventarios" subtitle="Sesiones de conteo por base" showBack />
      {loading ? (
        <LoadingState />
      ) : error ? (
        <ErrorState message={error} onRetry={load} />
      ) : sessions.length === 0 ? (
        <EmptyState title="Sin sesiones de inventario" />
      ) : (
        <FlatList
          data={sessions}
          keyExtractor={(s) => String(s.id)}
          contentContainerStyle={{ padding: space.lg, paddingTop: 0, gap: space.sm }}
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
            <Card>
              <View style={styles.headRow}>
                <Text style={styles.rowTitle}>{item.number}</Text>
                <InventorySessionStatusBadge status={item.status} />
              </View>
              <Text style={styles.rowSub}>Base: {item.base?.name ?? '—'}</Text>
              <Text style={styles.rowSub}>
                Esperado {item.expected_count ?? '—'} · Encontrado {item.found_count ?? '—'} · Faltante{' '}
                {item.missing_count ?? '—'}
              </Text>
            </Card>
          )}
        />
      )}
    </View>
  );
}

const styles = StyleSheet.create({
  root: { flex: 1, backgroundColor: colors.page },
  headRow: { flexDirection: 'row', justifyContent: 'space-between', alignItems: 'center', gap: space.sm },
  rowTitle: { color: colors.ink, fontSize: type.bodyStrong, fontWeight: '700' },
  rowSub: { color: colors.muted, fontSize: type.caption, marginTop: 4 },
});
