import { useRouter, type Href } from 'expo-router';
import { Pressable, ScrollView, StyleSheet, Text, View } from 'react-native';
import { useAuth } from '../../src/auth/AuthContext';
import { canViewTelemetry } from '../../src/auth/permissions';
import { colors, radius, space, touchTarget, type } from '../../src/theme';
import { PageHeader } from '../../src/ui/PageHeader';
import { Icon } from '../../src/ui/primitives';

type MenuItem = {
  key: string;
  label: string;
  hint: string;
  icon: keyof typeof import('@expo/vector-icons').Ionicons.glyphMap;
  href: Href;
};

export default function MoreScreen() {
  const router = useRouter();
  const { user } = useAuth();

  const items: MenuItem[] = [
    {
      key: 'work-orders',
      label: 'Órdenes de trabajo',
      hint: 'Recapado y reparación de neumáticos',
      icon: 'build-outline',
      href: '/(tabs)/work-orders',
    },
    {
      key: 'inventory-sessions',
      label: 'Inventario',
      hint: 'Sesiones de conteo por base',
      icon: 'clipboard-outline',
      href: '/(tabs)/inventory-sessions',
    },
    ...(canViewTelemetry(user?.role)
      ? [
          {
            key: 'telemetry',
            label: 'Telemetría',
            hint: 'Métricas de la flota',
            icon: 'pulse-outline' as const,
            href: '/(tabs)/telemetry' as Href,
          },
        ]
      : []),
    {
      key: 'profile',
      label: 'Perfil',
      hint: 'Tu cuenta y cerrar sesión',
      icon: 'person-outline',
      href: '/(tabs)/profile',
    },
  ];

  return (
    <View style={styles.root}>
      <PageHeader title="Más" subtitle="Órdenes, inventario y tu cuenta" />
      <ScrollView contentContainerStyle={styles.scroll}>
        {items.map((item) => (
          <Pressable
            key={item.key}
            onPress={() => router.push(item.href)}
            style={({ pressed }) => [styles.row, pressed && styles.rowPressed]}
            accessibilityRole="button"
            accessibilityLabel={item.label}
            accessibilityHint={item.hint}
          >
            <View style={styles.rowIcon}>
              <Icon name={item.icon} size={26} color={colors.primary} />
            </View>
            <View style={styles.rowBody}>
              <Text style={styles.rowLabel}>{item.label}</Text>
              <Text style={styles.rowHint}>{item.hint}</Text>
            </View>
            <Icon name="chevron-forward-outline" size={22} color={colors.muted} />
          </Pressable>
        ))}
      </ScrollView>
    </View>
  );
}

const styles = StyleSheet.create({
  root: { flex: 1, backgroundColor: colors.page },
  scroll: { padding: space.lg, paddingTop: 0, gap: space.md },
  row: {
    flexDirection: 'row',
    alignItems: 'center',
    minHeight: touchTarget.row,
    backgroundColor: colors.card,
    borderRadius: radius.lg,
    borderWidth: 1,
    borderColor: colors.line,
    paddingHorizontal: space.md,
    paddingVertical: space.sm,
    gap: space.md,
  },
  rowPressed: { backgroundColor: colors.card2 },
  rowIcon: {
    width: 48,
    height: 48,
    borderRadius: radius.lg,
    backgroundColor: colors.primarySoft,
    alignItems: 'center',
    justifyContent: 'center',
  },
  rowBody: { flex: 1 },
  rowLabel: { color: colors.ink, fontSize: type.bodyStrong, fontWeight: '700' },
  rowHint: { color: colors.muted, fontSize: type.caption, marginTop: 2 },
});
