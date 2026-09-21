import { useCallback, useEffect, useState } from 'react';
import { FlatList, Pressable, RefreshControl, ScrollView, StyleSheet, Text, View } from 'react-native';
import { useRouter } from 'expo-router';
import { api, ApiError } from '../../../src/api/client';
import type { Base, Tire, TireCatalogPayload } from '../../../src/api/types';
import { useAuth } from '../../../src/auth/AuthContext';
import { canWrite } from '../../../src/auth/permissions';
import { colors, radius, space, touchTarget, type } from '../../../src/theme';
import { TireStatusBadge } from '../../../src/ui/StatusBadge';
import { Chip, EmptyState, ErrorState, Field, Icon, LoadingState, PrimaryButton } from '../../../src/ui/primitives';

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

/** Filtros rápidos por condición visible, no por status interno — así se ve en toda la app. */
type QuickFilter = 'ALL' | 'NUEVA' | 'NUEVA_USADA' | 'USADA' | 'A_REPARAR' | 'RECAPADA' | 'BAJA';

const QUICK_FILTERS: Array<{ key: QuickFilter; label: string }> = [
  { key: 'ALL', label: 'Todos' },
  { key: 'NUEVA', label: 'Nuevo' },
  { key: 'NUEVA_USADA', label: 'Nuevo usado' },
  { key: 'USADA', label: 'Usado' },
  { key: 'A_REPARAR', label: 'A reparar' },
  { key: 'RECAPADA', label: 'Recapada' },
  { key: 'BAJA', label: 'Baja' },
];

function paramsForQuickFilter(f: QuickFilter): { status?: string; condition?: string } {
  if (f === 'A_REPARAR') return { status: 'EN_REPARACION' };
  if (f === 'BAJA') return { status: 'DE_BAJA' };
  if (f === 'ALL') return {};
  return { condition: f };
}

export default function TiresListScreen() {
  const router = useRouter();
  const { user } = useAuth();
  const writeAllowed = canWrite(user?.role);

  const [quick, setQuick] = useState<QuickFilter>('ALL');
  const [search, setSearch] = useState('');
  const [showFilters, setShowFilters] = useState(false);
  const [catalog, setCatalog] = useState<TireCatalogPayload | null>(null);
  const [bases, setBases] = useState<Base[]>([]);
  const [baseId, setBaseId] = useState<number | null>(null);
  const [sizeId, setSizeId] = useState<number | null>(null);

  const [tires, setTires] = useState<Tire[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [refreshing, setRefreshing] = useState(false);

  useEffect(() => {
    void Promise.all([api.tireCatalog(), api.bases()])
      .then(([c, b]) => {
        setCatalog(c);
        setBases(b);
      })
      .catch(() => {});
  }, []);

  const load = useCallback(async () => {
    setError(null);
    try {
      const res = await api.tires({
        ...paramsForQuickFilter(quick),
        q: search.trim() || undefined,
        base_id: baseId ?? undefined,
        tire_size_id: sizeId ?? undefined,
        page: 1,
      });
      setTires(res.data);
    } catch (e) {
      setError(e instanceof ApiError ? e.message : 'No se pudieron cargar los neumáticos');
    } finally {
      setLoading(false);
      setRefreshing(false);
    }
  }, [quick, search, baseId, sizeId]);

  useEffect(() => {
    setLoading(true);
    const t = setTimeout(() => void load(), search ? 350 : 0);
    return () => clearTimeout(t);
  }, [load, search]);

  const onRefresh = () => {
    setRefreshing(true);
    void load();
  };

  const activeFilterCount = (baseId ? 1 : 0) + (sizeId ? 1 : 0);

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

      <View style={styles.searchWrap}>
        <Field
          value={search}
          onChangeText={setSearch}
          placeholder="Buscar por número, patente, marca, modelo, medida, DOT…"
          autoCapitalize="none"
          returnKeyType="search"
        />
      </View>

      <ScrollView
        horizontal
        showsHorizontalScrollIndicator={false}
        style={styles.filters}
        contentContainerStyle={{ gap: space.sm, paddingHorizontal: space.lg }}
      >
        {QUICK_FILTERS.map((f) => (
          <Chip key={f.key} label={f.label} selected={quick === f.key} onPress={() => setQuick(f.key)} />
        ))}
      </ScrollView>

      <Pressable
        onPress={() => setShowFilters((v) => !v)}
        style={styles.filtersToggle}
        accessibilityRole="button"
        accessibilityLabel="Filtros avanzados"
      >
        <Icon name="options-outline" size={18} color={colors.primary} />
        <Text style={styles.filtersToggleText}>
          Filtros{activeFilterCount > 0 ? ` (${activeFilterCount})` : ''}
        </Text>
        <Icon name={showFilters ? 'chevron-up-outline' : 'chevron-down-outline'} size={18} color={colors.muted} />
      </Pressable>

      {showFilters && catalog ? (
        <View style={styles.advancedPanel}>
          <Text style={styles.advancedLabel}>Base</Text>
          <View style={styles.chipRow}>
            <Chip label="Todas" selected={baseId === null} onPress={() => setBaseId(null)} />
            {bases.map((b) => (
              <Chip key={b.id} label={b.name} selected={baseId === b.id} onPress={() => setBaseId(b.id)} />
            ))}
          </View>
          <Text style={styles.advancedLabel}>Medida</Text>
          <View style={styles.chipRow}>
            <Chip label="Todas" selected={sizeId === null} onPress={() => setSizeId(null)} />
            {catalog.sizes.map((s) => (
              <Chip key={s.id} label={s.alias ?? s.code} selected={sizeId === s.id} onPress={() => setSizeId(s.id)} />
            ))}
          </View>
          {activeFilterCount > 0 ? (
            <PrimaryButton
              title="Limpiar filtros"
              variant="ghost"
              onPress={() => {
                setBaseId(null);
                setSizeId(null);
              }}
            />
          ) : null}
        </View>
      ) : null}

      {loading ? (
        <LoadingState />
      ) : error ? (
        <ErrorState message={error} onRetry={load} />
      ) : tires.length === 0 ? (
        <EmptyState title="Sin neumáticos" hint="No hay resultados para esta búsqueda o filtro." />
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
  searchWrap: { paddingHorizontal: space.lg, paddingTop: space.sm },
  filters: { flexGrow: 0, paddingVertical: space.md },
  filtersToggle: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: space.xs,
    minHeight: 44,
    paddingHorizontal: space.lg,
    marginBottom: space.xs,
  },
  filtersToggleText: { color: colors.ink, fontSize: type.label, fontWeight: '700' },
  advancedPanel: {
    marginHorizontal: space.lg,
    marginBottom: space.md,
    padding: space.md,
    borderRadius: radius.lg,
    borderWidth: 1,
    borderColor: colors.line,
    backgroundColor: colors.card,
    gap: space.xs,
  },
  advancedLabel: { color: colors.muted, fontSize: type.label, fontWeight: '600' },
  chipRow: { flexDirection: 'row', flexWrap: 'wrap', gap: space.xs, marginBottom: space.sm },
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
