import React from 'react';
import { StatusPill } from './primitives';
import {
  INVENTORY_SESSION_STATUS_LABEL,
  TIRE_STATUS_LABEL,
  UNIT_STATUS_LABEL,
  WORK_ORDER_STATUS_LABEL,
  inventorySessionStatusColor,
  inventorySessionStatusIcon,
  tireStatusColor,
  tireStatusIcon,
  unitStatusColor,
  unitStatusIcon,
  workOrderStatusColor,
  workOrderStatusIcon,
} from '../theme';

export function TireStatusBadge({ status }: { status: string }) {
  return (
    <StatusPill
      label={TIRE_STATUS_LABEL[status] ?? status}
      color={tireStatusColor(status)}
      icon={tireStatusIcon(status)}
    />
  );
}

export function UnitStatusBadge({ status }: { status: string }) {
  return (
    <StatusPill
      label={UNIT_STATUS_LABEL[status] ?? status}
      color={unitStatusColor(status)}
      icon={unitStatusIcon(status)}
    />
  );
}

export function WorkOrderStatusBadge({ status }: { status: string }) {
  return (
    <StatusPill
      label={WORK_ORDER_STATUS_LABEL[status] ?? status}
      color={workOrderStatusColor(status)}
      icon={workOrderStatusIcon(status)}
    />
  );
}

export function InventorySessionStatusBadge({ status }: { status: string }) {
  return (
    <StatusPill
      label={INVENTORY_SESSION_STATUS_LABEL[status] ?? status}
      color={inventorySessionStatusColor(status)}
      icon={inventorySessionStatusIcon(status)}
    />
  );
}
