import { useState } from 'react';
import { ScrollView, StyleSheet, Text, View } from 'react-native';
import { useRouter } from 'expo-router';
import { api, ApiError } from '../../src/api/client';
import type { LookupResult } from '../../src/api/types';
import { colors, space, type } from '../../src/theme';
import { PageHeader } from '../../src/ui/PageHeader';
import { TireStatusBadge } from '../../src/ui/StatusBadge';
import { Card, EmptyState, Field, PrimaryButton } from '../../src/ui/primitives';

export default function LookupScreen() {
  const router = useRouter();
  const [q, setQ] = useState('');
  const [result, setResult] = useState<LookupResult | null>(null);
  const [error, setError] = useState<string | null>(null);
  const [busy, setBusy] = useState(false);

  const search = async () => {
    if (!q.trim()) return;
    setBusy(true);
    setError(null);
    setResult(null);
    try {
      const tire = await api.lookupTire(q.trim());
      setResult(tire);
    } catch (e) {
      setError(e instanceof ApiError ? e.message : 'No se pudo buscar');
    } finally {
      setBusy(false);
    }
  };

  return (
    <View style={styles.root}>
      <PageHeader title="Buscar neumático" subtitle="Por número individual o DOT/token" />
      <ScrollView contentContainerStyle={{ padding: space.lg, gap: space.md }}>
        <Card>
          <Field
            label="Número individual o token"
            value={q}
            onChangeText={setQ}
            placeholder="Ej: 30363"
            autoCapitalize="none"
            returnKeyType="search"
            onSubmitEditing={() => void search()}
          />
          <PrimaryButton title="Buscar" icon="search-outline" onPress={() => void search()} loading={busy} block />
        </Card>

        {error ? (
          <Card>
            <Text style={styles.error}>{error}</Text>
          </Card>
        ) : null}

        {result ? (
          <Card>
            <View style={styles.headRow}>
              <Text style={styles.title}>
                {result.model?.code ?? result.brand?.name ?? 'S/M'} Nº{result.individual_number}
              </Text>
              <TireStatusBadge status={result.status} />
            </View>
            {result.display_condition ? <Text style={styles.sub}>{result.display_condition}</Text> : null}
            <Text style={styles.sub}>DOT {result.dot ?? '—'}</Text>
            <Text style={styles.sub}>Km acumulados: {result.accumulated_km}</Text>
            <View style={{ marginTop: space.md }}>
              <PrimaryButton
                title="Ver detalle"
                icon="chevron-forward-outline"
                onPress={() => router.push(`/(tabs)/tires/${result.id}`)}
                block
              />
            </View>
          </Card>
        ) : !error && !busy ? (
          <EmptyState icon="search-outline" title="Buscá un neumático" hint="Ingresá el número individual o el token." />
        ) : null}
      </ScrollView>
    </View>
  );
}

const styles = StyleSheet.create({
  root: { flex: 1, backgroundColor: colors.page },
  headRow: { flexDirection: 'row', justifyContent: 'space-between', alignItems: 'center', gap: space.sm },
  title: { color: colors.ink, fontSize: type.bodyStrong, fontWeight: '700', flexShrink: 1 },
  sub: { color: colors.muted, fontSize: type.caption, marginTop: space.xs },
  error: { color: colors.danger, fontSize: type.body },
});
