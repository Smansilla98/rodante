import { useCallback, useEffect, useState } from 'react';
import { Alert, FlatList, Modal, StyleSheet, Text, View } from 'react-native';
import { useLocalSearchParams } from 'expo-router';
import { useSafeAreaInsets } from 'react-native-safe-area-context';
import { api, ApiError } from '../../../src/api/client';
import type { InventoryLine, InventorySessionDetailPayload } from '../../../src/api/types';
import { useAuth } from '../../../src/auth/AuthContext';
import { canWrite } from '../../../src/auth/permissions';
import { INVENTORY_LINE_DELTA_LABEL, colors, inventoryLineDeltaColor, radius, space, type } from '../../../src/theme';
import { PageHeader } from '../../../src/ui/PageHeader';
import { InventorySessionStatusBadge } from '../../../src/ui/StatusBadge';
import { Card, Chip, ErrorState, Field, LoadingState, PrimaryButton, SectionLabel, StatusPill } from '../../../src/ui/primitives';

export default function InventorySessionDetailScreen() {
  const { id } = useLocalSearchParams<{ id: string }>();
  const sessionId = Number(id);
  const { user } = useAuth();
  const writeAllowed = canWrite(user?.role);

  const [payload, setPayload] = useState<InventorySessionDetailPayload | null>(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [busy, setBusy] = useState(false);
  const [scanQuery, setScanQuery] = useState('');
  const [scanFeedback, setScanFeedback] = useState<string | null>(null);
  const [closeModal, setCloseModal] = useState(false);

  const load = useCallback(async () => {
    setError(null);
    try {
      setPayload(await api.inventorySession(sessionId));
    } catch (e) {
      setError(e instanceof ApiError ? e.message : 'No se pudo cargar el inventario');
    } finally {
      setLoading(false);
    }
  }, [sessionId]);

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

  const scan = async () => {
    if (!scanQuery.trim()) return;
    setBusy(true);
    setScanFeedback(null);
    try {
      const line = await api.scanInventorySession(sessionId, scanQuery.trim());
      setScanFeedback(
        `Nº${line.tire?.individual_number ?? '—'} · ${INVENTORY_LINE_DELTA_LABEL[line.delta] ?? line.delta}`,
      );
      setScanQuery('');
      await load();
    } catch (e) {
      setScanFeedback(e instanceof ApiError ? e.message : 'No se pudo escanear');
    } finally {
      setBusy(false);
    }
  };

  const confirmCancel = () => {
    Alert.alert('Cancelar inventario', 'Esta acción no se puede deshacer. ¿Confirmás?', [
      { text: 'No', style: 'cancel' },
      {
        text: 'Sí, cancelar',
        style: 'destructive',
        onPress: () => void runAction(() => api.cancelInventorySession(sessionId), 'Inventario cancelado.'),
      },
    ]);
  };

  if (loading) return <LoadingState />;
  if (error || !payload) return <ErrorState message={error ?? 'No encontrado'} onRetry={load} />;

  const { session, lines } = payload;
  const canCancel = writeAllowed && session.status !== 'CLOSED' && session.status !== 'CANCELLED';

  return (
    <View style={styles.root}>
      <PageHeader title={session.number} subtitle={session.base?.name ?? undefined} showBack />
      <FlatList
        data={lines.data}
        keyExtractor={(l) => String(l.id)}
        contentContainerStyle={{ padding: space.lg, paddingTop: 0, gap: space.md }}
        ListHeaderComponent={
          <View style={{ gap: space.md, marginBottom: space.sm }}>
            <Card>
              <View style={styles.headRow}>
                <Text style={styles.title}>Estado</Text>
                <InventorySessionStatusBadge status={session.status} />
              </View>
              <View style={styles.countsRow}>
                <View style={styles.countBox}>
                  <Text style={styles.countValue}>{session.expected_count ?? '—'}</Text>
                  <Text style={styles.countLabel}>Esperado</Text>
                </View>
                <View style={styles.countBox}>
                  <Text style={styles.countValue}>{session.found_count ?? '—'}</Text>
                  <Text style={styles.countLabel}>Encontrado</Text>
                </View>
                <View style={styles.countBox}>
                  <Text style={styles.countValue}>{session.missing_count ?? '—'}</Text>
                  <Text style={styles.countLabel}>Faltante</Text>
                </View>
                <View style={styles.countBox}>
                  <Text style={styles.countValue}>{session.unexpected_count ?? '—'}</Text>
                  <Text style={styles.countLabel}>Sobrante</Text>
                </View>
              </View>
            </Card>

            {writeAllowed && session.status === 'OPEN' ? (
              <PrimaryButton
                title="Iniciar conteo"
                icon="play-circle-outline"
                onPress={() => void runAction(() => api.startInventorySession(sessionId), 'Conteo iniciado.')}
                loading={busy}
                block
              />
            ) : null}

            {writeAllowed && session.status === 'COUNTING' ? (
              <Card>
                <SectionLabel>Escanear cubierta</SectionLabel>
                <View style={{ flexDirection: 'row', gap: space.sm, alignItems: 'flex-end' }}>
                  <View style={{ flex: 1 }}>
                    <Field
                      value={scanQuery}
                      onChangeText={setScanQuery}
                      placeholder="Número o token"
                      autoCapitalize="none"
                      returnKeyType="search"
                      onSubmitEditing={() => void scan()}
                    />
                  </View>
                  <View style={{ marginBottom: space.sm }}>
                    <PrimaryButton title="Escanear" onPress={() => void scan()} loading={busy} />
                  </View>
                </View>
                {scanFeedback ? <Text style={styles.sub}>{scanFeedback}</Text> : null}
                <View style={{ marginTop: space.md }}>
                  <PrimaryButton
                    title="Enviar a revisión"
                    icon="checkmark-done-outline"
                    onPress={() => void runAction(() => api.reviewInventorySession(sessionId), 'Enviado a revisión.')}
                    loading={busy}
                    variant="outline"
                    block
                  />
                </View>
              </Card>
            ) : null}

            {writeAllowed && session.status === 'REVIEW' ? (
              <PrimaryButton
                title="Cerrar inventario"
                icon="lock-closed-outline"
                onPress={() => setCloseModal(true)}
                loading={busy}
                block
              />
            ) : null}

            {canCancel ? (
              <PrimaryButton title="Cancelar inventario" icon="close-circle-outline" onPress={confirmCancel} loading={busy} variant="danger" block />
            ) : null}

            <SectionLabel>Diferencias ({lines.meta.total})</SectionLabel>
          </View>
        }
        renderItem={({ item }) => <LineRow line={item} />}
        ListEmptyComponent={<Text style={styles.sub}>Sin líneas todavía.</Text>}
      />

      <CloseSessionModal
        visible={closeModal}
        busy={busy}
        onClose={() => setCloseModal(false)}
        onConfirm={(applyFixes, notes) => {
          setCloseModal(false);
          void runAction(
            () => api.closeInventorySession(sessionId, { apply_fixes: applyFixes, notes }),
            applyFixes
              ? 'Inventario cerrado. Se aplicaron correcciones de base donde correspondía.'
              : 'Inventario cerrado. Las diferencias quedaron auditadas sin mover ubicaciones.',
          );
        }}
      />
    </View>
  );
}

function LineRow({ line }: { line: InventoryLine }) {
  return (
    <View style={styles.lineRow}>
      <View style={{ flex: 1 }}>
        <Text style={styles.rowTitle}>
          Nº{line.tire?.individual_number ?? '—'}
          {line.tire?.brand?.name ? ` · ${line.tire.brand.name}` : ''}
        </Text>
        {line.notes ? <Text style={styles.sub}>{line.notes}</Text> : null}
      </View>
      <StatusPill label={INVENTORY_LINE_DELTA_LABEL[line.delta] ?? line.delta} color={inventoryLineDeltaColor(line.delta)} />
    </View>
  );
}

function CloseSessionModal({
  visible,
  busy,
  onClose,
  onConfirm,
}: {
  visible: boolean;
  busy: boolean;
  onClose: () => void;
  onConfirm: (applyFixes: boolean, notes?: string) => void;
}) {
  const [applyFixes, setApplyFixes] = useState(false);
  const [notes, setNotes] = useState('');
  const insets = useSafeAreaInsets();

  useEffect(() => {
    if (visible) {
      setApplyFixes(false);
      setNotes('');
    }
  }, [visible]);

  return (
    <Modal visible={visible} animationType="slide" transparent onRequestClose={onClose}>
      <View style={styles.modalBackdrop}>
        {/* El botón de confirmación es lo último tocable — sin el inset acá
            queda debajo de la barra de gestos del teléfono y no se puede tocar. */}
        <View style={[styles.modalCard, { paddingBottom: space.lg + insets.bottom }]}>
          <Text style={styles.title}>Cerrar inventario</Text>
          <View style={{ flexDirection: 'row', gap: space.xs, marginTop: space.sm }}>
            <Chip label="Solo auditar diferencias" selected={!applyFixes} onPress={() => setApplyFixes(false)} />
            <Chip label="Corregir bases" selected={applyFixes} onPress={() => setApplyFixes(true)} />
          </View>
          <Text style={styles.hint}>
            {applyFixes
              ? 'Las cubiertas encontradas en otra base se van a mover ahí.'
              : 'No se mueve ninguna cubierta, solo queda registrada la diferencia.'}
          </Text>
          <Field label="Notas (opcional)" value={notes} onChangeText={setNotes} />
          <View style={{ flexDirection: 'row', gap: space.sm, marginTop: space.md }}>
            <View style={{ flex: 1 }}>
              <PrimaryButton title="Cancelar" onPress={onClose} variant="ghost" block />
            </View>
            <View style={{ flex: 1 }}>
              <PrimaryButton
                title="Cerrar inventario"
                onPress={() => onConfirm(applyFixes, notes.trim() || undefined)}
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
  hint: { color: colors.muted, fontSize: type.caption, marginTop: space.xs },
  countsRow: { flexDirection: 'row', flexWrap: 'wrap', gap: space.sm, marginTop: space.sm },
  countBox: { flexBasis: '47%', flexGrow: 1, alignItems: 'center', paddingVertical: space.xs },
  countValue: { color: colors.ink, fontSize: type.title, fontWeight: '700' },
  countLabel: { color: colors.muted, fontSize: type.caption, marginTop: 2 },
  lineRow: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: space.sm,
    backgroundColor: colors.card,
    borderRadius: radius.lg,
    borderWidth: 1,
    borderColor: colors.line,
    padding: space.md,
    marginBottom: space.sm,
  },
  rowTitle: { color: colors.ink, fontSize: type.bodyStrong, fontWeight: '700' },
  modalBackdrop: { flex: 1, backgroundColor: 'rgba(0,0,0,0.6)', justifyContent: 'flex-end' },
  modalCard: {
    backgroundColor: colors.card,
    borderTopLeftRadius: 20,
    borderTopRightRadius: 20,
    padding: space.lg,
    gap: space.xs,
  },
});
