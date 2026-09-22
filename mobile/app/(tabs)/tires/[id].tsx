import { useCallback, useEffect, useState } from 'react';
import { Alert, Modal, Pressable, ScrollView, StyleSheet, Text, View } from 'react-native';
import { useLocalSearchParams, useRouter } from 'expo-router';
import { api, ApiError } from '../../../src/api/client';
import type {
  LifeReportPayload,
  MeasurementZone,
  PredictionPayload,
  TireHistoryPayload,
  TireIncident,
  TireLifecycle,
  TireMovement,
} from '../../../src/api/types';
import { useAuth } from '../../../src/auth/AuthContext';
import { canManageAbm, canRetireOrRecap, canWrite } from '../../../src/auth/permissions';
import {
  INCIDENT_TYPE_LABEL,
  MOVEMENT_TYPE_LABEL,
  colors,
  radius,
  space,
  touchTarget,
  type,
} from '../../../src/theme';
import { TireStatusBadge } from '../../../src/ui/StatusBadge';
import { Card, Chip, ErrorState, Field, LoadingState, PrimaryButton, SectionLabel, StatusPill } from '../../../src/ui/primitives';

const CONFIDENCE_LABEL: Record<string, string> = { high: 'alta', medium: 'media', low: 'baja' };

const PREDICTION_STATUS: Record<string, { label: string; color: string }> = {
  critical: { label: 'Crítico', color: colors.danger },
  warn: { label: 'Atención', color: colors.warn },
  ok: { label: 'Normal', color: colors.ok },
  unknown: { label: 'Sin datos suficientes', color: colors.muted },
};

function formatDateTime(value?: string | null): string {
  if (!value) return '—';
  const d = new Date(value);
  if (Number.isNaN(d.getTime())) return value;
  return d.toLocaleDateString('es-AR', { day: '2-digit', month: '2-digit', year: 'numeric' });
}

function movementPlace(movement: TireMovement, side: 'from' | 'to'): string {
  const unit = side === 'from' ? movement.fromUnit : movement.toUnit;
  const position = side === 'from' ? movement.fromPosition : movement.toPosition;
  if (unit) return `${unit.plate}${position ? ` · ${position.code}` : ''}`;
  const baseId = side === 'from' ? movement.from_base_id : movement.to_base_id;
  return baseId ? 'Depósito' : '—';
}

function MovementRow({ movement }: { movement: TireMovement }) {
  const from = movementPlace(movement, 'from');
  const to = movementPlace(movement, 'to');
  return (
    <View style={styles.historyRow}>
      <Text style={styles.historyTitle}>{MOVEMENT_TYPE_LABEL[movement.type] ?? movement.type}</Text>
      <Text style={styles.historyMeta}>
        {formatDateTime(movement.occurred_at)} · {from} → {to}
        {movement.km_delta ? ` · ${movement.km_delta.toLocaleString()} km` : ''}
      </Text>
      {movement.notes ? <Text style={styles.historyNotes}>{movement.notes}</Text> : null}
    </View>
  );
}

function IncidentRow({ incident }: { incident: TireIncident }) {
  return (
    <View style={styles.historyRow}>
      <Text style={styles.historyTitle}>{INCIDENT_TYPE_LABEL[incident.type] ?? incident.type}</Text>
      <Text style={styles.historyMeta}>{formatDateTime(incident.occurred_at)}</Text>
      {incident.description ? <Text style={styles.historyNotes}>{incident.description}</Text> : null}
    </View>
  );
}

function LifecycleRow({ lifecycle }: { lifecycle: TireLifecycle }) {
  return (
    <View style={styles.historyRow}>
      <Text style={styles.historyTitle}>Vida {lifecycle.life_number}</Text>
      <Text style={styles.historyMeta}>
        {formatDateTime(lifecycle.started_at)} → {lifecycle.ended_at ? formatDateTime(lifecycle.ended_at) : 'En curso'}
      </Text>
    </View>
  );
}

