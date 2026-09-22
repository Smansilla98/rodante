import { useCallback, useEffect, useState } from 'react';
import { FlatList, Modal, Pressable, RefreshControl, ScrollView, StyleSheet, Text, View } from 'react-native';
import { useRouter } from 'expo-router';
import { useSafeAreaInsets } from 'react-native-safe-area-context';
import { api, ApiError } from '../../../src/api/client';
import type { RetreadShop, Tire, WorkOrder } from '../../../src/api/types';
import { useAuth } from '../../../src/auth/AuthContext';
import { canWrite } from '../../../src/auth/permissions';
import { colors, radius, space, touchTarget, type } from '../../../src/theme';
import { PageHeader } from '../../../src/ui/PageHeader';
import { WorkOrderStatusBadge } from '../../../src/ui/StatusBadge';
import { Card, Chip, EmptyState, ErrorState, Field, LoadingState, PrimaryButton } from '../../../src/ui/primitives';

export default function WorkOrdersScreen() {
  const router = useRouter();
  const { user } = useAuth();
  const writeAllowed = canWrite(user?.role);
  const [orders, setOrders] = useState<WorkOrder[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [refreshing, setRefreshing] = useState(false);
  const [showForm, setShowForm] = useState(false);

  const load = useCallback(async () => {
    setError(null);
    try {
      const res = await api.workOrders(1);
      setOrders(res.data);
    } catch (e) {
      setError(e instanceof ApiError ? e.message : 'No se pudieron cargar las órdenes');
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
      <PageHeader title="Órdenes de trabajo" subtitle="Recapado / reparación" showBack />
      {writeAllowed ? (
        <View style={{ paddingHorizontal: space.lg, marginBottom: space.sm }}>
          <PrimaryButton title="Nueva orden" icon="add-circle-outline" onPress={() => setShowForm(true)} block />
        </View>
      ) : null}

      {loading ? (
        <LoadingState />
      ) : error ? (
        <ErrorState message={error} onRetry={load} />
      ) : orders.length === 0 ? (
        <EmptyState title="Sin órdenes de trabajo" />
      ) : (
        <FlatList
          data={orders}
          keyExtractor={(o) => String(o.id)}
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
            <Pressable
              style={({ pressed }) => [styles.row, pressed && styles.rowPressed]}
              onPress={() => router.push(`/(tabs)/work-orders/${item.id}`)}
              accessibilityRole="button"
              accessibilityLabel={`Orden ${item.number ?? item.id}`}
              accessibilityHint="Ver detalle de la orden"
            >
              <View style={styles.headRow}>
                <Text style={styles.rowTitle}>
                  {item.number ?? `OT #${item.id}`} · {item.type === 'RECAPADO' ? 'Recapado' : 'Reparación'}
                </Text>
                <WorkOrderStatusBadge status={item.status} />
              </View>
              <Text style={styles.rowSub}>Taller: {item.shop?.name ?? 'Interno'}</Text>
              {item.tire ? <Text style={styles.rowSub}>Neumático: Nº{item.tire.individual_number}</Text> : null}
            </Pressable>
          )}
        />
      )}

      <NewWorkOrderModal
        visible={showForm}
        onClose={() => setShowForm(false)}
        onCreated={() => {
          setShowForm(false);
          void load();
        }}
      />
    </View>
  );
}

function NewWorkOrderModal({
  visible,
  onClose,
  onCreated,
}: {
  visible: boolean;
  onClose: () => void;
  onCreated: () => void;
}) {
  const [type, setType] = useState<'RECAPADO' | 'REPARACION'>('REPARACION');
  const [shops, setShops] = useState<RetreadShop[]>([]);
  const [shopId, setShopId] = useState<number | null>(null);
  const [internal, setInternal] = useState(false);
  const [tireQuery, setTireQuery] = useState('');
  const [foundTire, setFoundTire] = useState<Tire | null>(null);
  const [searching, setSearching] = useState(false);
  const [searchError, setSearchError] = useState<string | null>(null);
  const [notes, setNotes] = useState('');
  const [busy, setBusy] = useState(false);
  const [formError, setFormError] = useState<string | null>(null);
  const insets = useSafeAreaInsets();

  useEffect(() => {
    if (!visible) return;
    setType('REPARACION');
    setShopId(null);
    setInternal(false);
    setTireQuery('');
    setFoundTire(null);
    setSearchError(null);
    setNotes('');
    setFormError(null);
    void api
      .retreadShops()
      .then(setShops)
      .catch(() => setShops([]));
  }, [visible]);

  const searchTire = async () => {
    if (!tireQuery.trim()) return;
    setSearching(true);
    setSearchError(null);
    setFoundTire(null);
    try {
      setFoundTire(await api.lookupTire(tireQuery.trim()));
    } catch (e) {
      setSearchError(e instanceof ApiError ? e.message : 'No se pudo buscar el neumático');
    } finally {
      setSearching(false);
    }
  };

  const submit = async () => {
    if ((!internal && !shopId) || !foundTire) {
      setFormError('Elegí un taller (o marcá "Taller interno") y buscá el neumático.');
      return;
    }
    setBusy(true);
    setFormError(null);
    try {
      await api.createWorkOrder({
        tire_id: foundTire.id,
        retread_shop_id: internal ? undefined : (shopId ?? undefined),
        type,
        notes: notes.trim() || undefined,
      });
      onCreated();
    } catch (e) {
      setFormError(e instanceof ApiError ? e.message : 'No se pudo crear la orden');
    } finally {
      setBusy(false);
    }
  };

  return (
    <Modal visible={visible} animationType="slide" transparent onRequestClose={onClose}>
      <View style={styles.modalBackdrop}>
        {/* Sin el inset acá, el botón de confirmar queda debajo de la barra
            de gestos del teléfono y no se puede tocar. */}
        <View style={[styles.modalCard, { paddingBottom: space.lg + insets.bottom }]}>
          <ScrollView keyboardShouldPersistTaps="handled" contentContainerStyle={{ gap: space.sm }}>
            <Text style={styles.rowTitle}>Nueva orden de trabajo</Text>

            <Text style={styles.fieldLabel}>Tipo</Text>
            <View style={styles.chipRow}>
              <Chip label="Reparación" selected={type === 'REPARACION'} onPress={() => setType('REPARACION')} />
              <Chip label="Recapado" selected={type === 'RECAPADO'} onPress={() => setType('RECAPADO')} />
            </View>

            <Text style={styles.fieldLabel}>Taller</Text>
            <View style={styles.chipRow}>
              <Chip
                label="Taller interno"
                selected={internal}
                onPress={() => {
                  setInternal(true);
                  setShopId(null);
                }}
              />
              {shops.map((s) => (
                <Chip
                  key={s.id}
                  label={s.name}
                  selected={!internal && shopId === s.id}
                  onPress={() => {
                    setInternal(false);
                    setShopId(s.id);
                  }}
                />
              ))}
            </View>
            {!internal && shops.length === 0 ? (
              <Text style={styles.rowSub}>No hay talleres externos activos cargados — podés usar "Taller interno".</Text>
            ) : null}

            <Text style={styles.fieldLabel}>Neumático</Text>
            <View style={{ flexDirection: 'row', gap: space.sm, alignItems: 'flex-end' }}>
              <View style={{ flex: 1 }}>
                <Field
                  value={tireQuery}
                  onChangeText={(v) => {
                    setTireQuery(v);
                    setFoundTire(null);
                  }}
                  placeholder="Número del neumático"
                  keyboardType="number-pad"
                  returnKeyType="search"
                  onSubmitEditing={() => void searchTire()}
                />
              </View>
              <View style={{ marginBottom: space.sm }}>
                <PrimaryButton title="Buscar" icon="search-outline" onPress={() => void searchTire()} loading={searching} variant="outline" />
              </View>
            </View>
            {searchError ? <Text style={styles.error}>{searchError}</Text> : null}
            {foundTire ? (
              <View style={styles.foundCard}>
                <Text style={styles.rowTitle}>
                  Nº{foundTire.individual_number}
                  {foundTire.brand?.name ? ` · ${foundTire.brand.name}` : ''}
                </Text>
              </View>
            ) : null}

            <Field label="Notas (opcional)" value={notes} onChangeText={setNotes} />
            {formError ? <Text style={styles.error}>{formError}</Text> : null}

            <View style={{ flexDirection: 'row', gap: space.sm, marginTop: space.md }}>
              <View style={{ flex: 1 }}>
                <PrimaryButton title="Cancelar" onPress={onClose} variant="ghost" block />
              </View>
              <View style={{ flex: 1 }}>
                <PrimaryButton
                  title="Crear orden"
                  onPress={() => void submit()}
                  loading={busy}
                  disabled={(!internal && !shopId) || !foundTire}
                  block
                />
              </View>
            </View>
          </ScrollView>
        </View>
      </View>
    </Modal>
  );
}

const styles = StyleSheet.create({
  root: { flex: 1, backgroundColor: colors.page },
  headRow: { flexDirection: 'row', justifyContent: 'space-between', alignItems: 'center', gap: space.sm },
  row: {
    minHeight: touchTarget.row,
    backgroundColor: colors.card,
    borderRadius: radius.lg,
    borderWidth: 1,
    borderColor: colors.line,
    padding: space.md,
    gap: 4,
  },
  rowPressed: { backgroundColor: colors.card2 },
  rowTitle: { color: colors.ink, fontSize: type.bodyStrong, fontWeight: '700' },
  rowSub: { color: colors.muted, fontSize: type.caption, marginTop: 4 },
  fieldLabel: { color: colors.muted, fontSize: type.label, fontWeight: '600', marginTop: space.xs },
  chipRow: { flexDirection: 'row', flexWrap: 'wrap', gap: space.xs },
  error: { color: colors.danger, fontSize: type.body, marginTop: 4 },
  foundCard: {
    backgroundColor: colors.primarySoft,
    borderRadius: radius.md,
    borderWidth: 1,
    borderColor: colors.primary,
    padding: space.sm,
  },
  modalBackdrop: { flex: 1, backgroundColor: 'rgba(0,0,0,0.6)', justifyContent: 'flex-end' },
  modalCard: {
    backgroundColor: colors.card,
    borderTopLeftRadius: 20,
    borderTopRightRadius: 20,
    padding: space.lg,
    maxHeight: '85%',
  },
});
