import { useState } from 'react';
import { Pressable, ScrollView, StyleSheet, Text, View } from 'react-native';
import { useRouter, type Href } from 'expo-router';
import { api, ApiError } from '../../src/api/client';
import type { SearchPayload } from '../../src/api/types';
import { colors, radius, space, touchTarget, type } from '../../src/theme';
import { PageHeader } from '../../src/ui/PageHeader';
import { Card, EmptyState, Field, PrimaryButton } from '../../src/ui/primitives';

export default function LookupScreen() {
  const router = useRouter();
  const [q, setQ] = useState('');
  const [result, setResult] = useState<SearchPayload | null>(null);
  const [error, setError] = useState<string | null>(null);
  const [busy, setBusy] = useState(false);

  const search = async () => {
    if (!q.trim()) return;
    setBusy(true);
    setError(null);
    setResult(null);
    try {
      setResult(await api.search(q.trim()));
    } catch (e) {
      setError(e instanceof ApiError ? e.message : 'No se pudo buscar');
    } finally {
      setBusy(false);
    }
  };

  const empty = result && result.tires.length === 0 && result.units.length === 0;

  return (
    <View style={styles.root}>
      <PageHeader title="Buscar" subtitle="Patente, número, DOT o token" />
      <ScrollView contentContainerStyle={{ padding: space.lg, gap: space.md }}>
        <Card>
          <Field
            label="Qué estás buscando"
            value={q}
            onChangeText={setQ}
            placeholder="Patente, Nº de cubierta o QR"
            autoCapitalize="characters"
            returnKeyType="search"
            onSubmitEditing={() => void search()}
          />
          <PrimaryButton title="Buscar" icon="search-outline" onPress={() => void search()} loading={busy} block />
          <View style={{ marginTop: space.sm }}>
            <PrimaryButton
              title="Escanear QR"
              icon="qr-code-outline"
              variant="outline"
              onPress={() => router.push('/(tabs)/scan' as Href)}
              block
            />
          </View>
        </Card>

        {error ? (
          <Card>
            <Text style={styles.error}>{error}</Text>
          </Card>
        ) : null}

        {result?.units.map((unit) => (
          <Pressable
            key={`u-${unit.id}`}
            style={styles.hit}
            onPress={() => router.push(`/(tabs)/units/${unit.id}` as Href)}
            accessibilityRole="button"
            accessibilityLabel={`Unidad ${unit.plate}`}
          >
            <Text style={styles.hitTitle}>{unit.plate}</Text>
            <Text style={styles.hitMeta}>{unit.type ?? 'Unidad'}{unit.fleet ? ` · ${unit.fleet}` : ''}</Text>
          </Pressable>
        ))}
        {result?.tires.map((tire) => (
          <Pressable
            key={`t-${tire.id}`}
            style={styles.hit}
            onPress={() => router.push(`/(tabs)/tires/${tire.id}` as Href)}
            accessibilityRole="button"
            accessibilityLabel={`Neumático ${tire.individual_number}`}
          >
            <Text style={styles.hitTitle}>Nº {tire.individual_number}</Text>
            <Text style={styles.hitMeta}>
              {[tire.model, tire.brand, tire.plate ? `en ${tire.plate}` : null].filter(Boolean).join(' · ') || 'Neumático'}
            </Text>
          </Pressable>
        ))}
        {empty ? <EmptyState icon="search-outline" title="Sin resultados" hint="Probá con la patente o el número individual." /> : null}
        {!result && !error ? (
          <EmptyState icon="search-outline" title="Buscá en la flota" hint="Encontrás unidades y neumáticos sin salir de la app." />
        ) : null}
      </ScrollView>
    </View>
  );
}

const styles = StyleSheet.create({
  root: { flex: 1, backgroundColor: colors.page },
  error: { color: colors.danger },
  hit: {
    minHeight: touchTarget.row,
    backgroundColor: colors.card,
    borderRadius: radius.lg,
    borderWidth: 1,
    borderColor: colors.line,
    padding: space.md,
    justifyContent: 'center',
  },
  hitTitle: { color: colors.ink, fontSize: type.bodyStrong, fontWeight: '700' },
  hitMeta: { color: colors.muted, marginTop: 2 },
});