function sortByOccurredDesc<T extends { occurred_at: string }>(items: T[]): T[] {
  return [...items].sort((a, b) => new Date(b.occurred_at).getTime() - new Date(a.occurred_at).getTime());
}

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
          <View style={{ gap: space.md }}>
            <Card>
              <SectionLabel>Movimientos ({history.movements?.length ?? 0})</SectionLabel>
              {history.movements?.length ? (
                sortByOccurredDesc(history.movements).map((m) => <MovementRow key={m.id} movement={m} />)
              ) : (
                <Text style={styles.sub}>Sin movimientos todavía.</Text>
              )}
            </Card>
            <Card>
              <SectionLabel>Incidentes ({history.incidents?.length ?? 0})</SectionLabel>
              {history.incidents?.length ? (
                sortByOccurredDesc(history.incidents).map((i) => <IncidentRow key={i.id} incident={i} />)
              ) : (
                <Text style={styles.sub}>Sin incidentes.</Text>
              )}
            </Card>
            <Card>
              <SectionLabel>Ciclos de vida ({history.lifecycles?.length ?? 0})</SectionLabel>
              {history.lifecycles?.length ? (
                history.lifecycles.map((l) => <LifecycleRow key={l.id} lifecycle={l} />)
              ) : (
                <Text style={styles.sub}>Sin ciclos registrados.</Text>
              )}
            </Card>
          </View>
        ) : tab === 'prediction' ? (
          <Card>
            {prediction ? (
              <>
                <View style={styles.headRow}>
                  <Text style={styles.title}>Pronóstico</Text>
                  <StatusPill
                    label={(PREDICTION_STATUS[prediction.status] ?? PREDICTION_STATUS.unknown).label}
                    color={(PREDICTION_STATUS[prediction.status] ?? PREDICTION_STATUS.unknown).color}
                  />
                </View>
                <Text style={styles.sub}>{prediction.narrative}</Text>
                {prediction.remaining_km !== null ? (
                  <Text style={styles.hint}>
                    {prediction.remaining_km.toLocaleString()} km estimados hasta {prediction.threshold_mm} mm ·
                    confianza {CONFIDENCE_LABEL[prediction.confidence] ?? prediction.confidence}
                  </Text>
                ) : null}
                {prediction.zones.length > 0 ? (
                  <View style={{ marginTop: space.md }}>
                    <SectionLabel>Por zona</SectionLabel>
                    {prediction.zones.map((z) => (
                      <Text key={z.name} style={styles.sub}>
                        {z.name}: {z.mm} mm{z.remaining_km !== null ? ` · ${z.remaining_km.toLocaleString()} km restantes` : ''}
                      </Text>
                    ))}
                  </View>
                ) : null}
              </>
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

      <IncidentModal
        visible={modal === 'incident'}
        busy={busy}
        canRecap={canRetire}
        onClose={() => setModal(null)}
        onConfirm={(incidentType, description, notes) =>
          runAction(
            () => api.createIncident(tireId, { type: incidentType, description, notes }),
            'Incidente registrado.',
          )
        }
      />

      <MeasurementModal
        visible={modal === 'measurement'}
        busy={busy}
        zones={history.measurement_zones ?? []}
        onClose={() => setModal(null)}
        onConfirm={(readings, notes) =>
          runAction(
            () => api.createMeasurement(tireId, { notes, readings }),
            'Medición registrada.',
          )
        }
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

/** Excluye RECAPADO salvo que el usuario pueda registrar recapados — mismo criterio que TireController::show() en la web. */
function IncidentModal({
  visible,
  busy,
  canRecap,
  onClose,
  onConfirm,
}: {
  visible: boolean;
  busy: boolean;
  canRecap: boolean;
  onClose: () => void;
  onConfirm: (type: string, description?: string, notes?: string) => void;
}) {
  const options = Object.keys(INCIDENT_TYPE_LABEL).filter((key) => key !== 'RECAPADO' || canRecap);
  const [selected, setSelected] = useState(options[0] ?? 'OTRA');
  const [description, setDescription] = useState('');
  const [notes, setNotes] = useState('');

  useEffect(() => {
    if (visible) {
      setSelected(options[0] ?? 'OTRA');
      setDescription('');
      setNotes('');
    }
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [visible]);

  return (
    <Modal visible={visible} animationType="slide" transparent onRequestClose={onClose}>
      <View style={styles.modalBackdrop}>
        <View style={styles.modalCard}>
          <Text style={styles.title}>Registrar incidente</Text>
          <SectionLabel>Tipo</SectionLabel>
          <View style={styles.chipRow}>
            {options.map((key) => (
              <Chip key={key} label={INCIDENT_TYPE_LABEL[key] ?? key} selected={selected === key} onPress={() => setSelected(key)} />
            ))}
          </View>
          <Field label="Descripción (opcional)" value={description} onChangeText={setDescription} />
          <Field label="Notas (opcional)" value={notes} onChangeText={setNotes} />
          <View style={{ flexDirection: 'row', gap: space.sm, marginTop: space.md }}>
            <View style={{ flex: 1 }}>
              <PrimaryButton title="Cancelar" onPress={onClose} variant="ghost" block />
            </View>
            <View style={{ flex: 1 }}>
              <PrimaryButton
                title="Guardar incidente"
                onPress={() => onConfirm(selected, description.trim() || undefined, notes.trim() || undefined)}
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

/**
 * Un campo de milímetros por cada zona de la medida de la cubierta — el
 * backend (MeasurementService::record) rechaza el guardado si falta la
 * lectura de alguna zona, así que se piden todas de entrada.
 */
function MeasurementModal({
  visible,
  busy,
  zones,
  onClose,
  onConfirm,
}: {
  visible: boolean;
  busy: boolean;
  zones: MeasurementZone[];
  onClose: () => void;
  onConfirm: (readings: Array<{ zone_id: number; millimeters: number }>, notes?: string) => void;
}) {
  const [values, setValues] = useState<Record<number, string>>({});
  const [notes, setNotes] = useState('');
  const [error, setError] = useState<string | null>(null);

  useEffect(() => {
    if (visible) {
      setValues({});
      setNotes('');
      setError(null);
    }
  }, [visible]);

  const submit = () => {
    const readings: Array<{ zone_id: number; millimeters: number }> = [];
    for (const zone of zones) {
      const raw = (values[zone.id] ?? '').trim();
      if (raw === '' || Number.isNaN(Number(raw))) {
        setError(`Falta la medición de ${zone.name}.`);
        return;
      }
      readings.push({ zone_id: zone.id, millimeters: Number(raw) });
    }
    setError(null);
    onConfirm(readings, notes.trim() || undefined);
  };

  return (
    <Modal visible={visible} animationType="slide" transparent onRequestClose={onClose}>
      <View style={styles.modalBackdrop}>
        <ScrollView style={styles.measurementModalScroll} contentContainerStyle={styles.modalCard}>
          <Text style={styles.title}>Registrar medición</Text>
          {zones.length === 0 ? (
            <Text style={styles.sub}>
              Esta cubierta no tiene una medida con zonas de profundidad cargadas — no se puede registrar la medición.
            </Text>
          ) : (
            <>
              <Text style={styles.sub}>Profundidad en cada zona de la banda (mm).</Text>
              {zones.map((zone) => (
                <Field
                  key={zone.id}
                  label={`${zone.name} (mm)`}
                  value={values[zone.id] ?? ''}
                  onChangeText={(v) => setValues((prev) => ({ ...prev, [zone.id]: v }))}
                  keyboardType="decimal-pad"
                />
              ))}
              <Field label="Notas (opcional)" value={notes} onChangeText={setNotes} />
            </>
          )}
          {error ? <Text style={styles.errorText}>{error}</Text> : null}
          <View style={{ flexDirection: 'row', gap: space.sm, marginTop: space.md }}>
            <View style={{ flex: 1 }}>
              <PrimaryButton title="Cancelar" onPress={onClose} variant="ghost" block />
            </View>
            <View style={{ flex: 1 }}>
              <PrimaryButton title="Guardar medición" onPress={submit} loading={busy} disabled={zones.length === 0} block />
            </View>
          </View>
        </ScrollView>
      </View>
    </Modal>
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
  historyRow: {
    paddingVertical: space.sm,
    borderTopWidth: 1,
    borderTopColor: colors.line,
    marginTop: space.sm,
  },
  historyTitle: { color: colors.ink, fontSize: type.bodyStrong, fontWeight: '700' },
  historyMeta: { color: colors.muted, fontSize: type.caption, marginTop: 2 },
  historyNotes: { color: colors.inkSoft, fontSize: type.caption, marginTop: 2, fontStyle: 'italic' },
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
  measurementModalScroll: { maxHeight: '85%' },
  errorText: { color: colors.danger, fontSize: type.body, marginTop: space.xs },
});
