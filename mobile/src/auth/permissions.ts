import type { UserRole } from '../api/types';

/**
 * Matriz de capacidades — espejo cliente de `App\Enums\UserRole` (métodos
 * `canWrite`, `canRetireOrRecap`, `canViewTelemetry`, `canValidateOdometer`,
 * `canManageCouplings`, `canChangeConfiguration`, `canManageAbm`).
 *
 * Esto es SOLO para gatear la UI (ocultar/deshabilitar acciones). La
 * autorización real ocurre en el servidor vía middleware `capability:*`
 * (`app/Http/Middleware/EnsureCapability.php`) y Gates de modelo — un 403
 * del servidor siempre puede pasar aunque la UI lo permita, y debe
 * manejarse con un aviso, no asumirse imposible.
 */

const ALL_ROLES: UserRole[] = ['ADMINISTRADOR', 'JEFE_SECTOR', 'LOGISTICA', 'OPERARIO', 'CONSULTA'];

export function roleLabel(role: UserRole | undefined | null): string {
  switch (role) {
    case 'ADMINISTRADOR':
      return 'Administrador';
    case 'JEFE_SECTOR':
      return 'Jefe de sector';
    case 'LOGISTICA':
      return 'Logística';
    case 'OPERARIO':
      return 'Operario';
    case 'CONSULTA':
      return 'Consulta';
    default:
      return '—';
  }
}

/** canWrite(): rol !== Consulta. */
export function canWrite(role: UserRole | undefined | null): boolean {
  return role != null && role !== 'CONSULTA';
}

/** canValidateOdometer(): Administrador, Jefe de sector, Logística. */
export function canValidateOdometer(role: UserRole | undefined | null): boolean {
  return role === 'ADMINISTRADOR' || role === 'JEFE_SECTOR' || role === 'LOGISTICA';
}

/** canManageCatalogs(): solo Administrador. */
export function canManageCatalogs(role: UserRole | undefined | null): boolean {
  return role === 'ADMINISTRADOR';
}

/** canManageAbm(): solo Administrador. */
export function canManageAbm(role: UserRole | undefined | null): boolean {
  return role === 'ADMINISTRADOR';
}

/** canRetireOrRecap(): Administrador, Jefe de sector. */
export function canRetireOrRecap(role: UserRole | undefined | null): boolean {
  return role === 'ADMINISTRADOR' || role === 'JEFE_SECTOR';
}

/** canViewTelemetry(): igual a canRetireOrRecap(). */
export function canViewTelemetry(role: UserRole | undefined | null): boolean {
  return canRetireOrRecap(role);
}

/** canChangeConfiguration(): Administrador, Jefe de sector. */
export function canChangeConfiguration(role: UserRole | undefined | null): boolean {
  return role === 'ADMINISTRADOR' || role === 'JEFE_SECTOR';
}

/** canManageCouplings(): Administrador, Jefe de sector, Logística. */
export function canManageCouplings(role: UserRole | undefined | null): boolean {
  return role === 'ADMINISTRADOR' || role === 'JEFE_SECTOR' || role === 'LOGISTICA';
}

export const permissions = {
  canWrite,
  canValidateOdometer,
  canManageCatalogs,
  canManageAbm,
  canRetireOrRecap,
  canViewTelemetry,
  canChangeConfiguration,
  canManageCouplings,
};

export function allRoles(): UserRole[] {
  return ALL_ROLES;
}

export function dashboardKpis(role: UserRole | undefined | null): string[] {
  const all = ['total', 'stock', 'installed', 'reserve', 'spare', 'repair', 'retired', 'km'];
  switch (role) {
    case 'CONSULTA':
      return ['total', 'installed', 'km'];
    case 'OPERARIO':
      return ['stock', 'installed', 'spare', 'repair'];
    case 'LOGISTICA':
      return ['stock', 'installed', 'reserve', 'repair'];
    default:
      return all;
  }
}
