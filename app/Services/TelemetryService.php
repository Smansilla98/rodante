<?php

namespace App\Services;

use App\Models\TelemetryEvent;
use App\Models\User;
use App\Support\AccessScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class TelemetryService
{
    public function record(string $name, ?Model $entity = null, array $context = []): ?TelemetryEvent
    {
        try {
            $user = Auth::user();
            $request = request();
            $companyId = $user?->company_id
                ?? (isset($entity->company_id) ? (int) $entity->company_id : null);

            if (! $companyId) {
                return null;
            }

            if ($entity) {
                $context = $context + [
                    'entity_type' => $entity::class,
                    'entity_id' => $entity->getKey(),
                ];
            }

            return TelemetryEvent::create([
                'company_id' => (int) $companyId,
                'user_id' => $user?->id,
                'name' => $name,
                'source' => $this->source($request),
                'path' => $request?->path(),
                'context' => $context === [] ? null : $context,
                'ip_address' => $request?->ip(),
                'user_agent' => substr((string) $request?->userAgent(), 0, 512) ?: null,
                'created_at' => now(),
            ]);
        } catch (\Throwable $e) {
            Log::warning('Telemetría no registrada', [
                'name' => $name,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * @return array{totals: array<string,int>, sources: array<string,int>, events: \Illuminate\Contracts\Pagination\LengthAwarePaginator}
     */
    public function dashboard(User $user, int $days = 7): array
    {
        $query = TelemetryEvent::query();
        AccessScope::applyCompany($query, $user);
        $since = now()->subDays($days);

        $scoped = (clone $query)->where('created_at', '>=', $since);

        $totals = (clone $scoped)
            ->select('name', DB::raw('count(*) as total'))
            ->groupBy('name')
            ->pluck('total', 'name')
            ->all();

        $sources = (clone $scoped)
            ->select('source', DB::raw('count(*) as total'))
            ->groupBy('source')
            ->pluck('total', 'source')
            ->all();

        return [
            'days' => $days,
            'totals' => $totals,
            'sources' => $sources,
            'events' => $query
                ->with('user')
                ->latest('created_at')
                ->paginate(50)
                ->withQueryString(),
        ];
    }

    private function source(?Request $request): string
    {
        if (! $request) {
            return 'web';
        }
        if ($request->is('api/*')) {
            return 'api';
        }
        $header = strtolower((string) $request->header('X-Rodante-Client', ''));
        $cookie = strtolower((string) $request->cookie('rodante_client', ''));
        if ($header === 'pwa' || $cookie === 'pwa') {
            return 'pwa';
        }

        return 'web';
    }
}
