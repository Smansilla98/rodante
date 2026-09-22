import { API_URL } from '../config';
import { clearToken, getToken } from '../auth/storage';
import type {
  AuthPayload,
  Base,
  FleetUnit,
  InventorySession,
  LifeReportPayload,
  LookupResult,
  Paginated,
  PredictionPayload,
  TelemetryPayload,
  Tire,
  TireHistoryPayload,
  TireOperationResult,
  UnitLayoutPayload,
  WorkOrder,
} from './types';

export class ApiError extends Error {
  status: number;
  code?: string;
  errors?: Record<string, string[]>;

  constructor(message: string, status: number, code?: string, errors?: Record<string, string[]>) {
    super(message);
    this.status = status;
    this.code = code;
    this.errors = errors;
  }
}

/** Callback opcional para que la UI reaccione a un 401 (limpiar sesión, ir a /login). */
let onUnauthorized: (() => void) | null = null;
export function setUnauthorizedHandler(handler: (() => void) | null): void {
  onUnauthorized = handler;
}

/**
 * Rodante usa un único token Bearer Sanctum (30 días, sin refresh). Un 401
 * significa "token vencido o revocado": limpiamos el token guardado y
 * avisamos al AuthContext para redirigir a /login — nunca reintentamos con
 * un refresh que no existe en el backend.
 */
export async function apiRequest<T>(path: string, options: RequestInit = {}): Promise<T> {
  const token = await getToken();
  const headers: Record<string, string> = {
    Accept: 'application/json',
    'Content-Type': 'application/json',
    ...(options.headers as Record<string, string> | undefined),
  };
  if (token) headers.Authorization = `Bearer ${token}`;

  const controller = new AbortController();
  const timeout = setTimeout(() => controller.abort(), 20000);

  let res: Response;
  try {
    res = await fetch(`${API_URL}${path}`, {
      ...options,
      headers,
      signal: controller.signal,
    });
  } catch (e) {
    throw new ApiError(
      e instanceof Error && e.name === 'AbortError'
        ? 'Tiempo de espera agotado. Revisá tu conexión.'
        : 'Sin conexión. Reintentá en unos segundos.',
      0,
      'NETWORK',
    );
  } finally {
    clearTimeout(timeout);
  }

  if (res.status === 401) {
    await clearToken();
    onUnauthorized?.();
    const body = await res.json().catch(() => ({}));
    throw new ApiError((body as { message?: string }).message ?? 'Sesión vencida.', 401, 'UNAUTHORIZED');
  }

  if (res.status === 204) {
    return undefined as T;
  }

  const json = await res.json().catch(() => ({}));

  if (!res.ok) {
    const body = json as { message?: string; errors?: Record<string, string[]> };
    throw new ApiError(body.message ?? `Error ${res.status}`, res.status, undefined, body.errors);
  }

  return json as T;
}

