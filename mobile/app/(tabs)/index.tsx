import { useCallback, useEffect, useState } from 'react';
import { Pressable, RefreshControl, ScrollView, StyleSheet, Text, View } from 'react-native';
import { useRouter, type Href } from 'expo-router';
import { api, ApiError } from '../../src/api/client';
import type { DashboardPayload } from '../../src/api/types';
import { useAuth } from '../../src/auth/AuthContext';
import { canWrite, roleLabel } from '../../src/auth/permissions';
import { colors, radius, space, touchTarget, type } from '../../src/theme';
import { PageHeader } from '../../src/ui/PageHeader';
import { Card, ErrorState, Icon, LoadingState } from '../../src/ui/primitives';

/** Solo los 3 estados más relevantes para un vistazo rápido — el resto está a un toque en "Neumáticos". */
const HIGHLIGHT_STATUSES: Array<{ key: string; label: string }> = [
  { key: 'STOCK', label: 'En stock' },
  { key: 'INSTALADA', label: 'Instalados' },
  { key: 'DE_BAJA', label: 'De baja' },
];

export default function DashboardScreen() {
  const { user } = useAuth();
  const router = useRouter();
  const writeAllowed = canWrite(user?.role);
  const [data, setData] = useState<DashboardPayload | null>(null);
  const [error, setError] = useState<string | null>(null);
  const [refreshing, setRefreshing] = useState(false);

  const load = useCallback(async () => {
    setError(null);
    try {
      setData(await api.dashboard());
    } catch (e) {
      setError(e instanceof ApiError ? e.message : 'No se pudo cargar el resumen');
    }
  }, []);

  useEffect(() => {
    void load();
  }, [load]);

  const onRefresh = async () => {
    setRefreshing(true);
    await load();
    setRefreshing(false);
  };

  return (
    <View style={styles.root}>
      <PageHeader title={`Hola, ${user?.name?.split(' ')[0] ?? ''}`} subtitle={roleLabel(user?.role)} />
      <ScrollView
        contentContainerStyle={styles.scroll}
        refreshControl={<RefreshControl refreshing={refreshing} onRefresh={onRefresh} tintColor={colors.primary} />}
      >
        {data === null && !error ? (
          <LoadingState label="Cargando resumen…" />
        ) : error ? (
          <ErrorState message={error} onRetry={load} />
        ) : data ? (
          <>
            <Card style={styles.heroCard}>
              <Text style={styles.heroLabel}>Neumáticos</Text>
              <Text style={styles.heroValue}>{data.tires_total.toLocaleString()}</Text>
              <View style={styles.inlineStats}>
                {HIGHLIGHT_STATUSES.map((s, idx) => (
                  <View key={s.key} style={[styles.inlineStat, idx > 0 && styles.inlineStatBorder]}>
                    <Text style={styles.inlineLabel}>{s.label}</Text>
                    <Text style={styles.inlineValue}>{data.tires_by_status[s.key] ?? 0}</Text>
                  </View>
                ))}
              </View>
            </Card>

            <View style={styles.miniRow}>
              <MiniTile
                value={data.open_work_orders}
                label="OTs abiertas"
                onPress={() => router.push('/(tabs)/work-orders' as Href)}
              />
              <MiniTile
                value={data.units_total ?? 0}
                label="Unidades"
                onPress={() => router.push('/(tabs)/units' as Href)}
              />
            </View>
            <View style={styles.miniRow}>
              <MiniTile
                value={data.tires_by_status.EN_REPARACION ?? 0}
                label="A reparar"
                onPress={() => router.push('/(tabs)/stock' as Href)}
              />
              <MiniTile
                value={data.recap_in_shop ?? 0}
                label="En recapado"
                onPress={() => router.push('/(tabs)/work-orders' as Href)}
              />
            </View>
            <View style={styles.miniRow}>
              <MiniTile
                value={data.tires_by_status.STOCK ?? 0}
                label="En stock"
                onPress={() => router.push('/(tabs)/stock' as Href)}
              />
              <MiniTile
                value={data.open_inventory_sessions}
                label="Inventarios"
                onPress={() => router.push('/(tabs)/inventory-sessions' as Href)}
              />
            </View>
          </>
        ) : null}

        <Text style={styles.section}>Accesos rápidos</Text>
        <View style={{ gap: space.sm }}>
          <QuickLink icon="qr-code-outline" label="Escanear QR" onPress={() => router.push('/(tabs)/scan' as Href)} />
          {writeAllowed ? (
            <QuickLink icon="add-circle-outline" label="Nuevo neumático" onPress={() => router.push('/(tabs)/tires/new')} />
          ) : null}
          {writeAllowed ? (
            <QuickLink icon="bus-outline" label="Nueva unidad" onPress={() => router.push('/(tabs)/units/new' as Href)} />
          ) : null}
          <QuickLink icon="search-outline" label="Buscar neumático" onPress={() => router.push('/(tabs)/lookup')} />
          <QuickLink icon="ellipse-outline" label="Neumáticos" onPress={() => router.push('/(tabs)/tires')} />
          <QuickLink icon="bus-outline" label="Unidades" onPress={() => router.push('/(tabs)/units')} />
          <QuickLink
            icon="build-outline"
            label="Órdenes de trabajo"
            onPress={() => router.push('/(tabs)/work-orders' as Href)}
          />
          <QuickLink
            icon="clipboard-outline"
            label="Inventarios"
            onPress={() => router.push('/(tabs)/inventory-sessions' as Href)}
          />
        </View>
      </ScrollView>
    </View>
  );
}

