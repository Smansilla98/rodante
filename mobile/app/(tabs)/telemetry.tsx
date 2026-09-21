import { useCallback, useEffect, useState } from 'react';
import { ScrollView, StyleSheet, Text, View } from 'react-native';
import type { TelemetryPayload } from '../../src/api/types';
import { api, ApiError } from '../../src/api/client';
import { useAuth } from '../../src/auth/AuthContext';
import { canViewTelemetry } from '../../src/auth/permissions';
import { colors, space, type } from '../../src/theme';
import { PageHeader } from '../../src/ui/PageHeader';
import { Card, EmptyState, ErrorState, LoadingState, SectionLabel, StatTile } from '../../src/ui/primitives';

export default function TelemetryScreen() {
  const { user } = useAuth();
  const [data, setData] = useState<TelemetryPayload | null>(null);
  const [error, setError] = useState<string | null>(null);
  const [loading, setLoading] = useState(true);

  const load = useCallback(async () => {
    setError(null);
    try {
      setData(await api.telemetry());
    } catch (e) {
      setError(e instanceof ApiError ? e.message : 'No se pudo cargar la telemetría');
    } finally {
      setLoading(false);
    }
  }, []);

  useEffect(() => {
    if (canViewTelemetry(user?.role)) void load();
    else setLoading(false);
  }, [load, user?.role]);

  if (!canViewTelemetry(user?.role)) {
    return (
      <View style={styles.root}>
        <PageHeader title="Telemetría" showBack />
        <EmptyState
          icon="lock-closed-outline"
          title="Sin acceso"
          hint="Tu rol no tiene permiso para ver telemetría (solo Administrador o Jefe de sector)."
        />
      </View>
    );
  }

  return (
    <View style={styles.root}>
      <PageHeader title="Telemetría" subtitle={`Últimos ${data?.days ?? '—'} días`} showBack />
      <ScrollView contentContainerStyle={{ padding: space.lg, gap: space.md }}>
        {loading ? (
          <LoadingState />
        ) : error ? (
          <ErrorState message={error} onRetry={load} />
        ) : data ? (
          <>
            <View style={styles.grid}>
              {Object.entries(data.totals ?? {}).map(([key, value]) => (
                <StatTile key={key} label={key.replace(/_/g, ' ')} value={value} />
              ))}
            </View>
            <Card>
              <SectionLabel>Fuentes</SectionLabel>
              {Object.entries(data.sources ?? {}).map(([key, value]) => (
                <Text key={key} style={styles.sourceRow}>
                  {key.replace(/_/g, ' ')}: {value}
                </Text>
              ))}
            </Card>
          </>
        ) : null}
      </ScrollView>
    </View>
  );
}

const styles = StyleSheet.create({
  root: { flex: 1, backgroundColor: colors.page },
  grid: { flexDirection: 'row', flexWrap: 'wrap', gap: space.md },
  sourceRow: { color: colors.muted, fontSize: type.body, marginTop: space.xs },
});
