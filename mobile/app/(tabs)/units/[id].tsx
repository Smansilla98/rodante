import { useCallback, useEffect, useMemo, useState } from 'react';
import { Alert, Modal, Pressable, ScrollView, StyleSheet, Text, View } from 'react-native';
import { useLocalSearchParams, useRouter } from 'expo-router';
import { api, ApiError } from '../../../src/api/client';
import type {
  MovementReason,
  PositionCandidate,
  UnitLayoutEntry,
  UnitLayoutPayload,
} from '../../../src/api/types';
import { useAuth } from '../../../src/auth/AuthContext';
import { canWrite } from '../../../src/auth/permissions';
import { colors, radius, space, touchTarget, type, UNIT_DUTY_LABEL } from '../../../src/theme';
import { UnitStatusBadge } from '../../../src/ui/StatusBadge';
import {
  Card,
  Chip,
  ErrorState,
  Field,
  Icon,
  InfoRow,
  LoadingState,
  PrimaryButton,
  SectionLabel,
} from '../../../src/ui/primitives';

/** Qué panel se muestra dentro de la hoja de acciones de una posición. */
type Flow = 'actions' | 'install' | 'change' | 'move' | 'retire' | null;

/** Agrupa el layout por eje (para el mapa principal) y separa las posiciones auxiliares. */
function groupLayout(layout: UnitLayoutEntry[]) {
  const main = layout.filter((e) => !e.position.is_spare);
  const aux = layout.filter((e) => e.position.is_spare);
  const byAxle = new Map<number, UnitLayoutEntry[]>();
  main.forEach((entry) => {
    const n = entry.position.axle_number ?? 0;
    if (!byAxle.has(n)) byAxle.set(n, []);
    byAxle.get(n)!.push(entry);
  });
  const axles = [...byAxle.entries()]
    .sort(([a], [b]) => a - b)
    .map(([axle, entries]) => ({
      axle,
      entries: [...entries].sort((a, b) => (a.position.sort_order ?? 0) - (b.position.sort_order ?? 0)),
    }));
  return { axles, aux };
}

