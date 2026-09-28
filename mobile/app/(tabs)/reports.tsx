import { useCallback, useEffect, useState } from 'react';
import { RefreshControl, ScrollView, StyleSheet, Text, View } from 'react-native';
import { api, ApiError } from '../../src/api/client';
import type { ReportsSummaryPayload } from '../../src/api/types';
import { colors, space, type } from '../../src/theme';
import { PageHeader } from '../../src/ui/PageHeader';
import { Card, EmptyState, ErrorState, LoadingState } from '../../src/ui/primitives';

export default function ReportsScreen() {
  const [data, setData] = useState<ReportsSummaryPayload | null>(null);
  const [error, setError] = useState<string | null>(null);
  const [refreshing, setRefreshing] = useState(false);

  const load = useCallback(async () => {
    setError(null);
    try {
      setData(await api.reportsSummary());
    } catch (e) {
      setError(e instanceof ApiError ? e.message : 'No se pudo cargar el reporte');
    }
  }, []);

  useEffect(() => {
    void load();
  }, [load]);

  return (
    <View style={styles.root}>
      <PageHeader title="Costos por unidad" subtitle="Calculado en el servidor" />
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
        {data && data.cost_by_unit.length === 0 ? (
          <EmptyState title="Sin costos asentados" hint="Cuando haya compras o trabajos con costo, aparecen acá." />
        ) : null}
        {data?.cost_by_unit.map((row) => (
          <Card key={row.fleet_unit_id}>
            <Text style={styles.plate}>{row.plate}</Text>
            <Text style={styles.amount}>
              {row.total_amount.toLocaleString('es-AR', { style: 'currency', currency: 'ARS' })}
            </Text>
            <Text style={styles.meta}>
              {row.tire_count} neumáticos · {row.entries_count} movimientos de costo
            </Text>
          </Card>
        ))}
      </ScrollView>
    </View>
  );
}

const styles = StyleSheet.create({
  root: { flex: 1, backgroundColor: colors.page },
  scroll: { padding: space.lg, paddingTop: 0, gap: space.sm },
  plate: { color: colors.ink, fontSize: type.bodyStrong, fontWeight: '800' },
  amount: { color: colors.ink, fontSize: type.title, fontWeight: '700', marginTop: space.xs },
  meta: { color: colors.muted, marginTop: space.xs },
});
