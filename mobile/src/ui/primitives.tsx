import { Ionicons } from '@expo/vector-icons';
import React from 'react';
import {
  ActivityIndicator,
  Pressable,
  StyleSheet,
  Text,
  TextInput,
  View,
  type StyleProp,
  type TextInputProps,
  type TextProps,
  type TextStyle,
  type ViewStyle,
} from 'react-native';
import { colors, radius, space, touchTarget, type } from '../theme';

export function AppText({ children, style, ...rest }: TextProps) {
  return (
    <Text {...rest} style={[{ color: colors.ink, fontSize: type.body }, style]}>
      {children}
    </Text>
  );
}

export function Card({
  children,
  style,
}: {
  children: React.ReactNode;
  style?: StyleProp<ViewStyle>;
}) {
  return <View style={[styles.card, style]}>{children}</View>;
}

export function Icon({
  name,
  size = 18,
  color = colors.ink,
}: {
  name: keyof typeof Ionicons.glyphMap;
  size?: number;
  color?: string;
}) {
  return <Ionicons name={name} size={size} color={color} />;
}

/** Chip de estado — color de fondo/borde según `tone(status)`, más un ícono
 * propio por valor exacto de estado (no depende solo del color). */
export function StatusPill({
  label,
  color,
  icon,
}: {
  label: string;
  color: string;
  icon?: keyof typeof Ionicons.glyphMap;
}) {
  return (
    <View
      style={[styles.pill, { borderColor: color, backgroundColor: `${color}22` }]}
      accessibilityLabel={`Estado: ${label.replace(/_/g, ' ')}`}
    >
      {icon ? (
        <Icon name={icon} size={18} color={color} />
      ) : (
        <View style={[styles.pillDot, { backgroundColor: color }]} />
      )}
      <Text style={[styles.pillText, { color }]}>{label.replace(/_/g, ' ')}</Text>
    </View>
  );
}

export function PrimaryButton({
  title,
  onPress,
  disabled,
  variant = 'primary',
  icon,
  loading,
  block,
  accessibilityLabel,
  accessibilityHint,
}: {
  title: string;
  onPress: () => void;
  disabled?: boolean;
  variant?: 'primary' | 'danger' | 'ghost' | 'outline';
  icon?: keyof typeof Ionicons.glyphMap;
  loading?: boolean;
  /** Ocupa todo el ancho disponible — usalo en la acción principal de la pantalla. */
  block?: boolean;
  accessibilityLabel?: string;
  accessibilityHint?: string;
}) {
  const bg =
    variant === 'danger'
      ? colors.danger
      : variant === 'ghost'
        ? 'transparent'
        : variant === 'outline'
          ? 'transparent'
          : colors.primary;
  const fg = variant === 'ghost' || variant === 'outline' ? colors.ink : colors.white;
  const borderColor = variant === 'outline' ? colors.line : variant === 'ghost' ? 'transparent' : bg;

  return (
    <Pressable
      accessibilityRole="button"
      accessibilityLabel={accessibilityLabel ?? title}
      accessibilityHint={accessibilityHint}
      accessibilityState={{ disabled: disabled || loading, busy: loading }}
      disabled={disabled || loading}
      onPress={onPress}
      style={({ pressed }) => [
        styles.btn,
        block ? styles.btnBlock : null,
        {
          backgroundColor: bg,
          borderColor,
          opacity: disabled || loading ? 0.5 : pressed ? 0.85 : 1,
        },
      ]}
    >
      {loading ? (
        <ActivityIndicator color={fg} />
      ) : (
        <View style={styles.btnInner}>
          {icon ? <Icon name={icon} size={22} color={fg} /> : null}
          <Text style={[styles.btnText, { color: fg }]} numberOfLines={1}>
            {title}
          </Text>
        </View>
      )}
    </Pressable>
  );
}

export function Field({ label, style, ...props }: TextInputProps & { label?: string }) {
  return (
    <View style={styles.field}>
      {label ? <Text style={styles.fieldLabel}>{label}</Text> : null}
      <TextInput
        placeholderTextColor={colors.muted}
        accessibilityLabel={label}
        {...props}
        style={[styles.input, style as TextStyle]}
      />
    </View>
  );
}

/** Fila de datos con ícono — para reemplazar texto crudo de la base de datos
 * (nombres de configuración, códigos internos) por etiqueta + valor legibles. */
export function InfoRow({
  icon,
  label,
  value,
}: {
  icon: keyof typeof Ionicons.glyphMap;
  label: string;
  value: string;
}) {
  return (
    <View style={styles.infoRow} accessibilityLabel={`${label}: ${value}`}>
      <View style={styles.infoIcon}>
        <Icon name={icon} size={20} color={colors.primary} />
      </View>
      <View style={{ flex: 1 }}>
        <Text style={styles.infoLabel}>{label}</Text>
        <Text style={styles.infoValue}>{value}</Text>
      </View>
    </View>
  );
}

export function SectionLabel({ children }: { children: React.ReactNode }) {
  return (
    <Text style={styles.section} accessibilityRole="header">
      {children}
    </Text>
  );
}

export function StatTile({
  label,
  value,
  accent,
}: {
  label: string;
  value: string | number;
  accent?: string;
}) {
  return (
    <View style={styles.stat} accessibilityLabel={`${label}: ${value}`}>
      <View style={[styles.statAccent, { backgroundColor: accent ?? colors.primary }]} />
      <Text style={styles.statLabel}>{label}</Text>
      <Text style={[styles.statVal, accent ? { color: accent } : null]}>{value}</Text>
    </View>
  );
}

