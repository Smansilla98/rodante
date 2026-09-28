import { useEffect, useMemo, useState } from 'react';
import { Alert, ScrollView, StyleSheet, Text, View } from 'react-native';
import { useRouter, type Href } from 'expo-router';
import { api, ApiError } from '../api/client';
import type { FleetUnit, UnitOptionsPayload, UnitStatusValue } from '../api/types';
import { colors, space, type } from '../theme';
import { Card, Chip, Field, PrimaryButton } from '../ui/primitives';

const STATUSES: Array<{ value: UnitStatusValue; label: string }> = [
  { value: 'ACTIVA', label: 'Activa' },
  { value: 'INACTIVA', label: 'Inactiva' },
  { value: 'SPARE', label: 'Spare' },
];

export function UnitForm({ unit }: { unit?: FleetUnit }) {
  const router = useRouter();
  const editing = unit != null;
  const [options, setOptions] = useState<UnitOptionsPayload | null>(null);
  const [error, setError] = useState<string | null>(null);
  const [busy, setBusy] = useState(false);

  const [fleetId, setFleetId] = useState<number | null>(unit?.fleet_id ?? null);
  const [baseId, setBaseId] = useState<number | null>(unit?.base_id ?? null);
  const [typeId, setTypeId] = useState<number | null>(unit?.unit_type_id ?? unit?.type?.id ?? null);
  const [configId, setConfigId] = useState<number | null>(unit?.unit_configuration_id ?? unit?.configuration?.id ?? null);
  const [plate, setPlate] = useState(unit?.plate ?? '');
  const [brand, setBrand] = useState(unit?.brand ?? '');
  const [modelName, setModelName] = useState(unit?.model_name ?? '');
  const [odometer, setOdometer] = useState(unit?.current_odometer ? String(unit.current_odometer) : '');
  const [duty, setDuty] = useState<string | null>(unit?.duty ?? null);
  const [status, setStatus] = useState<UnitStatusValue>(unit?.status ?? 'ACTIVA');
  const initialWidth = unit?.specs?.tire_width;
  const [tireWidth, setTireWidth] = useState<295 | 385 | null>(
    initialWidth === 295 || initialWidth === 385 ? initialWidth : null,
  );
  const [notes, setNotes] = useState(unit?.notes ?? '');

  useEffect(() => {
    void api.unitOptions().then(setOptions).catch((e) => {
      setError(e instanceof ApiError ? e.message : 'No se pudo cargar el formulario');
    });
  }, []);

  const selectedType = options?.types.find((item) => item.id === typeId) ?? unit?.type ?? null;
  const hasOdometer = selectedType?.has_odometer ?? unit?.type?.has_odometer ?? true;
  const typeCode = selectedType && 'code' in selectedType ? selectedType.code : unit?.type?.code;

  const configurations = useMemo(() => {
    if (!options) return [];
    if (!typeCode) return options.configurations;
    return options.configurations.filter((item) => (item.compatible_types ?? []).includes(typeCode));
  }, [options, typeCode]);

  const save = async () => {
    if (!fleetId || !baseId || !plate.trim()) {
      setError('Completá flota, base y patente.');
      return;
    }
    if (!editing && (!typeId || !configId)) {
      setError('Elegí el tipo y la configuración.');
      return;
    }
    if (!hasOdometer && !tireWidth) {
      setError('Indicá si la unidad lleva lineal 295 o 385.');
      return;
    }
    setBusy(true);
    setError(null);
    try {
      const body = {
        fleet_id: fleetId,
        base_id: baseId,
        plate: plate.trim(),
        brand: brand.trim() || undefined,
        model_name: modelName.trim() || undefined,
        duty,
        notes: notes.trim() || undefined,
        ...(hasOdometer
          ? { current_odometer: odometer.trim() ? Number(odometer) : 0 }
          : { specs: { tire_width: tireWidth } }),
      };
      if (editing && unit) {
        await api.updateUnit(unit.id, { ...body, status });
        Alert.alert('Listo', 'Unidad actualizada.');
        router.back();
      } else {
        const created = await api.createUnit({
          ...body,
          unit_type_id: typeId!,
          unit_configuration_id: configId!,
        });
        router.replace(`/(tabs)/units/${created.id}` as Href);
      }
    } catch (e) {
      setError(e instanceof ApiError ? e.message : 'No se pudo guardar');
    } finally {
      setBusy(false);
    }
  };

  const remove = () => {
    if (!unit) return;
    Alert.alert('Eliminar unidad', 'Si tiene historial de enganches queda inactiva.', [
      { text: 'Cancelar', style: 'cancel' },
      {
        text: 'Eliminar',
        style: 'destructive',
        onPress: () => {
          void (async () => {
            setBusy(true);
            try {
              const result = await api.deleteUnit(unit.id);
              Alert.alert('Listo', result.message);
              router.replace('/(tabs)/units' as Href);
            } catch (e) {
              setError(e instanceof ApiError ? e.message : 'No se pudo eliminar');
            } finally {
              setBusy(false);
            }
          })();
        },
      },
    ]);
  };

  return (
    <ScrollView contentContainerStyle={styles.scroll}>
      {error ? (
        <Card>
          <Text style={styles.error}>{error}</Text>
        </Card>
      ) : null}
      <Card>
        <Text style={styles.label}>Flota</Text>
        <View style={styles.chips}>
          {(options?.fleets ?? []).map((item) => (
            <Chip key={item.id} label={item.name} selected={fleetId === item.id} onPress={() => setFleetId(item.id)} />
          ))}
        </View>
        <Text style={styles.label}>Base</Text>
        <View style={styles.chips}>
          {(options?.bases ?? []).map((item) => (
            <Chip key={item.id} label={item.name} selected={baseId === item.id} onPress={() => setBaseId(item.id)} />
          ))}
        </View>
      </Card>

      {editing ? (
        <Card>
          <Text style={styles.label}>Estado</Text>
          <View style={styles.chips}>
            {STATUSES.map((item) => (
              <Chip key={item.value} label={item.label} selected={status === item.value} onPress={() => setStatus(item.value)} />
            ))}
          </View>
          <Text style={styles.hint}>
            {unit?.type?.name ?? 'Unidad'} · {unit?.configuration?.name ?? 'configuración actual'}
          </Text>
        </Card>
      ) : (
        <Card>
          <Text style={styles.label}>Tipo</Text>
          <View style={styles.chips}>
            {(options?.types ?? []).map((item) => (
              <Chip
                key={item.id}
                label={item.name}
                selected={typeId === item.id}
                onPress={() => {
                  setTypeId(item.id);
                  setConfigId(null);
                }}
              />
            ))}
          </View>
          <Text style={styles.label}>Configuración</Text>
          <View style={styles.chips}>
            {configurations.map((item) => (
              <Chip key={item.id} label={item.code ?? item.name} selected={configId === item.id} onPress={() => setConfigId(item.id)} />
            ))}
          </View>
        </Card>
      )}

      <Card>
        <Field label="Patente" value={plate} onChangeText={setPlate} autoCapitalize="characters" />
        <Field label="Marca" value={brand} onChangeText={setBrand} />
        <Field label="Modelo" value={modelName} onChangeText={setModelName} />
        {hasOdometer ? (
          <Field label="Kilometraje" value={odometer} onChangeText={setOdometer} keyboardType="number-pad" />
        ) : (
          <>
            <Text style={styles.label}>Lineal</Text>
            <View style={styles.chips}>
              <Chip label="295" selected={tireWidth === 295} onPress={() => setTireWidth(295)} />
              <Chip label="385" selected={tireWidth === 385} onPress={() => setTireWidth(385)} />
            </View>
          </>
        )}
        <Text style={styles.label}>Uso</Text>
        <View style={styles.chips}>
          {(options?.duties ?? []).map((item) => (
            <Chip key={item.value} label={item.label} selected={duty === item.value} onPress={() => setDuty(item.value)} />
          ))}
        </View>
        <Field label="Notas" value={notes} onChangeText={setNotes} />
        <PrimaryButton title={editing ? 'Guardar cambios' : 'Crear unidad'} onPress={() => void save()} loading={busy} block />
        {editing ? (
          <View style={{ marginTop: space.sm }}>
            <PrimaryButton title="Eliminar o inactivar" variant="outline" onPress={remove} disabled={busy} block />
          </View>
        ) : null}
      </Card>
    </ScrollView>
  );
}

const styles = StyleSheet.create({
  scroll: { padding: space.lg, gap: space.md },
  label: { color: colors.muted, fontSize: type.caption, fontWeight: '700', marginBottom: space.xs, marginTop: space.sm },
  chips: { flexDirection: 'row', flexWrap: 'wrap', gap: space.xs },
  hint: { color: colors.muted, marginTop: space.sm },
  error: { color: colors.danger },
});
