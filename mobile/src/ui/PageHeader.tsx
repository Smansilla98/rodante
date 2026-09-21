import { useRouter } from 'expo-router';
import React from 'react';
import { Pressable, StyleSheet, Text, View } from 'react-native';
import { colors, space, touchTarget, type } from '../theme';
import { Icon } from './primitives';

export function PageHeader({
  title,
  subtitle,
  showBack,
}: {
  title: string;
  subtitle?: string;
  /** Muestra un botón "Volver" — usalo en pantallas a las que se llega desde el menú "Más". */
  showBack?: boolean;
}) {
  const router = useRouter();

  return (
    <View style={styles.wrap}>
      {showBack ? (
        <Pressable
          onPress={() => (router.canGoBack() ? router.back() : router.replace('/(tabs)/more'))}
          style={styles.back}
          accessibilityRole="button"
          accessibilityLabel="Volver"
          accessibilityHint="Vuelve a la pantalla anterior"
          hitSlop={8}
        >
          <Icon name="chevron-back-outline" size={24} color={colors.ink} />
          <Text style={styles.backText}>Volver</Text>
        </Pressable>
      ) : null}
      <Text style={styles.title}>{title}</Text>
      {subtitle ? <Text style={styles.subtitle}>{subtitle}</Text> : null}
    </View>
  );
}

const styles = StyleSheet.create({
  wrap: {
    paddingHorizontal: space.lg,
    paddingTop: space.lg,
    paddingBottom: space.md,
    backgroundColor: colors.page,
  },
  back: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 2,
    minHeight: touchTarget.min,
    marginLeft: -8,
    marginBottom: 2,
    alignSelf: 'flex-start',
  },
  backText: { color: colors.ink, fontSize: type.body, fontWeight: '600' },
  title: { color: colors.ink, fontSize: type.title, fontWeight: '800', letterSpacing: -0.5 },
  subtitle: { color: colors.muted, fontSize: type.subtitle, marginTop: 4 },
});
