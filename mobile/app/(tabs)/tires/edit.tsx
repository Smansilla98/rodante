import { useEffect, useMemo, useState } from 'react';
import { Alert, ScrollView, StyleSheet, Text, View } from 'react-native';
import { useLocalSearchParams, useRouter } from 'expo-router';
import { api, ApiError } from '../../../src/api/client';
import type { TireCatalogPayload, TireHistoryPayload } from '../../../src/api/types';
import { TIRE_CONDITION_LABEL, colors, space, type } from '../../../src/theme';
import { Card, Chip, ErrorState, Field, LoadingState, PrimaryButton, SectionLabel } from '../../../src/ui/primitives';

const CONDITIONS = ['NUEVA', 'NUEVA_USADA', 'USADA', 'RECAPADA', 'REPARADA'] as const;

export default function EditTireScreen() {
  const { id } = useLocalSearchParams<{ id: string }>();
  const tireId = Number(id);
  const router = useRouter();

  const [catalog, setCatalog] = useState<TireCatalogPayload | null>(null);
  const [history, setHistory] = useState<TireHistoryPayload | null>(null);
  const [loading, setLoading] = useState(true);
  const [loadError, setLoadError] = useState<string | null>(null);

  const [brandId, setBrandId] = useState<number | null>(null);
  const [modelId, setModelId] = useState<number | null>(null);
  const [sizeId, setSizeId] = useState<number | null>(null);
  const [dot, setDot] = useState('');
  const [condition, setCondition] = useState<(typeof CONDITIONS)[number]>('NUEVA');
  const [notes, setNotes] = useState('');
  const [saving, setSaving] = useState(false);

  useEffect(() => {
    void (async () => {
      try {
        const [c, h] = await Promise.all([api.tireCatalog(), api.tireHistory(tireId)]);
        setCatalog(c);
        setHistory(h);
        setBrandId(h.tire.tire_brand_id ?? null);
        setModelId(h.tire.tire_model_id ?? null);
        setSizeId(h.tire.tire_size_id ?? null);
        setDot(h.tire.dot ?? '');
        setCondition((h.tire.condition as (typeof CONDITIONS)[number]) ?? 'NUEVA');
        setNotes(h.tire.notes ?? '');
      } catch (e) {
        setLoadError(e instanceof ApiError ? e.message : 'No se pudo cargar el neumático');
      } finally {
        setLoading(false);
      }
    })();
  }, [tireId]);

  const models = useMemo(
    () => (catalog && brandId ? catalog.models.filter((m) => m.tire_brand_id === brandId) : []),
    [catalog, brandId],
  );
  const selectedModel = useMemo(() => models.find((m) => m.id === modelId) ?? null, [models, modelId]);
  const sizes = useMemo(
    () => (catalog && selectedModel ? catalog.sizes.filter((s) => selectedModel.size_ids.includes(s.id)) : []),
    [catalog, selectedModel],
  );

  const canSubmit = !!(brandId && modelId && sizeId) && !saving;

  const submit = async () => {
    if (!history || !brandId || !modelId || !sizeId) return;
    setSaving(true);
    try {
      await api.updateTire(tireId, {
        individual_number: history.tire.individual_number,
        tire_brand_id: brandId,
        tire_model_id: modelId,
        tire_size_id: sizeId,
        condition,
        dot: dot.trim() || undefined,
        notes: notes.trim() || undefined,
      });
      Alert.alert('Listo', 'Neumático actualizado.');
      router.replace(`/(tabs)/tires/${tireId}`);
    } catch (e) {
      Alert.alert('No se pudo guardar', e instanceof ApiError ? e.message : 'Error inesperado');
    } finally {
      setSaving(false);
    }
  };

  if (loading) return <LoadingState />;
  if (loadError || !catalog || !history) return <ErrorState message={loadError ?? 'No encontrado'} />;

  return (
    <View style={styles.root}>
      <ScrollView contentContainerStyle={{ padding: space.lg, gap: space.md }} keyboardShouldPersistTaps="handled">
        <Card>
          <SectionLabel>Nº{history.tire.individual_number}</SectionLabel>
          <Text style={styles.fieldLabel}>Marca</Text>
          <View style={styles.chipRow}>
            {catalog.brands.map((b) => (
              <Chip
                key={b.id}
                label={b.name}
                selected={brandId === b.id}
                onPress={() => {
                  setBrandId(b.id);
                  setModelId(null);
                  setSizeId(null);
                }}
              />
            ))}
          </View>

          {brandId ? (
            <>
              <Text style={styles.fieldLabel}>Modelo</Text>
              <View style={styles.chipRow}>
                {models.map((m) => (
                  <Chip
                    key={m.id}
                    label={m.name}
                    selected={modelId === m.id}
                    onPress={() => {
                      setModelId(m.id);
                      setSizeId(null);
                    }}
                  />
                ))}
              </View>
            </>
          ) : null}

          {modelId ? (
            <>
              <Text style={styles.fieldLabel}>Medida</Text>
              <View style={styles.chipRow}>
                {sizes.map((s) => (
                  <Chip key={s.id} label={s.alias ?? s.code} selected={sizeId === s.id} onPress={() => setSizeId(s.id)} />
                ))}
              </View>
            </>
          ) : null}

          <Field label="DOT (opcional)" value={dot} onChangeText={setDot} autoCapitalize="characters" />
        </Card>

        <Card>
          <SectionLabel>Condición</SectionLabel>
          <View style={styles.chipRow}>
            {CONDITIONS.map((c) => (
              <Chip key={c} label={TIRE_CONDITION_LABEL[c]} selected={condition === c} onPress={() => setCondition(c)} />
            ))}
          </View>
          <Text style={styles.hint}>
            Cambiar a Recapada acá no reemplaza pasar la cubierta por una orden de trabajo real — es solo para
            corregir la ficha.
          </Text>
        </Card>

        <Card>
          <SectionLabel>Observaciones</SectionLabel>
          <Field value={notes} onChangeText={setNotes} placeholder="Opcional" />
        </Card>

        <PrimaryButton title="Guardar cambios" onPress={() => void submit()} loading={saving} disabled={!canSubmit} block />
      </ScrollView>
    </View>
  );
}

const styles = StyleSheet.create({
  root: { flex: 1, backgroundColor: colors.page },
  fieldLabel: { color: colors.muted, fontSize: type.label, fontWeight: '600', marginTop: space.sm, marginBottom: space.xs },
  hint: { color: colors.muted, fontSize: type.caption, marginTop: space.xs },
  chipRow: { flexDirection: 'row', flexWrap: 'wrap', gap: space.xs },
});
