import { useCallback, useEffect, useState } from 'react';
import { FlatList, Modal, RefreshControl, StyleSheet, Text, View } from 'react-native';
import { api, ApiError } from '../../../src/api/client';
import type { WorkOrder } from '../../../src/api/types';
import { useAuth } from '../../../src/auth/AuthContext';
import { canWrite } from '../../../src/auth/permissions';
import { colors, space, type } from '../../../src/theme';
import { PageHeader } from '../../../src/ui/PageHeader';
import { WorkOrderStatusBadge } from '../../../src/ui/StatusBadge';
import { Card, EmptyState, ErrorState, Field, LoadingState, PrimaryButton } from '../../../src/ui/primitives';

export default function WorkOrdersScreen() {
  const { user } = useAuth();
  const writeAllowed = canWrite(user?.role);
  const [orders, setOrders] = useState<WorkOrder[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [refreshing, setRefreshing] = useState(false);
  const [showForm, setShowForm] = useState(false);
  const [busy, setBusy] = useState(false);
  const [form, setForm] = useState({ tire_id: '', retread_shop_id: '', type: 'RECAPADO', notes: '' });
  const [formError, setFormError] = useState<string | null>(null);

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

  const submit = async () => {
    setBusy(true);
    setFormError(null);
    try {
      await api.createWorkOrder({
        tire_id: Number(form.tire_id || 0) || undefined,
        retread_shop_id: Number(form.retread_shop_id || 0),
        type: form.type === 'REPARACION' ? 'REPARACION' : 'RECAPADO',
        notes: form.notes || undefined,
      });
      setShowForm(false);
      setForm({ tire_id: '', retread_shop_id: '', type: 'RECAPADO', notes: '' });
      await load();
    } catch (e) {
      setFormError(e instanceof ApiError ? e.message : 'No se pudo crear la orden');
    } finally {
      setBusy(false);
    }
  };

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
            <Card>
              <View style={styles.headRow}>
                <Text style={styles.rowTitle}>OT #{item.id} · {item.type}</Text>
                <WorkOrderStatusBadge status={item.status} />
              </View>
              <Text style={styles.rowSub}>Taller: {item.shop?.name ?? '—'}</Text>
              {item.tire ? <Text style={styles.rowSub}>Neumático: Nº{item.tire.individual_number}</Text> : null}
            </Card>
          )}
        />
      )}

      <Modal visible={showForm} animationType="slide" transparent onRequestClose={() => setShowForm(false)}>
        <View style={styles.modalBackdrop}>
          <View style={styles.modalCard}>
            <Text style={styles.rowTitle}>Nueva orden de trabajo</Text>
            <Field
              label="ID de neumático"
              keyboardType="number-pad"
              value={form.tire_id}
              onChangeText={(v) => setForm((s) => ({ ...s, tire_id: v }))}
            />
            <Field
              label="ID de taller (retread_shop_id)"
              keyboardType="number-pad"
              value={form.retread_shop_id}
              onChangeText={(v) => setForm((s) => ({ ...s, retread_shop_id: v }))}
            />
            <Field
              label="Tipo (RECAPADO o REPARACION)"
              value={form.type}
              onChangeText={(v) => setForm((s) => ({ ...s, type: v.toUpperCase() }))}
            />
            <Field label="Notas" value={form.notes} onChangeText={(v) => setForm((s) => ({ ...s, notes: v }))} />
            {formError ? <Text style={styles.error}>{formError}</Text> : null}
            <View style={{ flexDirection: 'row', gap: space.sm, marginTop: space.md }}>
              <View style={{ flex: 1 }}>
                <PrimaryButton title="Cancelar" onPress={() => setShowForm(false)} variant="ghost" block />
              </View>
              <View style={{ flex: 1 }}>
                <PrimaryButton title="Crear orden" onPress={() => void submit()} loading={busy} block />
              </View>
            </View>
          </View>
        </View>
      </Modal>
    </View>
  );
}

const styles = StyleSheet.create({
  root: { flex: 1, backgroundColor: colors.page },
  headRow: { flexDirection: 'row', justifyContent: 'space-between', alignItems: 'center', gap: space.sm },
  rowTitle: { color: colors.ink, fontSize: type.bodyStrong, fontWeight: '700' },
  rowSub: { color: colors.muted, fontSize: type.caption, marginTop: 4 },
  error: { color: colors.danger, fontSize: type.body, marginTop: 4 },
  modalBackdrop: { flex: 1, backgroundColor: 'rgba(0,0,0,0.6)', justifyContent: 'flex-end' },
  modalCard: {
    backgroundColor: colors.card,
    borderTopLeftRadius: 20,
    borderTopRightRadius: 20,
    padding: space.lg,
    gap: space.xs,
  },
});
