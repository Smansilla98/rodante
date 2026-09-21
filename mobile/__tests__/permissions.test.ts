import type { UserRole } from '../src/api/types';
import {
  allRoles,
  canChangeConfiguration,
  canManageAbm,
  canManageCouplings,
  canRetireOrRecap,
  canValidateOdometer,
  canViewTelemetry,
  canWrite,
} from '../src/auth/permissions';

/**
 * Espejo cliente de App\Enums\UserRole (backend). Cada caso acá corresponde
 * 1:1 a un método de ese enum — ver app/Enums/UserRole.php.
 */
const EXPECTED: Record<UserRole, {
  canWrite: boolean;
  canValidateOdometer: boolean;
  canManageAbm: boolean;
  canRetireOrRecap: boolean;
  canViewTelemetry: boolean;
  canChangeConfiguration: boolean;
  canManageCouplings: boolean;
}> = {
  ADMINISTRADOR: {
    canWrite: true,
    canValidateOdometer: true,
    canManageAbm: true,
    canRetireOrRecap: true,
    canViewTelemetry: true,
    canChangeConfiguration: true,
    canManageCouplings: true,
  },
  JEFE_SECTOR: {
    canWrite: true,
    canValidateOdometer: true,
    canManageAbm: false,
    canRetireOrRecap: true,
    canViewTelemetry: true,
    canChangeConfiguration: true,
    canManageCouplings: true,
  },
  LOGISTICA: {
    canWrite: true,
    canValidateOdometer: true,
    canManageAbm: false,
    canRetireOrRecap: false,
    canViewTelemetry: false,
    canChangeConfiguration: false,
    canManageCouplings: true,
  },
  OPERARIO: {
    canWrite: true,
    canValidateOdometer: false,
    canManageAbm: false,
    canRetireOrRecap: false,
    canViewTelemetry: false,
    canChangeConfiguration: false,
    canManageCouplings: false,
  },
  CONSULTA: {
    canWrite: false,
    canValidateOdometer: false,
    canManageAbm: false,
    canRetireOrRecap: false,
    canViewTelemetry: false,
    canChangeConfiguration: false,
    canManageCouplings: false,
  },
};

describe('permissions matrix', () => {
  it('covers every UserRole value', () => {
    expect(allRoles().sort()).toEqual(Object.keys(EXPECTED).sort());
  });

  it.each(allRoles())('matches the backend capability matrix for %s', (role) => {
    const expected = EXPECTED[role];
    expect(canWrite(role)).toBe(expected.canWrite);
    expect(canValidateOdometer(role)).toBe(expected.canValidateOdometer);
    expect(canManageAbm(role)).toBe(expected.canManageAbm);
    expect(canRetireOrRecap(role)).toBe(expected.canRetireOrRecap);
    expect(canViewTelemetry(role)).toBe(expected.canViewTelemetry);
    expect(canChangeConfiguration(role)).toBe(expected.canChangeConfiguration);
    expect(canManageCouplings(role)).toBe(expected.canManageCouplings);
  });

  it('treats null/undefined role as no permissions', () => {
    expect(canWrite(null)).toBe(false);
    expect(canWrite(undefined)).toBe(false);
    expect(canRetireOrRecap(null)).toBe(false);
  });
});
