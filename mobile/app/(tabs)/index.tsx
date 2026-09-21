import { useCallback, useEffect, useState } from 'react';
import { Pressable, RefreshControl, ScrollView, StyleSheet, Text, View } from 'react-native';
import { useRouter, type Href } from 'expo-router';
import { api, ApiError } from '../../src/api/client';
import { useAuth } from '../../src/auth/AuthContext';
import { canWrite, dashboardKpis, roleLabel } from '../../src/auth/permissions';
import { colors, radius, space, touchTarget, type } from '../../src/theme';
import { PageHeader } from '../../src/ui/PageHeader';
import { Card, ErrorState, Icon, LoadingState, SectionLabel, StatTile } from '../../src/ui/primitives';

const STATUS_BY_KPI: Record<string, string | null> = {
  total: null,
  stock: 'STOCK',
  installed: 'INSTALADA',
  reserve: 'RESERVA',
  spare: 'AUXILIO',
  repair: 'EN_REPARACION',
  retired: 'DE_BAJA',
};

const KPI_LABEL: Record<string, string> = {
  total: 'Total neumáticos',
  stock: 'En stock',
  installed: 'Instalados',
  reserve: 'En reserva',
  spare: 'Auxilio',
  repair: 'En reparación',
  retired: 'De baja',
  km: 'Km acumulados',
};

export default function DashboardScreen() {
  const { user } = useAuth();
  const router = useRouter();
  const [counts, setCounts] = useState<Record<string, number> | null>(null);
  const [error, setError] = useState<string | null>(null);
  const [refreshing, setRefreshing] = useState(false);

  const kpis = dashboardKpis(user?.role).filter((k) => k !== 'km');
  const writeAllowed = canWrite(user?.role);

  const load = useCallback(async () => {
    setError(null);
    try {
      const entries = await Promise.all(
        kpis.map(async (kpi) => {
          const status = STATUS_BY_KPI[kpi];
          const res = await api.tires(status ? { status, page: 1 } : { page: 1 });
          return [kpi, res.meta.total] as const;
        }),
      );
      setCounts(Object.fromEntries(entries));
    } catch (e) {
      setError(e instanceof ApiError ? e.message : 'No se pudo cargar el resumen');
    }
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [user?.role]);

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
        {counts === null && !error ? (
          <LoadingState label="Cargando resumen…" />
        ) : error ? (
          <ErrorState message={error} onRetry={load} />
        ) : (
          <View style={styles.grid}>
            {kpis.map((kpi) => (
              <StatTile key={kpi} label={KPI_LABEL[kpi] ?? kpi} value={counts?.[kpi] ?? 0} />
            ))}
          </View>
        )}

        <Card style={{ marginTop: space.lg }}>
          <SectionLabel>Accesos rápidos</SectionLabel>
          <View style={{ gap: space.sm }}>
            {writeAllowed ? (
              <QuickLink
                icon="add-circle-outline"
                label="Nuevo neumático"
                onPress={() => router.push('/(tabs)/tires/new')}
              />
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
        </Card>
      </ScrollView>
    </View>
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
  scroll: { padding: space.lg, paddingTop: 0 },
  grid: { flexDirection: 'row', flexWrap: 'wrap', gap: space.md },
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
