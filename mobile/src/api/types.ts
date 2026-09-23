/**
 * Tipos derivados de las respuestas reales de la API Rodante (`routes/api.php`,
 * `app/Http/Controllers/Api/*`). El backend devuelve JSON crudo de Eloquent
 * (no hay envelope `{success,data}`); los listados paginados usan el
 * paginator por defecto de Laravel: `{data, links, meta}`.
 */

export type UserRole = 'ADMINISTRADOR' | 'JEFE_SECTOR' | 'LOGISTICA' | 'OPERARIO' | 'CONSULTA';

export type TireStatusValue = 'STOCK' | 'RESERVA' | 'INSTALADA' | 'AUXILIO' | 'EN_REPARACION' | 'DE_BAJA';

export type TireConditionValue = string;

export type UnitStatusValue = 'ACTIVA' | 'INACTIVA' | 'SPARE';

export type UnitDutyValue = 'LARGA_DISTANCIA' | 'REGIONAL' | 'URBANO' | 'NIEVE' | 'MIXTO';

export type WorkOrderStatusValue = 'ABIERTA' | 'EN_TALLER' | 'CERRADA' | 'CANCELADA';

export type WorkOrderTypeValue = 'RECAPADO' | 'REPARACION';

export type IncidentTypeValue =
  | 'PINCHADURA'
  | 'SOPLADURA'
  | 'PARCHE'
  | 'REPARACION'
  | 'RECAPADO'
  | 'INSPECCION'
  | 'DESGASTE_IRREGULAR'
  | 'CAMBIO'
  | 'OTRA';

export type InventorySessionStatusValue = 'OPEN' | 'COUNTING' | 'REVIEW' | 'CLOSED' | 'CANCELLED';

/** Respuesta de POST /auth/token (TokenController::store). */
export interface AuthPayload {
  token: string;
  token_type: 'Bearer';
  expires_at: string | null;
  user: ApiUser;
}

/** Usuario embebido en la respuesta de login. GET /me devuelve más relaciones. */
export interface ApiUser {
  id: number;
  name: string;
  username: string;
  role: UserRole;
  company_id: number;
  company?: { id: number; name: string; is_active?: boolean } | null;
  fleets?: Array<{ id: number; name: string }>;
  bases?: Array<{ id: number; name: string }>;
}

export interface Base {
  id: number;
  company_id: number;
  name: string;
  code: string;
  location: string | null;
  is_active: boolean;
}

export interface TireBrand {
  id: number;
  name: string;
  is_active?: boolean;
}

export interface TireModelRef {
  id: number;
  code: string;
  name: string;
  application?: string | null;
  winter_capable?: boolean;
}

export interface TireSize {
  id: number;
  code: string;
  alias?: string | null;
  width_mm?: number;
  aspect_ratio?: number;
  rim_inches?: number;
}

export interface TireLocation {
  id: number;
  unit_id?: number | null;
  base_id?: number | null;
  position_id?: number | null;
  unit?: FleetUnit | null;
  base?: Base | null;
  position?: UnitPosition | null;
}

export interface Tire {
  id: number;
  company_id: number;
  public_token: string;
  individual_number: number;
  dot: string | null;
  tire_brand_id?: number | null;
  tire_model_id?: number | null;
  tire_size_id?: number | null;
  status: TireStatusValue;
  condition: TireConditionValue;
  /** Solo tiene sentido cuando condition="RECAPADA" — clasificación manual, no automática. */
  recap_wear?: 'NUEVA' | 'USADA' | null;
  /** Etiqueta única calculada en el backend (combina status+condition+recap_wear+aplicación) — usar esta, no armar una propia. */
  display_condition?: string;
  notes?: string | null;
  accumulated_km: number;
  current_tread_min: string | number | null;
  purchased_at: string | null;
  retired_at: string | null;
  brand?: TireBrand | null;
  model?: TireModelRef | null;
  size?: TireSize | null;
  currentLocation?: TireLocation | null;
}

