import type { Ionicons } from '@expo/vector-icons';

/**
 * Tokens Rodante — paridad con resources/css/app.css `:root` (tema oscuro).
 */
export const colors = {
  ink: '#f4f6fb',
  inkSoft: '#d7dced',
  page: '#0f141c',
  page2: '#141a24',
  card: '#171e2a',
  card2: '#1d2634',
  line: '#2a3344',
  muted: '#c5cbe0',
  primary: '#c8102e',
  primaryHover: '#e11d3a',
  primarySoft: 'rgba(200, 16, 46, 0.18)',
  sidebar: '#101820',
  ok: '#4ade80',
  warn: '#fbbf24',
  danger: '#f87171',
  white: '#ffffff',
  black: '#000000',
} as const;

export const radius = {
  sm: 6,
  md: 8,
  lg: 12,
  xl: 16,
  pill: 999,
} as const;

export const space = {
  xs: 8,
  sm: 12,
  md: 16,
  lg: 24,
  xl: 32,
  xxl: 40,
} as const;

/**
 * Escala tipográfica — pensada para trabajadores de campo (mecánicos,
 * operarios de depósito), no siempre familiarizados con apps. `caption`
 * (15px) es el piso: nunca usar un tamaño menor en ningún lado de la app.
 * Nada de mayúsculas forzadas (`textTransform: 'uppercase'`) — la
 * jerarquía se logra con peso/color, que se lee mejor con luz de taller.
 */
export const type = {
  caption: 15,
  body: 18,
  bodyStrong: 18,
  label: 16,
  subtitle: 18,
  title: 26,
  display: 32,
  button: 19,
} as const;

/** Todo elemento tocable (botón, fila de lista, tab, chip) respeta este piso. */
export const touchTarget = {
  min: 56,
  row: 72,
} as const;

/**
 * Tono por familia (verde/azul/ámbar/gris/naranja/rojo) — mismo mapeo que
 * `TireStatus::tone()` / `UnitStatus` en el backend, llevado a los tokens
 * ok/primary/warn/danger/muted de arriba.
 */
const toneColors: Record<string, string> = {
  green: colors.ok,
  blue: '#60a5fa',
  amber: colors.warn,
  slate: colors.muted,
  orange: '#fb923c',
  red: colors.danger,
};

const TIRE_STATUS_TONE: Record<string, string> = {
  STOCK: 'green',
  RESERVA: 'blue',
  INSTALADA: 'amber',
  AUXILIO: 'slate',
  EN_REPARACION: 'orange',
  DE_BAJA: 'red',
};

const UNIT_STATUS_TONE: Record<string, string> = {
  ACTIVA: 'green',
  INACTIVA: 'red',
  SPARE: 'blue',
};

const WORK_ORDER_STATUS_TONE: Record<string, string> = {
  ABIERTA: 'blue',
  EN_TALLER: 'amber',
  CERRADA: 'green',
  CANCELADA: 'red',
};

const INVENTORY_SESSION_TONE: Record<string, string> = {
  OPEN: 'blue',
  COUNTING: 'amber',
  REVIEW: 'orange',
  CLOSED: 'green',
  CANCELLED: 'red',
};

type IconName = keyof typeof Ionicons.glyphMap;

/**
 * Un ícono distinto por cada valor exacto de estado (no solo por familia de
 * color) — así el estado se distingue también a simple vista con sol fuerte
 * o para alguien con daltonismo, sin depender solo del color.
 */
const TIRE_STATUS_ICON: Record<string, IconName> = {
  STOCK: 'cube-outline',
  RESERVA: 'bookmark-outline',
  INSTALADA: 'checkmark-circle-outline',
  AUXILIO: 'help-buoy-outline',
  EN_REPARACION: 'build-outline',
  DE_BAJA: 'close-circle-outline',
};

