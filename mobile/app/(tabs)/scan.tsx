import { CameraView, useCameraPermissions } from 'expo-camera';
import { useRouter, type Href } from 'expo-router';
import { useRef, useState } from 'react';
import { StyleSheet, Text, View } from 'react-native';
import { api, ApiError } from '../../src/api/client';
import type { SearchPayload } from '../../src/api/types';
import { normalizeScan } from '../../src/scan/normalize';
import { colors, radius, space, type } from '../../src/theme';
import { PageHeader } from '../../src/ui/PageHeader';
import { Card, Field, PrimaryButton } from '../../src/ui/primitives';

export default function ScanScreen() {
  const router = useRouter();
  const [permission, requestPermission] = useCameraPermissions();
  const [manual, setManual] = useState('');
  const [error, setError] = useState<string | null>(null);
  const [hits, setHits] = useState<SearchPayload | null>(null);
  const [busy, setBusy] = useState(false);
  const lock = useRef(false);

  const resolve = async (raw: string) => {
    const q = normalizeScan(raw);
    if (!q || lock.current) return;
    lock.current = true;
    setBusy(true);
    setError(null);
    setHits(null);
    try {
      const result = await api.search(q);
      const tires = result.tires;
      const units = result.units;
      if (tires.length === 1 && units.length === 0) {
        router.push(`/(tabs)/tires/${tires[0].id}` as Href);
        return;
      }
      if (units.length === 1 && tires.length === 0) {
        router.push(`/(tabs)/units/${units[0].id}` as Href);
        return;
      }
      if (tires.length === 0 && units.length === 0) {
        setError('No encontramos un neumático ni una unidad con ese código.');
      } else {
        setHits(result);
      }
    } catch (e) {
      setError(e instanceof ApiError ? e.message : 'No se pudo identificar el código');
    } finally {
      setBusy(false);
      setTimeout(() => {
        lock.current = false;
      }, 1200);
    }
  };

  return (
    <View style={styles.root}>
      <PageHeader title="Escanear" subtitle="Neumático, patente o QR" />
      <View style={styles.body}>
        {permission?.granted ? (
          <CameraView
            style={styles.camera}
            facing="back"
            barcodeScannerSettings={{ barcodeTypes: ['qr'] }}
            onBarcodeScanned={busy ? undefined : (event) => void resolve(event.data)}
          />
        ) : (
          <Card>
            <Text style={styles.hint}>
              {permission == null
                ? 'Preparando la cámara…'
                : 'Activá la cámara para leer el QR. También podés escribir el número.'}
            </Text>
            <PrimaryButton title="Permitir cámara" onPress={() => void requestPermission()} block />
          </Card>
        )}

        <Card>
          <Field
            label="Número, patente o token"
            value={manual}
            onChangeText={setManual}
            autoCapitalize="characters"
            returnKeyType="search"
            onSubmitEditing={() => void resolve(manual)}
          />
          <PrimaryButton title="Identificar" icon="search-outline" onPress={() => void resolve(manual)} loading={busy} block />
        </Card>

        {error ? <Text style={styles.error}>{error}</Text> : null}

        {hits?.tires.map((tire) => (
          <PrimaryButton
            key={`t-${tire.id}`}
            title={`Neumático Nº${tire.individual_number}`}
            variant="outline"
            onPress={() => router.push(`/(tabs)/tires/${tire.id}` as Href)}
            block
          />
        ))}
        {hits?.units.map((unit) => (
          <PrimaryButton
            key={`u-${unit.id}`}
            title={`Unidad ${unit.plate}`}
            variant="outline"
            onPress={() => router.push(`/(tabs)/units/${unit.id}` as Href)}
            block
          />
        ))}
      </View>
    </View>
  );
}

const styles = StyleSheet.create({
  root: { flex: 1, backgroundColor: colors.page },
  body: { padding: space.lg, gap: space.md },
  camera: { height: 280, borderRadius: radius.lg, overflow: 'hidden' },
  hint: { color: colors.muted, marginBottom: space.sm },
  error: { color: colors.danger, fontSize: type.body },
});