export interface FleetUnit {
  id: number;
  company_id: number;
  fleet_id: number;
  base_id: number | null;
  unit_type_id?: number | null;
  unit_configuration_id: number;
  plate: string;
  brand?: string | null;
  model_name?: string | null;
  current_odometer: number;
  status: UnitStatusValue;
  duty: UnitDutyValue;
  notes?: string | null;
  type?: { id: number; code: string; name: string; has_odometer: boolean } | null;
  configuration?: UnitConfiguration | null;
  fleet?: { id: number; name: string } | null;
  base?: Base | null;
}

export interface MovementReason {
  id: number;
  code: string;
  name: string;
  applies_to: string;
}

export interface TireCatalogBrand {
  id: number;
  name: string;
}

export interface TireCatalogModel {
  id: number;
  code: string;
  name: string;
  tire_brand_id: number;
  application?: string | null;
  application_label?: string | null;
  size_ids: number[];
}

export interface TireCatalogSize {
  id: number;
  code: string;
  alias?: string | null;
}

export interface TireCatalogSupplier {
  id: number;
  name: string;
}

export interface TireCatalogPayload {
  brands: TireCatalogBrand[];
  models: TireCatalogModel[];
  sizes: TireCatalogSize[];
  suppliers: TireCatalogSupplier[];
}

export interface UnitPosition {
  id: number;
  unit_configuration_id: number;
  code: string;
  name: string;
  axle_number: number;
  axle_role?: string | null;
  side?: string | null;
  dual: boolean;
  is_spare: boolean;
  is_liftable?: boolean;
  is_self_steer?: boolean;
  grid_row?: number | null;
  grid_col?: number | null;
  sort_order?: number | null;
}

export interface UnitConfiguration {
  id: number;
  code: string;
  name: string;
  family_code?: string | null;
  axle_count?: number;
  drive_axle_count?: number;
  position_count?: number;
  positions?: UnitPosition[];
}

export interface TireDiagnosticFlag {
  code: 'ALINEACION' | 'ROTACION';
  label: string;
  detail: string;
}

export interface UnitLayoutEntry {
  position: UnitPosition;
  tire: Tire | null;
  diagnostics?: TireDiagnosticFlag[];
}

export interface PositionCandidate {
  id: number;
  individual_number: number;
  brand?: string | null;
  model?: string | null;
  size?: string | null;
  application_label?: string | null;
}

export interface PositionCandidatesPayload {
  data: PositionCandidate[];
  hint: string;
  size?: string | null;
}

export interface UnitLayoutPayload {
  unit: FleetUnit;
  layout: UnitLayoutEntry[];
}

export interface RetreadShop {
  id: number;
  name: string;
}

export interface WorkOrder {
  id: number;
  company_id: number;
  number?: string;
  tire_id?: number | null;
  retread_shop_id: number;
  type: WorkOrderTypeValue;
  status: WorkOrderStatusValue;
  notes?: string | null;
  cost?: number | string | null;
  created_at: string;
  updated_at: string;
  tire?: Tire | null;
  items?: Array<{ id: number; tire_id: number; tire?: Tire | null }>;
  shop?: { id: number; name: string } | null;
  opener?: { id: number; name: string } | null;
  closer?: { id: number; name: string } | null;
}

export interface InventorySession {
  id: number;
  company_id: number;
  base_id: number;
  number: string;
  status: InventorySessionStatusValue;
  expected_count?: number | null;
  found_count?: number | null;
  missing_count?: number | null;
  unexpected_count?: number | null;
  notes?: string | null;
  opened_at: string | null;
  counting_started_at?: string | null;
  submitted_at?: string | null;
  closed_at?: string | null;
  cancelled_at?: string | null;
  base?: Base | null;
  opener?: { id: number; name: string } | null;
}