const UNIT_STATUS_ICON: Record<string, IconName> = {
  ACTIVA: 'checkmark-circle-outline',
  INACTIVA: 'close-circle-outline',
  SPARE: 'archive-outline',
};

const WORK_ORDER_STATUS_ICON: Record<string, IconName> = {
  ABIERTA: 'folder-open-outline',
  EN_TALLER: 'build-outline',
  CERRADA: 'checkmark-circle-outline',
  CANCELADA: 'close-circle-outline',
};

const INVENTORY_SESSION_STATUS_ICON: Record<string, IconName> = {
  OPEN: 'folder-open-outline',
  COUNTING: 'list-outline',
  REVIEW: 'eye-outline',
  CLOSED: 'checkmark-circle-outline',
  CANCELLED: 'close-circle-outline',
};

const DEFAULT_STATUS_ICON: IconName = 'ellipse-outline';

export function tireStatusIcon(status: string): IconName {
  return TIRE_STATUS_ICON[status] ?? DEFAULT_STATUS_ICON;
}

export function unitStatusIcon(status: string): IconName {
  return UNIT_STATUS_ICON[status] ?? DEFAULT_STATUS_ICON;
}

export function workOrderStatusIcon(status: string): IconName {
  return WORK_ORDER_STATUS_ICON[status] ?? DEFAULT_STATUS_ICON;
}

export function inventorySessionStatusIcon(status: string): IconName {
  return INVENTORY_SESSION_STATUS_ICON[status] ?? DEFAULT_STATUS_ICON;
}

export function toneColor(tone: string): string {
  return toneColors[tone] ?? colors.muted;
}

export function tireStatusColor(status: string): string {
  return toneColor(TIRE_STATUS_TONE[status] ?? 'slate');
}

export function unitStatusColor(status: string): string {
  return toneColor(UNIT_STATUS_TONE[status] ?? 'slate');
}

export function workOrderStatusColor(status: string): string {
  return toneColor(WORK_ORDER_STATUS_TONE[status] ?? 'slate');
}

export function inventorySessionStatusColor(status: string): string {
  return toneColor(INVENTORY_SESSION_TONE[status] ?? 'slate');
}

export const TIRE_STATUS_LABEL: Record<string, string> = {
  STOCK: 'Stock',
  RESERVA: 'Reserva',
  INSTALADA: 'Instalada',
  AUXILIO: 'Auxilio',
  EN_REPARACION: 'En reparación',
  DE_BAJA: 'De baja',
};

/** Espejo de `App\Enums\TireCondition::label()` — para el picker completo de "Modificar". */
export const TIRE_CONDITION_LABEL: Record<string, string> = {
  NUEVA: 'Nueva',
  NUEVA_USADA: 'Nueva usada',
  USADA: 'Usada',
  RECAPADA: 'Recapada',
  REPARADA: 'Reparada (parche)',
};

export const UNIT_STATUS_LABEL: Record<string, string> = {
  ACTIVA: 'Activa',
  INACTIVA: 'Inactiva',
  SPARE: 'Spare',
};

/** Espejo de `App\Enums\UnitDuty::label()` — uso principal de la unidad. */
export const UNIT_DUTY_LABEL: Record<string, string> = {
  LARGA_DISTANCIA: 'Larga distancia',
  REGIONAL: 'Regional',
  URBANO: 'Urbano',
  NIEVE: 'Nieve / cordillera',
  MIXTO: 'Mixto',
};

export const WORK_ORDER_STATUS_LABEL: Record<string, string> = {
  ABIERTA: 'Abierta',
  EN_TALLER: 'En taller',
  CERRADA: 'Cerrada',
  CANCELADA: 'Cancelada',
};

export const INVENTORY_SESSION_STATUS_LABEL: Record<string, string> = {
  OPEN: 'Abierta',
  COUNTING: 'En conteo',
  REVIEW: 'En revisión',
  CLOSED: 'Cerrada',
  CANCELLED: 'Cancelada',
};
