import { useCallback, useEffect, useState } from 'react';
import { Alert, Modal, ScrollView, StyleSheet, Text, View } from 'react-native';
import { useLocalSearchParams } from 'expo-router';
import { useSafeAreaInsets } from 'react-native-safe-area-context';
import { api, ApiError } from '../../../src/api/client';
import type { WorkOrder } from '../../../src/api/types';
import { useAuth } from '../../../src/auth/AuthContext';
import { canWrite } from '../../../src/auth/permissions';
import { colors, space, type } from '../../../src/theme';
import { PageHeader } from '../../../src/ui/PageHeader';
import { WorkOrderStatusBadge } from '../../../src/ui/StatusBadge';
import { Card, ErrorState, Field, LoadingState, PrimaryButton, SectionLabel } from '../../../src/ui/primitives';

export default function WorkOrderDetailScreen() {
  const { id } = useLocalSearchParams<{ id: string }>();
  const orderId = Number(id);
  const { user } = useAuth();
  const writeAllowed = canWrite(user?.role);

  const [order, setOrder] = useState<WorkOrder | null>(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [busy, setBusy] = useState(false);
  const [closeModal, setCloseModal] = useState(false);

  const load = useCallback(async () => {
    setError(null);
    try {
      setOrder(await api.workOrder(orderId));
    } catch (e) {
      setError(e instanceof ApiError ? e.message : 'No se pudo cargar la orden');
    } finally {
      setLoading(false);
    }
  }, [orderId]);

  useEffect(() => {
    void load();
  }, [load]);

  const runAction = async (fn: () => Promise<unknown>, successMsg: string) => {
    setBusy(true);
    try {
      await fn();
      Alert.alert('Listo', successMsg);
      await load();
    } catch (e) {
      Alert.alert('No se pudo completar', e instanceof ApiError ? e.message : 'Error inesperado');
    } finally {
      setBusy(false);
    }
  };

  const confirmSend = () => {
    Alert.alert('Enviar a taller', 'La cubierta va a quedar marcada en reparación. ¿Confirmás?', [
      { text: 'Cancelar', style: 'cancel' },
      { text: 'Sí, enviar', onPress: () => void runAction(() => api.sendWorkOrderToShop(orderId), 'Orden enviada al taller.') },
    ]);
  };

  const confirmCancel = () => {
    Alert.alert('Cancelar orden', 'Esta acción no se puede deshacer. ¿Confirmás?', [
      { text: 'No', style: 'cancel' },
      {
        text: 'Sí, cancelar orden',
        style: 'destructive',
        onPress: () => void runAction(() => api.cancelWorkOrder(orderId), 'Orden cancelada.'),
      },
    ]);
  };

  if (loading) return <LoadingState />;
  if (error || !order) return <ErrorState message={error ?? 'No encontrada'} onRetry={load} />;

  return (
    <View style={styles.root}>
      <PageHeader title={order.number ?? `OT #${order.id}`} subtitle={order.type === 'RECAPADO' ? 'Recapado' : 'Reparación'} showBack />
      <ScrollView contentContainerStyle={{ padding: space.lg, paddingTop: 0, gap: space.md }}>
        <Card>
          <View style={styles.headRow}>
            <Text style={styles.title}>Estado</Text>
            <WorkOrderStatusBadge status={order.status} />
          </View>
          <Text style={styles.sub}>Taller: {order.shop?.name ?? '—'}</Text>
          {order.tire ? (
            <Text style={styles.sub}>
              Neumático: Nº{order.tire.individual_number}
              {order.tire.brand?.name ? ` · ${order.tire.brand.name}` : ''}
            </Text>
          ) : null}
          {order.cost ? <Text style={styles.sub}>Costo: ${order.cost}</Text> : null}
          {order.notes ? <Text style={styles.sub}>Notas: {order.notes}</Text> : null}
        </Card>

        {writeAllowed && (order.status === 'ABIERTA' || order.status === 'EN_TALLER') ? (
          <Card>
            <SectionLabel>Acciones</SectionLabel>
            <View style={{ gap: space.sm }}>
              {order.status === 'ABIERTA' ? (
                <PrimaryButton title="Enviar a taller" icon="build-outline" onPress={confirmSend} loading={busy} variant="outline" block />
              ) : null}
              {order.status === 'EN_TALLER' ? (
                <PrimaryButton
                  title="Cerrar orden"
                  icon="checkmark-circle-outline"
                  onPress={() => setCloseModal(true)}
                  loading={busy}
                  block
                />
              ) : null}
              <PrimaryButton title="Cancelar orden" icon="close-circle-outline" onPress={confirmCancel} loading={busy} variant="danger" block />
            </View>
          </Card>
        ) : null}
      </ScrollView>

      <CloseOrderModal
        visible={closeModal}
        busy={busy}
        onClose={() => setCloseModal(false)}
        onConfirm={(cost, notes) => {
          setCloseModal(false);
          void runAction(() => api.closeWorkOrder(orderId, { cost, notes }), 'Orden cerrada. El historial de la cubierta quedó asentado.');
        }}
      />
    </View>
  );
}

function CloseOrderModal({
  visible,
  busy,
  onClose,
  onConfirm,
}: {
  visible: boolean;
  busy: boolean;
  onClose: () => void;
  onConfirm: (cost?: number, notes?: string) => void;
}) {
  const [cost, setCost] = useState('');
  const [notes, setNotes] = useState('');
  const insets = useSafeAreaInsets();

  useEffect(() => {
    if (visible) {
      setCost('');
      setNotes('');
    }
  }, [visible]);

  return (
    <Modal visible={visible} animationType="slide" transparent onRequestClose={onClose}>
      <View style={styles.modalBackdrop}>
        {/* Sin el inset acá, el botón de confirmar queda debajo de la barra
            de gestos del teléfono y no se puede tocar. */}
        <View style={[styles.modalCard, { paddingBottom: space.lg + insets.bottom }]}>
          <Text style={styles.title}>Cerrar orden</Text>
          <Text style={styles.sub}>La cubierta vuelve a stock. El costo es opcional.</Text>
          <Field label="Costo (opcional)" keyboardType="decimal-pad" value={cost} onChangeText={setCost} placeholder="0" />
          <Field label="Notas (opcional)" value={notes} onChangeText={setNotes} />
          <View style={{ flexDirection: 'row', gap: space.sm, marginTop: space.md }}>
            <View style={{ flex: 1 }}>
              <PrimaryButton title="Cancelar" onPress={onClose} variant="ghost" block />
            </View>
            <View style={{ flex: 1 }}>
              <PrimaryButton
                title="Cerrar orden"
                onPress={() => onConfirm(cost.trim() ? Number(cost.trim()) : undefined, notes.trim() || undefined)}
                loading={busy}
                block
              />
            </View>
          </View>
        </View>
      </View>
    </Modal>
  );
}

const styles = StyleSheet.create({
  root: { flex: 1, backgroundColor: colors.page },
  headRow: { flexDirection: 'row', justifyContent: 'space-between', alignItems: 'center', gap: space.sm },
  title: { color: colors.ink, fontSize: type.title, fontWeight: '700' },
  sub: { color: colors.muted, fontSize: type.body, marginTop: space.xs },
  modalBackdrop: { flex: 1, backgroundColor: 'rgba(0,0,0,0.6)', justifyContent: 'flex-end' },
  modalCard: {
    backgroundColor: colors.card,
    borderTopLeftRadius: 20,
    borderTopRightRadius: 20,
    padding: space.lg,
    gap: space.xs,
  },
});