export interface InventoryLine {
  id: number;
  tire_id: number;
  expected_kind?: string | null;
  observed_kind?: string | null;
  delta: string;
  found: boolean;
  notes?: string | null;
  tire?: Tire | null;
  expectedBase?: Base | null;
  observedBase?: Base | null;
  scanner?: { id: number; name: string } | null;
}

export interface InventorySessionDetailPayload {
  session: InventorySession;
  lines: Paginated<InventoryLine>;
}

/**
 * Paginador de Laravel tal como lo devuelve `response()->json($paginator)`
 * (un LengthAwarePaginator crudo, sin envolver en un API Resource) — es el
 * formato PLANO, no el `{data, links, meta}` de los Resources. `current_page`
 * y `total` van directo en el objeto, no bajo `meta`. Verificado contra la
 * respuesta real de producción antes de corregir este tipo — el tipo viejo
 * declaraba `meta.total`, que no existe, y eso rompía el render de
 * inventory-sessions/[id].tsx apenas cargaba.
 */
export interface Paginated<T> {
  current_page: number;
  data: T[];
  first_page_url: string | null;
  from: number | null;
  last_page: number;
  last_page_url: string | null;
  links: Array<{ url: string | null; label: string; active: boolean }>;
  next_page_url: string | null;
  path: string;
  per_page: number;
  prev_page_url: string | null;
  to: number | null;
  total: number;
}

export interface TireMovement {
  id: number;
  type: string;
  occurred_at: string;
  from_unit_id?: number | null;
  to_unit_id?: number | null;
  from_base_id?: number | null;
  to_base_id?: number | null;
  km_delta?: number | null;
  notes?: string | null;
  fromUnit?: { id: number; plate: string } | null;
  toUnit?: { id: number; plate: string } | null;
  fromPosition?: { id: number; code: string } | null;
  toPosition?: { id: number; code: string } | null;
  user?: { id: number; name: string } | null;
}

export interface TireIncident {
  id: number;
  type: string;
  occurred_at: string;
  description?: string | null;
  notes?: string | null;
  user?: { id: number; name: string } | null;
}

export interface TireLifecycle {
  id: number;
  life_number: number;
  started_at: string;
  ended_at?: string | null;
  km_in_life?: number | null;
  condition_at_start?: string | null;
}

export interface MeasurementZone {
  id: number;
  code: string;
  name: string;
}

export interface TireHistoryPayload {
  tire: Partial<Tire> & { id: number; individual_number: number; status: TireStatusValue };
  display: string;
  timeline: unknown[];
  movements: TireMovement[];
  incidents: TireIncident[];
  lifecycles: TireLifecycle[];
  /** Zonas de la medida de esta cubierta — hay que mandar una lectura por cada una al registrar una medición. */
  measurement_zones: MeasurementZone[];
}

export interface PredictionZone {
  name: string;
  mm: number;
  remaining_km: number | null;
}

export interface PredictionPayload {
  current_mm: number | null;
  threshold_mm: number;
  wear_mm_per_1000km: number;
  remaining_km: number | null;
  confidence: 'low' | 'medium' | 'high' | string;
  source: string;
  status: 'unknown' | 'critical' | 'warn' | 'ok' | string;
  samples: number;
  ai_enabled: boolean;
  zones: PredictionZone[];
  narrative: string;
}

export interface LifeReportPayload {
  tire: Partial<Tire>;
  display: string;
  manufacture: unknown;
  timeline: unknown[];
  forecast: PredictionPayload;
  photos: Array<{ id: number; kind: string; captured_at: string | null; original_name: string | null }>;
  cost_total: number;
}

export interface TelemetryPayload {
  days: number;
  totals: Record<string, number>;
  sources: Record<string, number>;
  events: unknown[];
}

export interface DashboardPayload {
  tires_total: number;
  tires_by_status: Record<string, number>;
  open_work_orders: number;
  open_inventory_sessions: number;
}

export interface LookupResult extends Tire {}

export interface TireOperationResult {
  [key: string]: unknown;
}
