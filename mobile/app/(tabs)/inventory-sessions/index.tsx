import { useCallback, useEffect, useState } from 'react';
import { FlatList, Modal, Pressable, RefreshControl, StyleSheet, Text, View } from 'react-native';
import { useRouter } from 'expo-router';
import { useSafeAreaInsets } from 'react-native-safe-area-context';
import { api, ApiError } from '../../../src/api/client';
import type { Base, InventorySession } from '../../../src/api/types';
import { useAuth } from '../../../src/auth/AuthContext';
import { canWrite } from '../../../src/auth/permissions';
import { colors, radius, space, touchTarget, type } from '../../../src/theme';
import { PageHeader } from '../../../src/ui/PageHeader';
import { InventorySessionStatusBadge } from '../../../src/ui/StatusBadge';
import { Card, Chip, EmptyState, ErrorState, Field, LoadingState, PrimaryButton } from '../../../src/ui/primitives';

export default function InventorySessionsScreen() {
  const router = useRouter();
  const { user } = useAuth();
  const writeAllowed = canWrite(user?.role);
  const [sessions, setSessions] = useState<InventorySession[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [refreshing, setRefreshing] = useState(false);
  const [showForm, setShowForm] = useState(false);

  const load = useCallback(async () => {
    setError(null);
    try {
      const res = await api.inventorySessions(1);
      setSessions(res.data);
    } catch (e) {
      setError(e instanceof ApiError ? e.message : 'No se pudieron cargar los inventarios');
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
      <PageHeader title="Inventarios" subtitle="Sesiones de conteo por base" showBack />
      {writeAllowed ? (
        <View style={{ paddingHorizontal: space.lg, marginBottom: space.sm }}>
          <PrimaryButton title="Nuevo inventario" icon="add-circle-outline" onPress={() => setShowForm(true)} block />
        </View>
      ) : null}

      {loading ? (
        <LoadingState />
      ) : error ? (
        <ErrorState message={error} onRetry={load} />
      ) : sessions.length === 0 ? (
        <EmptyState title="Sin sesiones de inventario" />
      ) : (
        <FlatList
          data={sessions}
          keyExtractor={(s) => String(s.id)}
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
              onPress={() => router.push(`/(tabs)/inventory-sessions/${item.id}`)}
              accessibilityRole="button"
              accessibilityLabel={`Inventario ${item.number}`}
              accessibilityHint="Ver detalle del inventario"
            >
              <View style={styles.headRow}>
                <Text style={styles.rowTitle}>{item.number}</Text>
                <InventorySessionStatusBadge status={item.status} />
              </View>
              <Text style={styles.rowSub}>Base: {item.base?.name ?? '—'}</Text>
              <Text style={styles.rowSub}>
                Esperado {item.expected_count ?? '—'} · Encontrado {item.found_count ?? '—'} · Faltante{' '}
                {item.missing_count ?? '—'}
              </Text>
            </Pressable>
          )}
        />
      )}

      <NewSessionModal
        visible={showForm}
        onClose={() => setShowForm(false)}
        onCreated={(session) => {
          setShowForm(false);
          router.push(`/(tabs)/inventory-sessions/${session.id}`);
        }}
      />
    </View>
  );
}

function NewSessionModal({
  visible,
  onClose,
  onCreated,
}: {
  visible: boolean;
  onClose: () => void;
  onCreated: (session: InventorySession) => void;
}) {
  const [bases, setBases] = useState<Base[]>([]);
  const [baseId, setBaseId] = useState<number | null>(null);
  const [notes, setNotes] = useState('');
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const insets = useSafeAreaInsets();

  useEffect(() => {
    if (!visible) return;
    setBaseId(null);
    setNotes('');
    setError(null);
    void api
      .bases()
      .then((b) => {
        setBases(b);
        if (b.length === 1) setBaseId(b[0].id);
      })
      .catch(() => setBases([]));
  }, [visible]);

  const submit = async () => {
    if (!baseId) {
      setError('Elegí una base.');
      return;
    }
    setBusy(true);
    setError(null);
    try {
      const session = await api.createInventorySession(baseId, notes.trim() || undefined);
      onCreated(session);
    } catch (e) {
      setError(e instanceof ApiError ? e.message : 'No se pudo abrir el inventario');
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
          <Text style={styles.rowTitle}>Nuevo inventario</Text>
          <Text style={styles.rowSub}>Se toma una foto de lo esperado en la base al momento de abrir.</Text>
          <Text style={styles.fieldLabel}>Base</Text>
          <View style={styles.chipRow}>
            {bases.map((b) => (
              <Chip key={b.id} label={b.name} selected={baseId === b.id} onPress={() => setBaseId(b.id)} />
            ))}
          </View>
          <Field label="Notas (opcional)" value={notes} onChangeText={setNotes} />
          {error ? <Text style={styles.error}>{error}</Text> : null}
          <View style={{ flexDirection: 'row', gap: space.sm, marginTop: space.md }}>
            <View style={{ flex: 1 }}>
              <PrimaryButton title="Cancelar" onPress={onClose} variant="ghost" block />
            </View>
            <View style={{ flex: 1 }}>
              <PrimaryButton title="Abrir inventario" onPress={() => void submit()} loading={busy} disabled={!baseId} block />
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
  modalBackdrop: { flex: 1, backgroundColor: 'rgba(0,0,0,0.6)', justifyContent: 'flex-end' },
  modalCard: {
    backgroundColor: colors.card,
    borderTopLeftRadius: 20,
    borderTopRightRadius: 20,
    padding: space.lg,
    gap: space.xs,
  },
});