function MiniTile({ value, label, onPress }: { value: number; label: string; onPress: () => void }) {
  return (
    <Pressable
      style={({ pressed }) => [styles.miniTile, pressed && styles.miniTilePressed]}
      onPress={onPress}
      accessibilityRole="button"
      accessibilityLabel={`${value} ${label}`}
    >
      <Text style={styles.miniValue}>{value}</Text>
      <Text style={styles.miniLabel}>{label}</Text>
    </Pressable>
  );
}

function QuickLink({
  icon,
  label,
  onPress,
}: {
  icon: keyof typeof import('@expo/vector-icons').Ionicons.glyphMap;
  label: string;
  onPress: () => void;
}) {
  return (
    <Pressable
      onPress={onPress}
      style={({ pressed }) => [styles.link, pressed && styles.linkPressed]}
      accessibilityRole="button"
      accessibilityLabel={label}
    >
      <Icon name={icon} size={22} color={colors.primary} />
      <Text style={styles.linkText}>{label}</Text>
      <Icon name="chevron-forward-outline" size={18} color={colors.muted} />
    </Pressable>
  );
}

const styles = StyleSheet.create({
  root: { flex: 1, backgroundColor: colors.page },
  scroll: { padding: space.lg, paddingTop: 0, gap: space.md },
  heroCard: { gap: space.xs },
  heroLabel: { color: colors.muted, fontSize: type.label, fontWeight: '600' },
  heroValue: { color: colors.ink, fontSize: 40, fontWeight: '800' },
  inlineStats: { flexDirection: 'row', marginTop: space.sm },
  inlineStat: { flex: 1, alignItems: 'center', paddingVertical: space.xs },
  inlineStatBorder: { borderLeftWidth: 1, borderLeftColor: colors.line },
  inlineLabel: { color: colors.muted, fontSize: type.caption },
  inlineValue: { color: colors.ink, fontSize: type.title, fontWeight: '700', marginTop: 2 },
  miniRow: { flexDirection: 'row', gap: space.sm },
  miniTile: {
    flex: 1,
    minHeight: touchTarget.row,
    backgroundColor: colors.card,
    borderRadius: radius.lg,
    borderWidth: 1,
    borderColor: colors.line,
    padding: space.md,
    justifyContent: 'center',
  },
  miniTilePressed: { backgroundColor: colors.card2 },
  miniValue: { color: colors.ink, fontSize: type.display, fontWeight: '800' },
  miniLabel: { color: colors.muted, fontSize: type.caption, marginTop: 2 },
  section: { color: colors.muted, fontSize: type.label, fontWeight: '700', marginBottom: space.xs },
  link: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: space.sm,
    minHeight: touchTarget.min,
    paddingHorizontal: space.sm,
    borderRadius: radius.md,
    backgroundColor: colors.card2,
  },
  linkPressed: { opacity: 0.7 },
  linkText: { flex: 1, color: colors.ink, fontSize: type.body, fontWeight: '600' },
});
