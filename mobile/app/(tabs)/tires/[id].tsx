import { useCallback, useEffect, useState } from 'react';
import { Alert, Modal, Pressable, ScrollView, StyleSheet, Text, View } from 'react-native';
import { useLocalSearchParams, useRouter } from 'expo-router';
import { api, ApiError } from '../../../src/api/client';
import type { LifeReportPayload, PredictionPayload, TireHistoryPayload } from '../../../src/api/types';
import { useAuth } from '../../../src/auth/AuthContext';
import { canManageAbm, canRetireOrRecap, canWrite } from '../../../src/auth/permissions';
import { colors, radius, space, touchTarget, type } from '../../../src/theme';
import { TireStatusBadge } from '../../../src/ui/StatusBadge';
import { Card, Chip, ErrorState, Field, LoadingState, PrimaryButton, SectionLabel } from '../../../src/ui/primitives';

type Tab = 'history' | 'prediction' | 'life-report';

const TAB_LABEL: Record<Tab, string> = {
  history: 'Historial',
  prediction: 'Predicción',
  'life-report': 'Reporte de vida',
};

export default function TireDetailScreen() {
  const { id } = useLocalSearchParams<{ id: string }>();
  const tireId = Number(id);
  const router = useRouter();
  const { user } = useAuth();

  const [tab, setTab] = useState<Tab>('history');
  const [history, setHistory] = useState<TireHistoryPayload | null>(null);
  const [prediction, setPrediction] = useState<PredictionPayload | null>(null);
  const [lifeReport, setLifeReport] = useState<LifeReportPayload | null>(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [busy, setBusy] = useState(false);
  const [modal, setModal] = useState<null | 'incident' | 'measurement' | 'return' | 'retire'>(null);
  const [showConditionPicker, setShowConditionPicker] = useState(false);

  const canWriteThis = canWrite(user?.role);
  const canRetire = canRetireOrRecap(user?.role);
  const canModify = canManageAbm(user?.role);

  const load = useCallback(async () => {
    setError(null);
    try {
      const h = await api.tireHistory(tireId);
      setHistory(h);
    } catch (e) {
      setError(e instanceof ApiError ? e.message : 'No se pudo cargar el neumático');
    } finally {
      setLoading(false);
    }
  }, [tireId]);

  useEffect(() => {
    void load();
  }, [load]);

  useEffect(() => {
    if (tab === 'prediction' && !prediction) {
      api.tirePrediction(tireId).then(setPrediction).catch(() => {});
    }
    if (tab === 'life-report' && !lifeReport) {
      api.tireLifeReport(tireId).then(setLifeReport).catch(() => {});
    }
  }, [tab, tireId, prediction, lifeReport]);

  const runAction = async (fn: () => Promise<unknown>, successMsg: string) => {
    setBusy(true);
    try {
      await fn();
      setModal(null);
      Alert.alert('Listo', successMsg);
      await load();
    } catch (e) {
      Alert.alert('No se pudo completar la acción', e instanceof ApiError ? e.message : 'Error inesperado');
    } finally {
      setBusy(false);
    }
  };

  const confirmReturnToStock = () => {
    Alert.alert(
      'Devolver a stock',
      'El neumático va a volver a quedar disponible en stock. ¿Confirmás esta acción?',
      [
        { text: 'Cancelar', style: 'cancel' },
        {
          text: 'Sí, devolver a stock',
          onPress: () => void runAction(() => api.returnToStock(tireId, {}), 'Neumático devuelto a stock.'),
        },
      ],
    );
  };

  if (loading) return <LoadingState />;
  if (error || !history) return <ErrorState message={error ?? 'No encontrado'} onRetry={load} />;

  return (
    <View style={styles.root}>
      <ScrollView contentContainerStyle={{ padding: space.lg, gap: space.md }}>
        <Card>
          <View style={styles.headRow}>
            <Text style={styles.title}>{history.display}</Text>
            <TireStatusBadge status={history.tire.status} />
          </View>
          {canWriteThis && ['NUEVA', 'NUEVA_USADA', 'USADA'].includes(history.tire.condition ?? '') ? (
            <Pressable
              onPress={() => setShowConditionPicker((v) => !v)}
              style={styles.conditionRow}
              accessibilityRole="button"
              accessibilityLabel={`Condición: ${history.tire.display_condition}. Tocá para cambiarla`}
            >
              <Text style={styles.sub}>{history.tire.display_condition ?? '—'}</Text>
              <Text style={styles.changeLink}>Cambiar</Text>
            </Pressable>
          ) : (
            <Text style={styles.sub}>{history.tire.display_condition ?? '—'}</Text>
          )}
          {showConditionPicker ? (
            <View style={{ marginTop: space.xs }}>
              <View style={styles.chipRow}>
                {(
                  [
                    ['NUEVA', 'Nuevo'],
                    ['NUEVA_USADA', 'Nuevo usado'],
                    ['USADA', 'Usado'],
                  ] as const
                ).map(([value, label]) => (
                  <Chip
                    key={value}
                    label={label}
                    selected={history.tire.condition === value}
                    onPress={() => void runAction(() => api.setCondition(tireId, value), 'Condición actualizada.')}
                  />
                ))}
              </View>
              <Text style={styles.hint}>Para recapado, reparación o baja usá las acciones de abajo.</Text>
            </View>
          ) : null}
          <Text style={styles.sub}>Km acumulados: {history.tire.accumulated_km ?? '—'}</Text>
          {canWriteThis && history.tire.condition === 'RECAPADA' ? (
            <View style={{ marginTop: space.sm }}>
              <Text style={styles.fieldLabel}>Desgaste del recapado</Text>
              <View style={{ flexDirection: 'row', gap: space.xs }}>
                <Chip
                  label="Nueva"
                  selected={history.tire.recap_wear === 'NUEVA'}
                  onPress={() => void runAction(() => api.setRecapWear(tireId, 'NUEVA'), 'Recapado marcado como nuevo.')}
                />
                <Chip
                  label="Usada"
                  selected={history.tire.recap_wear === 'USADA'}
                  onPress={() => void runAction(() => api.setRecapWear(tireId, 'USADA'), 'Recapado marcado como usado.')}
                />
              </View>
            </View>
          ) : null}
        </Card>

        <View style={styles.tabs}>
          {(['history', 'prediction', 'life-report'] as Tab[]).map((t) => (
            <Pressable
              key={t}
              onPress={() => setTab(t)}
              style={[styles.tab, tab === t && styles.tabOn]}
              accessibilityRole="tab"
              accessibilityState={{ selected: tab === t }}
              accessibilityLabel={TAB_LABEL[t]}
            >
              <Text style={[styles.tabText, tab === t && styles.tabTextOn]}>{TAB_LABEL[t]}</Text>
            </Pressable>
          ))}
        </View>

        {tab === 'history' ? (
          <Card>
            <SectionLabel>Movimientos ({history.movements?.length ?? 0})</SectionLabel>
            <SectionLabel>Incidentes ({history.incidents?.length ?? 0})</SectionLabel>
            <SectionLabel>Ciclos de vida ({history.lifecycles?.length ?? 0})</SectionLabel>
          </Card>
        ) : tab === 'prediction' ? (
          <Card>
            {prediction ? (
              <Text style={styles.mono}>{JSON.stringify(prediction, null, 2)}</Text>
            ) : (
              <LoadingState label="Calculando predicción…" />
            )}
          </Card>
        ) : (
          <Card>
            {lifeReport ? (
              <>
                <SectionLabel>Costo total</SectionLabel>
                <Text style={styles.sub}>${lifeReport.cost_total}</Text>
                <SectionLabel>Fotos de baja ({lifeReport.photos?.length ?? 0})</SectionLabel>
              </>
            ) : (
              <LoadingState label="Generando reporte…" />
            )}
          </Card>
        )}

        {canWriteThis ? (
          <Card>
            <SectionLabel>Acciones</SectionLabel>
            <View style={{ gap: space.sm }}>
              {canModify ? (
                <PrimaryButton
                  title="Modificar neumático"
                  icon="create-outline"
                  onPress={() => router.push(`/(tabs)/tires/edit?id=${tireId}`)}
                  variant="outline"
                  block
                />
              ) : null}
              <PrimaryButton
                title="Registrar incidente"
                icon="alert-circle-outline"
                onPress={() => setModal('incident')}
                variant="outline"
                block
              />
              <PrimaryButton
                title="Registrar medición"
                icon="speedometer-outline"
                onPress={() => setModal('measurement')}
                variant="outline"
                block
              />
              <PrimaryButton
                title="Devolver a stock"
                icon="arrow-undo-outline"
                onPress={confirmReturnToStock}
                variant="outline"
                block
              />
              {canRetire ? (
                <PrimaryButton
                  title="Dar de baja"
                  icon="close-circle-outline"
                  onPress={() => setModal('retire')}
                  variant="danger"
                  block
                />
              ) : null}
            </View>
          </Card>
        ) : (
          <Card>
            <Text style={styles.sub}>Tu rol (Consulta) no tiene permiso para modificar neumáticos.</Text>
          </Card>
        )}
      </ScrollView>

      <ActionModal
        visible={modal === 'incident'}
        title="Registrar incidente"
        confirmLabel="Guardar incidente"
        onClose={() => setModal(null)}
        busy={busy}
        onSubmit={(fields) =>
          runAction(
            () => api.createIncident(tireId, { type: fields.type || 'OTRA', description: fields.description, notes: fields.notes }),
            'Incidente registrado.',
          )
        }
        fields={[
          { key: 'type', label: 'Tipo (ej: PINCHADURA, PARCHE, INSPECCION)' },
          { key: 'description', label: 'Descripción' },
          { key: 'notes', label: 'Notas' },
        ]}
      />

      <ActionModal
        visible={modal === 'measurement'}
        title="Registrar medición"
        confirmLabel="Guardar medición"
        onClose={() => setModal(null)}
        busy={busy}
        onSubmit={(fields) =>
          runAction(
            () =>
              api.createMeasurement(tireId, {
                notes: fields.notes,
                readings: [
                  {
                    zone_id: Number(fields.zone_id || 0),
                    millimeters: Number(fields.millimeters || 0),
                  },
                ],
              }),
            'Medición registrada.',
          )
        }
        fields={[
          { key: 'zone_id', label: 'ID de zona de medición', keyboardType: 'number-pad' },
          { key: 'millimeters', label: 'Milímetros', keyboardType: 'decimal-pad' },
          { key: 'notes', label: 'Notas' },
        ]}
      />

      <ActionModal
        visible={modal === 'retire'}
        title="Dar de baja"
        confirmLabel="Dar de baja"
        onClose={() => setModal(null)}
        busy={busy}
        destructive
        confirmAlert={{
          message:
            'Esta acción no se puede deshacer. El neumático va a pasar a estado "De baja" y ya no va a poder usarse en la flota.',
          confirmLabel: 'Sí, dar de baja',
        }}
        onSubmit={(fields) =>
          runAction(
            () => api.retireTire(tireId, { reason_id: Number(fields.reason_id || 0), notes: fields.notes }),
            'Neumático dado de baja.',
          )
        }
        fields={[
          { key: 'reason_id', label: 'ID de motivo (movement_reasons)', keyboardType: 'number-pad' },
          { key: 'notes', label: 'Notas' },
        ]}
      />
    </View>
  );
}

type FieldSpec = { key: string; label: string; keyboardType?: 'default' | 'number-pad' | 'decimal-pad' };

function ActionModal({
  visible,
  title,
  fields,
  busy,
  destructive,
  confirmLabel = 'Guardar',
  confirmAlert,
  onClose,
  onSubmit,
}: {
  visible: boolean;
  title: string;
  fields: FieldSpec[];
  busy: boolean;
  destructive?: boolean;
  confirmLabel?: string;
  /** Cuando está seteado, antes de enviar se pide una confirmación explícita en lenguaje simple. */
  confirmAlert?: { message: string; confirmLabel: string };
  onClose: () => void;
  onSubmit: (values: Record<string, string>) => void;
}) {
  const [values, setValues] = useState<Record<string, string>>({});

  useEffect(() => {
    if (visible) setValues({});
  }, [visible]);

  const handleConfirm = () => {
    if (confirmAlert) {
      Alert.alert(title, confirmAlert.message, [
        { text: 'Cancelar', style: 'cancel' },
        { text: confirmAlert.confirmLabel, style: 'destructive', onPress: () => onSubmit(values) },
      ]);
    } else {
      onSubmit(values);
    }
  };

  return (
    <Modal visible={visible} animationType="slide" transparent onRequestClose={onClose}>
      <View style={styles.modalBackdrop}>
        <View style={styles.modalCard}>
          <Text style={styles.title}>{title}</Text>
          {fields.map((f) => (
            <Field
              key={f.key}
              label={f.label}
              keyboardType={f.keyboardType}
              value={values[f.key] ?? ''}
              onChangeText={(v) => setValues((s) => ({ ...s, [f.key]: v }))}
            />
          ))}
          <View style={{ flexDirection: 'row', gap: space.sm, marginTop: space.md }}>
            <View style={{ flex: 1 }}>
              <PrimaryButton title="Cancelar" onPress={onClose} variant="ghost" block />
            </View>
            <View style={{ flex: 1 }}>
              <PrimaryButton
                title={confirmLabel}
                onPress={handleConfirm}
                loading={busy}
                variant={destructive ? 'danger' : 'primary'}
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
  title: { color: colors.ink, fontSize: type.title, fontWeight: '700', flexShrink: 1 },
  sub: { color: colors.muted, fontSize: type.body, marginTop: space.xs },
  fieldLabel: { color: colors.muted, fontSize: type.label, fontWeight: '600', marginBottom: space.xs },
  conditionRow: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
    minHeight: 36,
  },
  changeLink: { color: colors.primary, fontSize: type.label, fontWeight: '700' },
  chipRow: { flexDirection: 'row', flexWrap: 'wrap', gap: space.xs },
  hint: { color: colors.muted, fontSize: type.caption, marginTop: space.xs },
  mono: { color: colors.muted, fontSize: type.caption, fontFamily: 'monospace' },
  tabs: { flexDirection: 'row', gap: space.sm },
  tab: {
    flex: 1,
    minHeight: touchTarget.min,
    alignItems: 'center',
    justifyContent: 'center',
    borderRadius: radius.md,
    backgroundColor: colors.card,
    borderWidth: 1,
    borderColor: colors.line,
  },
  tabOn: { backgroundColor: colors.primarySoft, borderColor: colors.primary },
  tabText: { color: colors.muted, fontSize: type.caption, fontWeight: '700' },
  tabTextOn: { color: colors.primary },
  modalBackdrop: { flex: 1, backgroundColor: 'rgba(0,0,0,0.6)', justifyContent: 'flex-end' },
  modalCard: {
    backgroundColor: colors.card,
    borderTopLeftRadius: 20,
    borderTopRightRadius: 20,
    padding: space.lg,
    gap: space.xs,
  },
});
