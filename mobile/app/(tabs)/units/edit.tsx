import { useLocalSearchParams } from 'expo-router';
import { useEffect, useState } from 'react';
import { api, ApiError } from '../../../src/api/client';
import type { FleetUnit } from '../../../src/api/types';
import { UnitForm } from '../../../src/units/UnitForm';
import { ErrorState, LoadingState } from '../../../src/ui/primitives';

export default function EditUnitScreen() {
  const { unitId } = useLocalSearchParams<{ unitId: string }>();
  const [unit, setUnit] = useState<FleetUnit | null>(null);
  const [error, setError] = useState<string | null>(null);

  useEffect(() => {
    void api
      .unitLayout(Number(unitId))
      .then((payload) => setUnit(payload.unit))
      .catch((e) => setError(e instanceof ApiError ? e.message : 'No se pudo cargar la unidad'));
  }, [unitId]);

  if (error) return <ErrorState message={error} />;
  if (!unit) return <LoadingState />;
  return <UnitForm unit={unit} />;
}
