import { useCallback, useEffect, useState } from 'react';
import { FlatList, Pressable, RefreshControl, ScrollView, StyleSheet, Text, View } from 'react-native';
import { useRouter } from 'expo-router';
import { api, ApiError } from '../../../src/api/client';
import type { Tire } from '../../../src/api/types';
import { useAuth } from '../../../src/auth/AuthContext';
import { canWrite } from '../../../src/auth/permissions';
import { colors, radius, space, touchTarget, type } from '../../../src/theme';
import { TireStatusBadge } from '../../../src/ui/StatusBadge';
import { Chip, EmptyState, ErrorState, LoadingState, PrimaryButton } from '../../../src/ui/primitives';

/** Dónde está la cubierta ahora mismo, en una línea — igual criterio en toda la app. */
function locationLabel(tire: Tire): string {
  const loc = tire.currentLocation;
  if (loc?.unit) {
    return `En unidad ${loc.unit.plate}${loc.position ? ` · Posición ${loc.position.code}` : ''}`;
  }
  if (loc?.base?.name) {
    return loc.base.name;
  }
  return 'Stock';
}

const STATUS_FILTERS: Array<{ key: string | null; label: string }> = [
  { key: null, label: 'Todas' },
  { key: 'STOCK', label: 'Stock' },
  { key: 'INSTALADA', label: 'Instalada' },
  { key: 'RESERVA', label: 'Reserva' },
  { key: 'AUXILIO', label: 'Auxilio' },
  { key: 'EN_REPARACION', label: 'En reparación' },
  { key: 'DE_BAJA', label: 'De baja' },
];

export default function TiresListScreen() {
  const router = useRouter();
  const { user } = useAuth();
  const writeAllowed = canWrite(user?.role);
  const [status, setStatus] = useState<string | null>(null);
  const [tires, setTires] = useState<Tire[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [refreshing, setRefreshing] = useState(false);

  const load = useCallback(async (s: string | null) => {
    setError(null);
    try {
      const res = await api.tires({ status: s ?? undefined, page: 1 });
      setTires(res.data);
    } catch (e) {
      setError(e instanceof ApiError ? e.message : 'No se pudieron cargar los neumáticos');
    } finally {
      setLoading(false);
      setRefreshing(false);
    }
  }, []);

  useEffect(() => {
    setLoading(true);
    void load(status);
  }, [status, load]);

  const onRefresh = () => {
    setRefreshing(true);
    void load(status);
  };

  return (
    <View style={styles.root}>
      {writeAllowed ? (
        <View style={styles.newButtonWrap}>
          <PrimaryButton
            title="Nuevo neumático"
            icon="add-circle-outline"
            onPress={() => router.push('/(tabs)/tires/new')}
            block
          />
        </View>
      ) : null}
      <ScrollView
        horizontal
        showsHorizontalScrollIndicator={false}
        style={styles.filters}
        contentContainerStyle={{ gap: space.sm, paddingHorizontal: space.lg }}
      >
        {STATUS_FILTERS.map((f) => (
          <Chip key={f.label} label={f.label} selected={status === f.key} onPress={() => setStatus(f.key)} />
        ))}
      </ScrollView>

      {loading ? (
        <LoadingState />
      ) : error ? (
        <ErrorState message={error} onRetry={() => load(status)} />
      ) : tires.length === 0 ? (
        <EmptyState title="Sin neumáticos" hint="No hay resultados para este filtro." />
      ) : (
        <FlatList
          data={tires}
          keyExtractor={(t) => String(t.id)}
          contentContainerStyle={{ padding: space.lg, gap: space.sm }}
          refreshControl={<RefreshControl refreshing={refreshing} onRefresh={onRefresh} tintColor={colors.primary} />}
          renderItem={({ item }) => (
            <Pressable
              style={({ pressed }) => [styles.row, pressed && styles.rowPressed]}
              onPress={() => router.push(`/(tabs)/tires/${item.id}`)}
              accessibilityRole="button"
              accessibilityLabel={`Neumático número ${item.individual_number}`}
              accessibilityHint="Abre el detalle del neumático"
            >
              <View style={{ flex: 1 }}>
                <Text style={styles.rowTitle}>
                  Nº{item.individual_number}
                  {item.brand?.name ? ` · ${item.brand.name}` : ''}
                </Text>
                <Text style={styles.rowSub}>
                  {[item.size?.code, item.display_condition].filter(Boolean).join(' · ')}
                </Text>
                <Text style={styles.rowMeta}>{locationLabel(item)}</Text>
              </View>
              <TireStatusBadge status={item.status} />
            </Pressable>
          )}
        />
      )}
    </View>
  );
}

const styles = StyleSheet.create({
  root: { flex: 1, backgroundColor: colors.page },
  newButtonWrap: { paddingHorizontal: space.lg, paddingTop: space.md },
  filters: { flexGrow: 0, paddingVertical: space.md },
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
