import { useEffect, useMemo, useState } from 'react';
import { Alert, ScrollView, StyleSheet, Text, View } from 'react-native';
import { useRouter } from 'expo-router';
import { api, ApiError } from '../../../src/api/client';
import type { Base, TireCatalogPayload } from '../../../src/api/types';
import { colors, space, type } from '../../../src/theme';
import { Card, Chip, Field, LoadingState, PrimaryButton, SectionLabel } from '../../../src/ui/primitives';

type Origin = 'compra' | 'puesta_a_punto';

export default function NewTireScreen() {
  const router = useRouter();
  const [catalog, setCatalog] = useState<TireCatalogPayload | null>(null);
  const [bases, setBases] = useState<Base[]>([]);
  const [loading, setLoading] = useState(true);
  const [loadError, setLoadError] = useState<string | null>(null);

  const [brandId, setBrandId] = useState<number | null>(null);
  const [modelId, setModelId] = useState<number | null>(null);
  const [sizeId, setSizeId] = useState<number | null>(null);
  const [dot, setDot] = useState('');
  const [origin, setOrigin] = useState<Origin>('compra');
  const [supplierId, setSupplierId] = useState<number | null>(null);
  const [baseId, setBaseId] = useState<number | null>(null);
  const [showMore, setShowMore] = useState(false);
  const [cost, setCost] = useState('');
  const [notes, setNotes] = useState('');
  const [saving, setSaving] = useState(false);

  useEffect(() => {
    void (async () => {
      try {
        const [c, b] = await Promise.all([api.tireCatalog(), api.bases()]);
        setCatalog(c);
        setBases(b);
        if (b.length === 1) setBaseId(b[0].id);
        if (c.suppliers.length === 1) setSupplierId(c.suppliers[0].id);
      } catch (e) {
        setLoadError(e instanceof ApiError ? e.message : 'No se pudieron cargar los catálogos');
      } finally {
        setLoading(false);
      }
    })();
  }, []);

  const models = useMemo(
    () => (catalog && brandId ? catalog.models.filter((m) => m.tire_brand_id === brandId) : []),
    [catalog, brandId],
  );
  const selectedModel = useMemo(() => models.find((m) => m.id === modelId) ?? null, [models, modelId]);
  const sizes = useMemo(
    () => (catalog && selectedModel ? catalog.sizes.filter((s) => selectedModel.size_ids.includes(s.id)) : []),
    [catalog, selectedModel],
  );

  const canSubmit = !!(brandId && modelId && sizeId && supplierId && baseId) && !saving;

  const submit = async () => {
    if (!brandId || !modelId || !sizeId || !supplierId || !baseId) {
      Alert.alert('Faltan datos', 'Completá marca, modelo, medida, proveedor y base antes de continuar.');
      return;
    }
    setSaving(true);
    try {
      const tire = await api.createTire({
        tire_brand_id: brandId,
        tire_model_id: modelId,
        tire_size_id: sizeId,
        supplier_id: supplierId,
        base_id: baseId,
        dot: dot.trim() || undefined,
        unit_cost: origin === 'compra' && cost.trim() ? Number(cost.trim()) : undefined,
        notes: notes.trim() || undefined,
      });
      Alert.alert('Listo', `Neumático Nº${tire.individual_number} creado.`);
      router.replace(`/(tabs)/tires/${tire.id}`);
    } catch (e) {
      Alert.alert('No se pudo crear', e instanceof ApiError ? e.message : 'Error inesperado');
    } finally {
      setSaving(false);
    }
  };

  if (loading) return <LoadingState />;
  if (loadError || !catalog) {
    return (
      <View style={styles.root}>
        <Card>
          <Text style={styles.sub}>{loadError ?? 'No se pudo cargar'}</Text>
        </Card>
      </View>
    );
  }

  return (
    <View style={styles.root}>
      <ScrollView contentContainerStyle={{ padding: space.lg, gap: space.md }} keyboardShouldPersistTaps="handled">
        <Card>
          <SectionLabel>Datos del neumático</SectionLabel>
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
              {models.length === 0 ? (
                <Text style={styles.sub}>Esta marca no tiene modelos activos.</Text>
              ) : (
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
              )}
            </>
          ) : null}

          {modelId ? (
            <>
              <Text style={styles.fieldLabel}>Medida</Text>
              {sizes.length === 0 ? (
                <Text style={styles.sub}>Este modelo no tiene medidas compatibles cargadas.</Text>
              ) : (
                <View style={styles.chipRow}>
                  {sizes.map((s) => (
                    <Chip key={s.id} label={s.alias ?? s.code} selected={sizeId === s.id} onPress={() => setSizeId(s.id)} />
                  ))}
                </View>
              )}
            </>
          ) : null}

          <Field label="DOT (opcional)" value={dot} onChangeText={setDot} autoCapitalize="characters" placeholder="Solo si vas a cargar 1 unidad" />
        </Card>

        <Card>
          <SectionLabel>Origen</SectionLabel>
          <View style={styles.chipRow}>
            <Chip label="Compra nueva" selected={origin === 'compra'} onPress={() => setOrigin('compra')} />
            <Chip
              label="Puesta a punto (ya la tenía)"
              selected={origin === 'puesta_a_punto'}
              onPress={() => setOrigin('puesta_a_punto')}
            />
          </View>
          {origin === 'puesta_a_punto' ? (
            <Text style={styles.sub}>Carga manual — no genera costo, ya estaba comprada.</Text>
          ) : null}

          <Text style={styles.fieldLabel}>Proveedor</Text>
          <View style={styles.chipRow}>
            {catalog.suppliers.map((s) => (
              <Chip key={s.id} label={s.name} selected={supplierId === s.id} onPress={() => setSupplierId(s.id)} />
            ))}
          </View>

          <Text style={styles.fieldLabel}>Base</Text>
          <View style={styles.chipRow}>
            {bases.map((b) => (
              <Chip key={b.id} label={b.name} selected={baseId === b.id} onPress={() => setBaseId(b.id)} />
            ))}
          </View>
        </Card>

        <Card>
          <PrimaryButton
            title={showMore ? 'Ocultar más información' : 'Más información (opcional)'}
            onPress={() => setShowMore((v) => !v)}
            variant="ghost"
            icon={showMore ? 'chevron-up-outline' : 'chevron-down-outline'}
          />
          {showMore ? (
            <View style={{ marginTop: space.sm, gap: space.xs }}>
              {origin === 'compra' ? (
                <Field label="Costo (opcional)" keyboardType="decimal-pad" value={cost} onChangeText={setCost} placeholder="0" />
              ) : null}
              <Field label="Notas (opcional)" value={notes} onChangeText={setNotes} />
            </View>
          ) : null}
        </Card>

        <PrimaryButton title="Crear neumático" onPress={() => void submit()} loading={saving} disabled={!canSubmit} block />
      </ScrollView>
    </View>
  );
}

const styles = StyleSheet.create({
  root: { flex: 1, backgroundColor: colors.page },
  sub: { color: colors.muted, fontSize: type.caption, marginTop: space.xs, marginBottom: space.xs },
  fieldLabel: { color: colors.muted, fontSize: type.label, fontWeight: '600', marginTop: space.sm, marginBottom: space.xs },
  chipRow: { flexDirection: 'row', flexWrap: 'wrap', gap: space.xs },
});