export function Chip({
  label,
  selected,
  onPress,
}: {
  label: string;
  selected?: boolean;
  onPress?: () => void;
}) {
  return (
    <Pressable
      onPress={onPress}
      style={[styles.chip, selected && styles.chipOn]}
      accessibilityRole="button"
      accessibilityLabel={label}
      accessibilityState={{ selected: !!selected }}
    >
      <Text style={[styles.chipText, selected && styles.chipTextOn]}>{label}</Text>
    </Pressable>
  );
}

export function EmptyState({
  icon = 'file-tray-outline',
  title,
  hint,
}: {
  icon?: keyof typeof Ionicons.glyphMap;
  title: string;
  hint?: string;
}) {
  return (
    <View style={styles.empty}>
      <Icon name={icon} size={40} color={colors.muted} />
      <Text style={styles.emptyTitle}>{title}</Text>
      {hint ? <Text style={styles.emptyHint}>{hint}</Text> : null}
    </View>
  );
}

export function LoadingState({ label = 'Cargando…' }: { label?: string }) {
  return (
    <View style={styles.empty} accessibilityLiveRegion="polite">
      <ActivityIndicator color={colors.primary} size="large" />
      <Text style={styles.emptyHint}>{label}</Text>
    </View>
  );
}

export function ErrorState({ message, onRetry }: { message: string; onRetry?: () => void }) {
  return (
    <View style={styles.empty} accessibilityLiveRegion="assertive">
      <Icon name="alert-circle-outline" size={40} color={colors.danger} />
      <Text style={[styles.emptyTitle, { color: colors.danger }]}>{message}</Text>
      {onRetry ? (
        <View style={{ marginTop: space.md }}>
          <PrimaryButton title="Reintentar" onPress={onRetry} variant="outline" icon="refresh-outline" />
        </View>
      ) : null}
    </View>
  );
}

const styles = StyleSheet.create({
  card: {
    backgroundColor: colors.card,
    borderRadius: radius.lg,
    padding: space.lg,
    borderWidth: 1,
    borderColor: colors.line,
  },
  pill: {
    flexDirection: 'row',
    alignItems: 'center',
    alignSelf: 'flex-start',
    gap: 8,
    minHeight: 40,
    paddingHorizontal: 14,
    paddingVertical: 8,
    borderRadius: radius.pill,
    borderWidth: 1,
  },
  pillDot: { width: 10, height: 10, borderRadius: 5 },
  pillText: { fontSize: type.caption, fontWeight: '700', letterSpacing: 0.1 },
  btn: {
    minHeight: touchTarget.min,
    borderRadius: radius.md,
    alignItems: 'center',
    justifyContent: 'center',
    alignSelf: 'stretch',
    paddingHorizontal: 20,
    paddingVertical: 10,
    borderWidth: 1,
  },
  btnBlock: { width: '100%', alignSelf: 'stretch' },
  btnInner: { flexDirection: 'row', alignItems: 'center', gap: 10 },
  btnText: { fontSize: type.button, fontWeight: '700' },
  field: { marginBottom: space.sm },
  fieldLabel: { color: colors.muted, marginBottom: 8, fontSize: type.label, fontWeight: '600' },
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
  section: {
    fontSize: type.label,
    color: colors.muted,
    letterSpacing: 0.2,
    marginTop: space.md,
    marginBottom: space.sm,
    fontWeight: '700',
  },
  stat: {
    flexGrow: 1,
    flexBasis: '47%',
    minWidth: 140,
    backgroundColor: colors.card,
    borderRadius: radius.lg,
    padding: space.md,
    borderWidth: 1,
    borderColor: colors.line,
    overflow: 'hidden',
    position: 'relative',
  },
  statAccent: { position: 'absolute', top: 0, left: 0, right: 0, height: 4 },
  statLabel: {
    fontSize: type.label,
    color: colors.muted,
    fontWeight: '600',
  },
  statVal: { fontSize: type.display, color: colors.ink, fontWeight: '800', marginTop: 6 },
  chip: {
    minHeight: touchTarget.min,
    paddingHorizontal: 18,
    justifyContent: 'center',
    borderRadius: radius.md,
    borderWidth: 1,
    borderColor: colors.line,
    backgroundColor: colors.card,
  },
  chipOn: { backgroundColor: colors.primarySoft, borderColor: colors.primary },
  chipText: { fontSize: type.label, color: colors.muted, fontWeight: '600' },
  chipTextOn: { color: colors.ink, fontWeight: '700' },
  infoRow: { flexDirection: 'row', alignItems: 'center', gap: space.sm, paddingVertical: space.xs },
  infoIcon: {
    width: 40,
    height: 40,
    borderRadius: radius.md,
    backgroundColor: colors.primarySoft,
    alignItems: 'center',
    justifyContent: 'center',
  },
  infoLabel: { color: colors.muted, fontSize: type.caption, fontWeight: '600' },
  infoValue: { color: colors.ink, fontSize: type.body, fontWeight: '600', marginTop: 1 },
  empty: { alignItems: 'center', justifyContent: 'center', padding: space.xl, gap: space.xs },
  emptyTitle: { color: colors.ink, fontSize: type.bodyStrong, fontWeight: '700', textAlign: 'center' },
  emptyHint: { color: colors.muted, fontSize: type.caption, textAlign: 'center' },
});