export default function UnitDetailScreen() {
  const { id } = useLocalSearchParams<{ id: string }>();
  const router = useRouter();
  const unitId = Number(id);
  const { user } = useAuth();
  const writeAllowed = canWrite(user?.role);

  const [payload, setPayload] = useState<UnitLayoutPayload | null>(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [selected, setSelected] = useState<UnitLayoutEntry | null>(null);
  const [flow, setFlow] = useState<Flow>(null);
  const [busy, setBusy] = useState(false);

  const load = useCallback(async () => {
    setError(null);
    try {
      setPayload(await api.unitLayout(unitId));
    } catch (e) {
      setError(e instanceof ApiError ? e.message : 'No se pudo cargar la unidad');
    } finally {
      setLoading(false);
    }
  }, [unitId]);

  useEffect(() => {
    void load();
  }, [load]);

  const { axles, aux } = useMemo(() => groupLayout(payload?.layout ?? []), [payload]);

  if (loading) return <LoadingState />;
  if (error || !payload) return <ErrorState message={error ?? 'No encontrada'} onRetry={load} />;

  const unit = payload.unit;

  const openPosition = (entry: UnitLayoutEntry) => {
    if (!writeAllowed) return;
    setSelected(entry);
    setFlow(entry.tire ? 'actions' : 'install');
  };

  const closeSheet = () => {
    setSelected(null);
    setFlow(null);
  };

  const finish = async (message: string) => {
    closeSheet();
    await load();
    Alert.alert('Listo', message);
  };

  const fail = (e: unknown) =>
    Alert.alert('No se pudo completar', e instanceof ApiError ? e.message : 'Error inesperado');

  /** Instalar en una posición vacía, o cambiar (retira la actual + instala la nueva) en una ocupada. */
  const submitInstallOrChange = async (newTireId: number, odometer?: number) => {
    if (!selected) return;
    setBusy(true);
    try {
      await api.operateUnit(unitId, {
        odometer,
        removals: selected.tire
          ? [{ tire_id: selected.tire.id, position_id: selected.position.id }]
          : undefined,
        installations: [{ tire_id: newTireId, position_id: selected.position.id }],
      });
      await finish(
        selected.tire
          ? `Se cambió la cubierta de la posición ${selected.position.code}.`
          : `Cubierta instalada en la posición ${selected.position.code}.`,
      );
    } catch (e) {
      fail(e);
    } finally {
      setBusy(false);
    }
  };

  const submitRetire = async (
    reasonId: number | undefined,
    destination: string | undefined,
    notes: string | undefined,
    odometer?: number,
  ) => {
    if (!selected?.tire) return;
    setBusy(true);
    try {
      await api.operateUnit(unitId, {
        odometer,
        notes,
        removals: [
          { tire_id: selected.tire.id, position_id: selected.position.id, reason_id: reasonId, destination },
        ],
      });
      await finish(`Cubierta retirada de la posición ${selected.position.code}.`);
    } catch (e) {
      fail(e);
    } finally {
      setBusy(false);
    }
  };

  /** Mover a una posición vacía, o intercambiar si el destino ya tiene otra cubierta. */
  const submitMove = async (target: UnitLayoutEntry, odometer?: number) => {
    if (!selected?.tire) return;
    setBusy(true);
    try {
      const tireA = selected.tire;
      const tireB = target.tire;
      await api.operateUnit(unitId, {
        odometer,
        removals: tireB
          ? [
              { tire_id: tireA.id, position_id: selected.position.id },
              { tire_id: tireB.id, position_id: target.position.id },
            ]
          : [{ tire_id: tireA.id, position_id: selected.position.id }],
        installations: tireB
          ? [
              { tire_id: tireA.id, position_id: target.position.id },
              { tire_id: tireB.id, position_id: selected.position.id },
            ]
          : [{ tire_id: tireA.id, position_id: target.position.id }],
      });
      await finish(
        tireB
          ? `Se intercambiaron las cubiertas de ${selected.position.code} y ${target.position.code}.`
          : `Cubierta movida de ${selected.position.code} a ${target.position.code}.`,
      );
    } catch (e) {
      fail(e);
    } finally {
      setBusy(false);
    }
  };

  return (
    <View style={styles.root}>
      <ScrollView contentContainerStyle={{ padding: space.lg, gap: space.md }}>
        <Card>
          <View style={styles.headRow}>
            <Text style={styles.title}>{unit.plate}</Text>
            <UnitStatusBadge status={unit.status} />
          </View>
          <View style={{ marginTop: space.sm }}>
            <InfoRow icon="car-outline" label="Tipo" value={unit.type?.name ?? 'Sin tipo'} />
            <InfoRow icon="navigate-outline" label="Uso" value={UNIT_DUTY_LABEL[unit.duty] ?? unit.duty} />
            {unit.base?.name ? <InfoRow icon="location-outline" label="Base" value={unit.base.name} /> : null}
            {unit.fleet?.name ? <InfoRow icon="albums-outline" label="Flota" value={unit.fleet.name} /> : null}
            {unit.type?.has_odometer ? (
              <InfoRow
                icon="speedometer-outline"
                label="Odómetro"
                value={`${unit.current_odometer.toLocaleString()} km`}
              />
            ) : null}
          </View>
        </Card>

        <Card>
          <SectionLabel>Mapa de cubiertas</SectionLabel>
          {axles.map(({ axle, entries }) => (
            <View key={axle} style={styles.axleGroup}>
              <Text style={styles.axleLabel}>Eje {axle}</Text>
              <View style={styles.axleRow}>
                {entries.map((entry) => (
                  <PositionBox
                    key={entry.position.id}
                    entry={entry}
                    onPress={() => openPosition(entry)}
                    disabled={!writeAllowed}
                  />
                ))}
              </View>
            </View>
          ))}
          {!writeAllowed ? <Text style={styles.sub}>Tu rol no puede operar posiciones.</Text> : null}
        </Card>

        {aux.length > 0 ? (
          <Card>
            <SectionLabel>Auxilio</SectionLabel>
            <View style={styles.axleRow}>
              {aux.map((entry) => (
                <PositionBox
                  key={entry.position.id}
                  entry={entry}
                  onPress={() => openPosition(entry)}
                  disabled={!writeAllowed}
                />
              ))}
            </View>
          </Card>
        ) : null}
      </ScrollView>

      <PositionSheet
        visible={!!selected}
        entry={selected}
        flow={flow}
        busy={busy}
        unitId={unitId}
        allEntries={payload.layout}
        onClose={closeSheet}
        onChangeFlow={setFlow}
        onViewDetail={() => {
          const tireId = selected?.tire?.id;
          if (!tireId) return;
          closeSheet();
          router.push(`/(tabs)/tires/${tireId}`);
        }}
        onInstallOrChange={submitInstallOrChange}
        onRetire={submitRetire}
        onMove={submitMove}
      />
    </View>
  );
}

function PositionBox({
  entry,
  onPress,
  disabled,
}: {
  entry: UnitLayoutEntry;
  onPress: () => void;
  disabled?: boolean;
}) {
  const occupied = !!entry.tire;
  const tireLabel = entry.tire
    ? [entry.tire.brand?.name, entry.tire.model?.name].filter(Boolean).join(' ')
    : null;
  return (
    <Pressable
      onPress={onPress}
      disabled={disabled}
      style={[styles.posBox, occupied ? styles.posBoxFilled : styles.posBoxEmpty]}
      accessibilityRole="button"
      accessibilityLabel={`Posición ${entry.position.code}, ${
        occupied ? `ocupada por neumático número ${entry.tire?.individual_number}` : 'vacía'
      }`}
      accessibilityHint={
        disabled ? undefined : occupied ? 'Ver acciones para esta cubierta' : 'Instalar una cubierta en esta posición'
      }
    >
      <Text style={styles.posCode}>{entry.position.code}</Text>
      <Text style={styles.posStatus} numberOfLines={1}>
        {occupied ? `Nº${entry.tire?.individual_number}` : 'Vacío'}
      </Text>
      {tireLabel ? (
        <Text style={styles.posMeta} numberOfLines={1}>
          {tireLabel}
        </Text>
      ) : null}
    </Pressable>
  );
}

function PositionSheet({
  visible,
  entry,
  flow,
  busy,
  unitId,
  allEntries,
  onClose,
  onChangeFlow,
  onViewDetail,
  onInstallOrChange,
  onRetire,
  onMove,
}: {
  visible: boolean;
  entry: UnitLayoutEntry | null;
  flow: Flow;
  busy: boolean;
  unitId: number;
  allEntries: UnitLayoutEntry[];
  onClose: () => void;
  onChangeFlow: (f: Flow) => void;
  onViewDetail: () => void;
  onInstallOrChange: (tireId: number, odometer?: number) => void;
  onRetire: (
    reasonId: number | undefined,
    destination: string | undefined,
    notes: string | undefined,
    odometer?: number,
  ) => void;
  onMove: (target: UnitLayoutEntry, odometer?: number) => void;
}) {
  return (
    <Modal visible={visible} animationType="slide" transparent onRequestClose={onClose}>
      <View style={styles.modalBackdrop}>
        <View style={styles.modalCard}>
          {entry ? (
            <ScrollView keyboardShouldPersistTaps="handled" contentContainerStyle={{ gap: space.xs }}>
              {flow === 'actions' && entry.tire ? (
                <ActionsPanel entry={entry} onClose={onClose} onChangeFlow={onChangeFlow} onViewDetail={onViewDetail} />
              ) : null}
              {flow === 'install' || flow === 'change' ? (
                <CandidateSearch
                  unitId={unitId}
                  positionId={entry.position.id}
                  currentTireNumber={entry.tire?.individual_number}
                  title={flow === 'change' ? 'Cambiar cubierta' : 'Instalar cubierta'}
                  confirmLabel={flow === 'change' ? 'Confirmar cambio' : 'Instalar'}
                  busy={busy}
                  onConfirm={onInstallOrChange}
                  onBack={entry.tire ? () => onChangeFlow('actions') : onClose}
                />
              ) : null}
              {flow === 'move' && entry.tire ? (
                <MovePicker
                  entry={entry}
                  allEntries={allEntries}
                  busy={busy}
                  onConfirm={onMove}
                  onBack={() => onChangeFlow('actions')}
                />
              ) : null}
              {flow === 'retire' && entry.tire ? (
                <RetirePanel entry={entry} busy={busy} onConfirm={onRetire} onBack={() => onChangeFlow('actions')} />
              ) : null}
            </ScrollView>
          ) : null}
        </View>
      </View>
    </Modal>
  );
}

function ActionsPanel({
  entry,
  onClose,
  onChangeFlow,
  onViewDetail,
}: {
  entry: UnitLayoutEntry;
  onClose: () => void;
  onChangeFlow: (f: Flow) => void;
  onViewDetail: () => void;
}) {
  const tireLabel = [entry.tire?.brand?.name, entry.tire?.model?.name].filter(Boolean).join(' ');
  return (
    <View style={{ gap: space.sm }}>
      <Text style={styles.title}>Posición {entry.position.code}</Text>
      <Text style={styles.sub}>
        Cubierta actual: Nº{entry.tire?.individual_number}
        {tireLabel ? ` · ${tireLabel}` : ''}
      </Text>
      <View style={{ gap: space.sm, marginTop: space.sm }}>
        <PrimaryButton
          title="Cambiar cubierta"
          icon="swap-horizontal-outline"
          onPress={() => onChangeFlow('change')}
          variant="outline"
          block
        />
        <PrimaryButton
          title="Mover / Intercambiar"
          icon="shuffle-outline"
          onPress={() => onChangeFlow('move')}
          variant="outline"
          block
        />
        <PrimaryButton
          title="Ver detalle"
          icon="document-text-outline"
          onPress={onViewDetail}
          variant="outline"
          block
        />
        <PrimaryButton
          title="Retirar"
          icon="remove-circle-outline"
          onPress={() => onChangeFlow('retire')}
          variant="danger"
          block
        />
      </View>
      <View style={{ marginTop: space.sm }}>
        <PrimaryButton title="Cerrar" onPress={onClose} variant="ghost" block />
      </View>
    </View>
  );
}

function BackRow({ label, onPress }: { label: string; onPress: () => void }) {
  return (
    <Pressable onPress={onPress} style={styles.backRow} accessibilityRole="button" accessibilityLabel={label}>
      <Icon name="chevron-back-outline" size={20} color={colors.muted} />
      <Text style={styles.backText}>{label}</Text>
    </Pressable>
  );
}

function CandidateSearch({
  unitId,
  positionId,
  currentTireNumber,
  title,
  confirmLabel,
  busy,
  onConfirm,
  onBack,
}: {
  unitId: number;
  positionId: number;
  currentTireNumber?: number;
  title: string;
  confirmLabel: string;
  busy: boolean;
  onConfirm: (tireId: number, odometer?: number) => void;
  onBack: () => void;
}) {
  const [query, setQuery] = useState('');
  const [candidates, setCandidates] = useState<PositionCandidate[]>([]);
  const [loadingList, setLoadingList] = useState(true);
  const [listError, setListError] = useState<string | null>(null);
  const [picked, setPicked] = useState<PositionCandidate | null>(null);
  const [odometer, setOdometer] = useState('');

  const fetchCandidates = useCallback(
    async (q: string) => {
      setLoadingList(true);
      setListError(null);
      try {
        const res = await api.positionCandidates(unitId, positionId, q || undefined);
        setCandidates(res.data);
      } catch (e) {
        setListError(e instanceof ApiError ? e.message : 'No se pudieron buscar cubiertas');
      } finally {
        setLoadingList(false);
      }
    },
    [unitId, positionId],
  );

  useEffect(() => {
    const t = setTimeout(() => void fetchCandidates(query), query ? 350 : 0);
    return () => clearTimeout(t);
  }, [query, fetchCandidates]);

  if (picked) {
    return (
      <View style={{ gap: space.sm }}>
        <BackRow label="Volver a la búsqueda" onPress={() => setPicked(null)} />
        <Text style={styles.title}>{title}</Text>
        {currentTireNumber ? <Text style={styles.sub}>Actual: Nº{currentTireNumber}</Text> : null}
        <Text style={styles.sub}>
          Nueva: Nº{picked.individual_number}
          {picked.brand ? ` · ${picked.brand}` : ''}
          {picked.model ? ` ${picked.model}` : ''}
        </Text>
        <Field
          label="Odómetro actual (opcional)"
          keyboardType="number-pad"
          value={odometer}
          onChangeText={setOdometer}
          placeholder="Dejalo vacío si no lo tenés a mano"
        />
        <View style={{ flexDirection: 'row', gap: space.sm, marginTop: space.md }}>
          <View style={{ flex: 1 }}>
            <PrimaryButton title="Cancelar" onPress={onBack} variant="ghost" block />
          </View>
          <View style={{ flex: 1 }}>
            <PrimaryButton
              title={confirmLabel}
              onPress={() => onConfirm(picked.id, odometer.trim() ? Number(odometer.trim()) : undefined)}
              loading={busy}
              block
            />
          </View>
        </View>
      </View>
    );
  }

  return (
    <View style={{ gap: space.sm }}>
      <BackRow label="Volver" onPress={onBack} />
      <Text style={styles.title}>{title}</Text>
      <Field value={query} onChangeText={setQuery} placeholder="Buscar por número, marca o modelo" autoCapitalize="none" />
      {listError ? <Text style={styles.errorText}>{listError}</Text> : null}
      {loadingList ? (
        <Text style={styles.sub}>Buscando…</Text>
      ) : candidates.length === 0 ? (
        <Text style={styles.sub}>No hay cubiertas compatibles disponibles.</Text>
      ) : (
        <ScrollView style={{ maxHeight: 260 }} keyboardShouldPersistTaps="handled">
          {candidates.map((c) => (
            <Pressable
              key={c.id}
              onPress={() => setPicked(c)}
              style={styles.candidateRow}
              accessibilityRole="button"
              accessibilityLabel={`Elegir neumático Nº${c.individual_number}`}
            >
              <View style={{ flex: 1 }}>
                <Text style={styles.candidateTitle}>Nº{c.individual_number}</Text>
                <Text style={styles.sub}>{[c.brand, c.model, c.size].filter(Boolean).join(' · ')}</Text>
              </View>
              <Icon name="chevron-forward-outline" size={20} color={colors.muted} />
            </Pressable>
          ))}
        </ScrollView>
      )}
    </View>
  );
}

function MovePicker({
  entry,
  allEntries,
  busy,
  onConfirm,
  onBack,
}: {
  entry: UnitLayoutEntry;
  allEntries: UnitLayoutEntry[];
  busy: boolean;
  onConfirm: (target: UnitLayoutEntry, odometer?: number) => void;
  onBack: () => void;
}) {
  const [target, setTarget] = useState<UnitLayoutEntry | null>(null);
  const [odometer, setOdometer] = useState('');
  const others = allEntries.filter((e) => e.position.id !== entry.position.id);

  if (target) {
    const swap = !!target.tire;
    return (
      <View style={{ gap: space.sm }}>
        <BackRow label="Elegir otra posición" onPress={() => setTarget(null)} />
        <Text style={styles.title}>{swap ? 'Intercambiar cubiertas' : 'Mover cubierta'}</Text>
        {swap ? (
          <>
            <Text style={styles.sub}>Esta posición ya tiene una cubierta — se van a intercambiar.</Text>
            <Text style={styles.sub}>
              {entry.position.code} → {target.position.code} (Nº{entry.tire?.individual_number})
            </Text>
            <Text style={styles.sub}>
              {target.position.code} → {entry.position.code} (Nº{target.tire?.individual_number})
            </Text>
          </>
        ) : (
          <Text style={styles.sub}>
            Nº{entry.tire?.individual_number}: {entry.position.code} → {target.position.code}
          </Text>
        )}
        <Field
          label="Odómetro actual (opcional)"
          keyboardType="number-pad"
          value={odometer}
          onChangeText={setOdometer}
          placeholder="Dejalo vacío si no lo tenés a mano"
        />
        <View style={{ flexDirection: 'row', gap: space.sm, marginTop: space.md }}>
          <View style={{ flex: 1 }}>
            <PrimaryButton title="Cancelar" onPress={onBack} variant="ghost" block />
          </View>
          <View style={{ flex: 1 }}>
            <PrimaryButton
              title={swap ? 'Confirmar intercambio' : 'Confirmar movimiento'}
              onPress={() => onConfirm(target, odometer.trim() ? Number(odometer.trim()) : undefined)}
              loading={busy}
              block
            />
          </View>
        </View>
      </View>
    );
  }

  return (
    <View style={{ gap: space.sm }}>
      <BackRow label="Volver" onPress={onBack} />
      <Text style={styles.title}>Mover Nº{entry.tire?.individual_number}</Text>
      <Text style={styles.sub}>Elegí la posición destino. Si ya tiene una cubierta, se intercambian.</Text>
      <ScrollView style={{ maxHeight: 280 }}>
        <View style={styles.axleRow}>
          {others.map((e) => (
            <PositionBox key={e.position.id} entry={e} onPress={() => setTarget(e)} />
          ))}
        </View>
      </ScrollView>
    </View>
  );
}

function RetirePanel({
  entry,
  busy,
  onConfirm,
  onBack,
}: {
  entry: UnitLayoutEntry;
  busy: boolean;
  onConfirm: (
    reasonId: number | undefined,
    destination: string | undefined,
    notes: string | undefined,
    odometer?: number,
  ) => void;
  onBack: () => void;
}) {
  const [reasons, setReasons] = useState<MovementReason[]>([]);
  const [reasonId, setReasonId] = useState<number | null>(null);
  const [destination, setDestination] = useState('');
  const [notes, setNotes] = useState('');
  const [odometer, setOdometer] = useState('');

  useEffect(() => {
    void api
      .movementReasons('RETIRO')
      .then(setReasons)
      .catch(() => setReasons([]));
  }, []);

  const submit = () => {
    Alert.alert(
      'Retirar cubierta',
      `Vas a retirar el neumático Nº${entry.tire?.individual_number} de la posición ${entry.position.code}.`,
      [
        { text: 'Cancelar', style: 'cancel' },
        {
          text: 'Sí, retirar',
          onPress: () =>
            onConfirm(
              reasonId ?? undefined,
              destination || undefined,
              notes || undefined,
              odometer.trim() ? Number(odometer.trim()) : undefined,
            ),
        },
      ],
    );
  };

  return (
    <View style={{ gap: space.sm }}>
      <BackRow label="Volver" onPress={onBack} />
      <Text style={styles.title}>Retirar Nº{entry.tire?.individual_number}</Text>
      {reasons.length > 0 ? (
        <View>
          <Text style={styles.fieldLabel}>Motivo (opcional)</Text>
          <View style={styles.chipRow}>
            {reasons.map((r) => (
              <Chip
                key={r.id}
                label={r.name}
                selected={reasonId === r.id}
                onPress={() => setReasonId(reasonId === r.id ? null : r.id)}
              />
            ))}
          </View>
        </View>
      ) : null}
      <Field label="¿A dónde va? (opcional)" value={destination} onChangeText={setDestination} placeholder="Ej: depósito, recapado" />
      <Field
        label="Odómetro actual (opcional)"
        keyboardType="number-pad"
        value={odometer}
        onChangeText={setOdometer}
        placeholder="Dejalo vacío si no lo tenés a mano"
      />
      <Field label="Notas (opcional)" value={notes} onChangeText={setNotes} />
      <View style={{ flexDirection: 'row', gap: space.sm, marginTop: space.md }}>
        <View style={{ flex: 1 }}>
          <PrimaryButton title="Cancelar" onPress={onBack} variant="ghost" block />
        </View>
        <View style={{ flex: 1 }}>
          <PrimaryButton title="Retirar" onPress={submit} loading={busy} variant="danger" block />
        </View>
      </View>
    </View>
  );
}

const styles = StyleSheet.create({
  root: { flex: 1, backgroundColor: colors.page },
  headRow: { flexDirection: 'row', justifyContent: 'space-between', alignItems: 'center' },
  title: { color: colors.ink, fontSize: type.title, fontWeight: '700' },
  sub: { color: colors.muted, fontSize: type.caption, marginTop: space.xs },
  fieldLabel: { color: colors.muted, marginBottom: 8, fontSize: type.label, fontWeight: '600' },
  errorText: { color: colors.danger, fontSize: type.caption, fontWeight: '600' },
  chipRow: { flexDirection: 'row', flexWrap: 'wrap', gap: space.xs },
  axleGroup: { marginTop: space.sm },
  axleLabel: {
    color: colors.muted,
    fontSize: type.label,
    fontWeight: '700',
    marginBottom: space.xs,
    textAlign: 'center',
  },
  axleRow: { flexDirection: 'row', flexWrap: 'wrap', gap: space.sm, justifyContent: 'center' },
  backRow: { flexDirection: 'row', alignItems: 'center', gap: 4, alignSelf: 'flex-start', minHeight: 36 },
  backText: { color: colors.muted, fontSize: type.label, fontWeight: '600' },
  candidateRow: {
    flexDirection: 'row',
    alignItems: 'center',
    minHeight: touchTarget.row,
    borderBottomWidth: 1,
    borderBottomColor: colors.line,
    paddingVertical: space.sm,
    gap: space.sm,
  },
  candidateTitle: { color: colors.ink, fontSize: type.bodyStrong, fontWeight: '700' },
  posBox: {
    flexBasis: '47%',
    flexGrow: 1,
    minHeight: touchTarget.row,
    borderRadius: radius.lg,
    borderWidth: 1,
    padding: space.sm,
    alignItems: 'center',
    justifyContent: 'center',
    gap: 4,
  },
  posCode: { fontSize: type.bodyStrong, fontWeight: '700', color: colors.ink },
  posStatus: { fontSize: type.caption, color: colors.inkSoft },
  posMeta: { fontSize: type.caption, color: colors.muted },
  posBoxFilled: { backgroundColor: colors.primarySoft, borderColor: colors.primary },
  posBoxEmpty: { backgroundColor: colors.card2, borderColor: colors.line },
  modalBackdrop: { flex: 1, backgroundColor: 'rgba(0,0,0,0.6)', justifyContent: 'flex-end' },
  modalCard: {
    backgroundColor: colors.card,
    borderTopLeftRadius: 20,
    borderTopRightRadius: 20,
    padding: space.lg,
    maxHeight: '85%',
  },
});
