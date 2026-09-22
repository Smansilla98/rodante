import { useRef, useState } from 'react';
import {
  Image,
  KeyboardAvoidingView,
  Platform,
  Pressable,
  ScrollView,
  StyleSheet,
  Text,
  TextInput,
  View,
  type TextInput as TextInputType,
} from 'react-native';
import { useSafeAreaInsets } from 'react-native-safe-area-context';
import { useAuth } from '../src/auth/AuthContext';
import { ApiError } from '../src/api/client';
import { colors, radius, space, touchTarget, type } from '../src/theme';
import { Icon, PrimaryButton } from '../src/ui/primitives';

const brandMark = require('../assets/splash-icon.png');

export default function LoginScreen() {
  const { login, offlineHint } = useAuth();
  const insets = useSafeAreaInsets();
  const passwordRef = useRef<TextInputType>(null);

  const [username, setUsername] = useState('');
  const [password, setPassword] = useState('');
  const [showPassword, setShowPassword] = useState(false);
  const [companyId, setCompanyId] = useState('');
  const [error, setError] = useState<string | null>(null);
  const [needsCompany, setNeedsCompany] = useState(false);
  const [busy, setBusy] = useState(false);

  const onSubmit = async () => {
    setError(null);
    setBusy(true);
    try {
      const parsedCompanyId = companyId.trim() ? Number(companyId.trim()) : undefined;
      await login(username, password, parsedCompanyId);
    } catch (e) {
      if (e instanceof ApiError) {
        setError(e.message);
        if (e.message.toLowerCase().includes('company_id')) {
          setNeedsCompany(true);
        }
      } else {
        setError('No se pudo iniciar sesión');
      }
    } finally {
      setBusy(false);
    }
  };

  return (
    <View style={styles.root}>
      <KeyboardAvoidingView
        style={styles.flex}
        behavior={Platform.OS === 'ios' ? 'padding' : 'height'}
        keyboardVerticalOffset={Platform.OS === 'ios' ? 8 : 0}
      >
        <ScrollView
          contentContainerStyle={[
            styles.scroll,
            {
              paddingTop: Math.max(insets.top, 24) + 12,
              paddingBottom: Math.max(insets.bottom, 24) + 24,
            },
          ]}
          keyboardShouldPersistTaps="handled"
          keyboardDismissMode="on-drag"
          showsVerticalScrollIndicator={false}
        >
          <View style={styles.hero}>
            <Image source={brandMark} style={styles.logo} resizeMode="contain" accessibilityLabel="Rodante" />
            <Text style={styles.brandName}>Rodante</Text>
            <Text style={styles.brandSub}>Trazabilidad de neumáticos</Text>
          </View>

          <View style={styles.card}>
            <Text style={styles.cardTitle}>Iniciar sesión</Text>
            <Text style={styles.cardHint}>Ingresá tu usuario y contraseña de campo</Text>

            <Text style={styles.label}>Usuario</Text>
            <TextInput
              style={styles.input}
              autoCapitalize="none"
              autoCorrect={false}
              autoComplete="username"
              textContentType="username"
              value={username}
              onChangeText={setUsername}
              placeholder="operario1"
              placeholderTextColor={colors.muted}
              returnKeyType="next"
              onSubmitEditing={() => passwordRef.current?.focus()}
              blurOnSubmit={false}
              accessibilityLabel="Usuario"
            />

            <Text style={styles.label}>Contraseña</Text>
            <View style={styles.passwordRow}>
              <TextInput
                ref={passwordRef}
                style={[styles.input, styles.passwordInput]}
                secureTextEntry={!showPassword}
                autoCapitalize="none"
                autoCorrect={false}
                value={password}
                onChangeText={setPassword}
                placeholder="Tu contraseña"
                placeholderTextColor={colors.muted}
                autoComplete="password"
                textContentType="password"
                returnKeyType={needsCompany ? 'next' : 'go'}
                onSubmitEditing={() => void onSubmit()}
                accessibilityLabel="Contraseña"
              />
              <Pressable
                onPress={() => setShowPassword((v) => !v)}
                style={styles.eyeBtn}
                accessibilityRole="button"
                accessibilityLabel={showPassword ? 'Ocultar contraseña' : 'Mostrar contraseña'}
                hitSlop={8}
              >
                <Icon name={showPassword ? 'eye-off-outline' : 'eye-outline'} size={22} color={colors.muted} />
              </Pressable>
            </View>

            {needsCompany ? (
              <>
                <Text style={styles.label}>ID de empresa</Text>
                <TextInput
                  style={styles.input}
                  keyboardType="number-pad"
                  value={companyId}
                  onChangeText={setCompanyId}
                  placeholder="Ej: 1"
                  placeholderTextColor={colors.muted}
                  returnKeyType="go"
                  onSubmitEditing={() => void onSubmit()}
                  accessibilityLabel="ID de empresa"
                />
              </>
            ) : null}

            {(error || offlineHint) && <Text style={styles.error}>{error ?? offlineHint}</Text>}

            <View style={styles.cta}>
              <PrimaryButton
                title="Entrar"
                icon="log-in-outline"
                onPress={() => void onSubmit()}
                loading={busy}
                disabled={busy}
                block
              />
            </View>
          </View>
        </ScrollView>
      </KeyboardAvoidingView>
    </View>
  );
}

const styles = StyleSheet.create({
  root: { flex: 1, backgroundColor: colors.page },
  flex: { flex: 1 },
  scroll: { flexGrow: 1, justifyContent: 'center', paddingHorizontal: space.lg, gap: 28 },
  hero: { alignItems: 'center', gap: 4 },
  logo: { width: 120, height: 120 },
  brandName: { color: colors.ink, fontSize: type.title, fontWeight: '800', letterSpacing: -0.5, marginTop: 8 },
  brandSub: { color: colors.muted, fontSize: type.body },
  card: {
    backgroundColor: colors.card,
    borderRadius: radius.xl,
    borderWidth: 1,
    borderColor: colors.line,
    padding: space.xl,
  },
  // Sin `gap` acá a propósito: cada campo ya trae su propio margen (label
  // arriba, input después) — sumarle un gap del contenedor encima duplicaba
  // el espacio entre algunos pares y lo dejaba casi pegado entre otros.
  cardTitle: { color: colors.ink, fontSize: type.subtitle, fontWeight: '700' },
  cardHint: { color: colors.muted, fontSize: type.caption, marginTop: space.xs, marginBottom: space.sm },
  label: { color: colors.muted, fontSize: type.label, marginTop: space.sm, marginBottom: space.xs, fontWeight: '600' },
  input: {
    backgroundColor: colors.card2,
    borderRadius: radius.md,
    borderWidth: 1,
    borderColor: colors.line,
    minHeight: touchTarget.min,
    paddingHorizontal: space.md,
    fontSize: type.body,
    color: colors.ink,
  },
  passwordRow: { justifyContent: 'center' },
  passwordInput: { paddingRight: touchTarget.min },
  eyeBtn: {
    position: 'absolute',
    right: 0,
    height: touchTarget.min,
    width: touchTarget.min,
    alignItems: 'center',
    justifyContent: 'center',
  },
  error: { color: colors.danger, marginTop: space.sm, marginBottom: space.xs, fontSize: type.body },
  cta: { marginTop: space.lg },
});