export const api = {
  login: (username: string, password: string, companyId?: number) =>
    apiRequest<AuthPayload>('/auth/token', {
      method: 'POST',
      body: JSON.stringify({
        username,
        password,
        ...(companyId != null ? { company_id: companyId } : {}),
        device: 'mobile',
      }),
    }),

  logout: () => apiRequest<{ message: string }>('/auth/token', { method: 'DELETE' }),

  me: () => apiRequest<import('./types').ApiUser>('/me'),

  dashboard: () => apiRequest<import('./types').DashboardPayload>('/dashboard'),

  bases: () => apiRequest<Base[]>('/bases'),

  retreadShops: () => apiRequest<import('./types').RetreadShop[]>('/retread-shops'),

  movementReasons: (appliesTo?: string) =>
    apiRequest<import('./types').MovementReason[]>(
      appliesTo ? `/movement-reasons?applies_to=${encodeURIComponent(appliesTo)}` : '/movement-reasons',
    ),

  tireCatalog: () => apiRequest<import('./types').TireCatalogPayload>('/tire-catalog'),

  /** Alta de una cubierta. Sin `unit_cost` (o 0) = puesta a punto, no genera costo. */
  createTire: (body: {
    tire_brand_id: number;
    tire_model_id: number;
    tire_size_id: number;
    supplier_id: number;
    base_id: number;
    dot?: string;
    purchased_at?: string;
    unit_cost?: number;
    notes?: string;
  }) =>
    apiRequest<Tire>('/tires', {
      method: 'POST',
      body: JSON.stringify(body),
    }),

  /** Modificar neumático (marca/modelo/medida/DOT/condición/observaciones) — solo Administrador. */
  updateTire: (
    tireId: number,
    body: {
      individual_number: number;
      tire_brand_id: number;
      tire_model_id: number;
      tire_size_id: number;
      condition: string;
      dot?: string;
      notes?: string;
    },
  ) =>
    apiRequest<Tire>(`/tires/${tireId}`, {
      method: 'POST',
      body: JSON.stringify(body),
    }),

  workOrders: (page = 1) => apiRequest<Paginated<WorkOrder>>(`/work-orders?page=${page}`),

  workOrder: (id: number) => apiRequest<WorkOrder>(`/work-orders/${id}`),

  createWorkOrder: (body: {
    tire_id?: number;
    tire_ids?: number[];
    /** Sin este campo, la orden queda como interna (taller propio, sin recapadora externa). */
    retread_shop_id?: number;
    type: 'RECAPADO' | 'REPARACION';
    notes?: string;
  }) =>
    apiRequest<WorkOrder>('/work-orders', {
      method: 'POST',
      body: JSON.stringify(body),
    }),

  sendWorkOrderToShop: (id: number) => apiRequest<WorkOrder>(`/work-orders/${id}/send`, { method: 'POST' }),

  closeWorkOrder: (id: number, body?: { cost?: number; notes?: string }) =>
    apiRequest<WorkOrder>(`/work-orders/${id}/close`, {
      method: 'POST',
      body: JSON.stringify(body ?? {}),
    }),

  cancelWorkOrder: (id: number, notes?: string) =>
    apiRequest<WorkOrder>(`/work-orders/${id}/cancel`, {
      method: 'POST',
      body: JSON.stringify({ notes }),
    }),

  inventorySessions: (page = 1) =>
    apiRequest<Paginated<InventorySession>>(`/inventory-sessions?page=${page}`),

  inventorySession: (id: number) =>
    apiRequest<import('./types').InventorySessionDetailPayload>(`/inventory-sessions/${id}`),

  createInventorySession: (base_id: number, notes?: string) =>
    apiRequest<InventorySession>('/inventory-sessions', {
      method: 'POST',
      body: JSON.stringify({ base_id, notes }),
    }),

  startInventorySession: (id: number) =>
    apiRequest<InventorySession>(`/inventory-sessions/${id}/start`, { method: 'POST' }),

  scanInventorySession: (id: number, q: string) =>
    apiRequest<import('./types').InventoryLine>(`/inventory-sessions/${id}/scan`, {
      method: 'POST',
      body: JSON.stringify({ q }),
    }),

  reviewInventorySession: (id: number) =>
    apiRequest<InventorySession>(`/inventory-sessions/${id}/review`, { method: 'POST' }),

  closeInventorySession: (id: number, body?: { apply_fixes?: boolean; notes?: string }) =>
    apiRequest<InventorySession>(`/inventory-sessions/${id}/close`, {
      method: 'POST',
      body: JSON.stringify(body ?? {}),
    }),

  cancelInventorySession: (id: number, notes?: string) =>
    apiRequest<InventorySession>(`/inventory-sessions/${id}/cancel`, {
      method: 'POST',
      body: JSON.stringify({ notes }),
    }),

  tires: (opts?: {
    status?: string;
    condition?: string;
    q?: string;
    base_id?: number;
    tire_size_id?: number;
    page?: number;
  }) => {
    const qs = new URLSearchParams();
    if (opts?.status) qs.set('status', opts.status);
    if (opts?.condition) qs.set('condition', opts.condition);
    if (opts?.q) qs.set('q', opts.q);
    if (opts?.base_id) qs.set('base_id', String(opts.base_id));
    if (opts?.tire_size_id) qs.set('tire_size_id', String(opts.tire_size_id));
    qs.set('page', String(opts?.page ?? 1));
    return apiRequest<Paginated<Tire>>(`/tires?${qs.toString()}`);
  },

  /** Acotado a NUEVA/NUEVA_USADA/USADA — ver TireApiController::setCondition. */
  setCondition: (tireId: number, condition: 'NUEVA' | 'NUEVA_USADA' | 'USADA') =>
    apiRequest<Tire>(`/tires/${tireId}/condition`, {
      method: 'POST',
      body: JSON.stringify({ condition }),
    }),

  lookupTire: (q: string) =>
    apiRequest<LookupResult>('/tires/lookup', {
      method: 'POST',
      body: JSON.stringify({ q }),
    }),

  tire: (id: number) => apiRequest<TireHistoryPayload>(`/tires/${id}`),

  tireHistory: (id: number) => apiRequest<TireHistoryPayload>(`/tires/${id}/history`),

  tirePrediction: (id: number) => apiRequest<PredictionPayload>(`/tires/${id}/prediction`),

  tireLifeReport: (id: number) => apiRequest<LifeReportPayload>(`/tires/${id}/life-report`),

  telemetry: () => apiRequest<TelemetryPayload>('/telemetry'),

  units: () => apiRequest<FleetUnit[]>('/units'),

  unitLayout: (id: number) => apiRequest<UnitLayoutPayload>(`/units/${id}/layout`),

  /** Cubiertas de stock compatibles con una posición (misma regla que la web). */
  positionCandidates: (unitId: number, positionId: number, q?: string) =>
    apiRequest<import('./types').PositionCandidatesPayload>(
      `/units/${unitId}/positions/${positionId}/candidates${q ? `?q=${encodeURIComponent(q)}` : ''}`,
    ),

  createIncident: (
    tireId: number,
    body: { type: string; occurred_at?: string; description?: string; notes?: string; odometer?: number },
  ) =>
    apiRequest<TireOperationResult>(`/tires/${tireId}/incident`, {
      method: 'POST',
      body: JSON.stringify(body),
    }),

  createMeasurement: (
    tireId: number,
    body: {
      measured_at?: string;
      odometer?: number;
      notes?: string;
      readings: Array<{ zone_id: number; millimeters: number }>;
    },
  ) =>
    apiRequest<TireOperationResult>(`/tires/${tireId}/measurement`, {
      method: 'POST',
      body: JSON.stringify(body),
    }),

  returnToStock: (tireId: number, body: { notes?: string; as_recap?: boolean }) =>
    apiRequest<TireOperationResult>(`/tires/${tireId}/return-stock`, {
      method: 'POST',
      body: JSON.stringify(body),
    }),

  retireTire: (tireId: number, body: { reason_id: number; notes?: string }) =>
    apiRequest<TireOperationResult>(`/tires/${tireId}/retire`, {
      method: 'POST',
      body: JSON.stringify(body),
    }),

  /** Clasificación manual de desgaste — solo válida sobre una cubierta recapada. */
  setRecapWear: (tireId: number, wear: 'NUEVA' | 'USADA') =>
    apiRequest<import('./types').Tire>(`/tires/${tireId}/recap-wear`, {
      method: 'POST',
      body: JSON.stringify({ recap_wear: wear }),
    }),

  operateUnit: (
    unitId: number,
    body: {
      /** Opcional: si se omite, el backend usa el último odómetro conocido de la unidad motriz. */
      odometer?: number;
      notes?: string;
      removals?: Array<{ tire_id: number; reason_id?: number; destination?: string; position_id?: number }>;
      installations?: Array<{ tire_id: number; position_id: number; expect_empty?: boolean }>;
    },
  ) =>
    apiRequest<TireOperationResult>(`/units/${unitId}/tire-operations`, {
      method: 'POST',
      body: JSON.stringify(body),
    }),
};
