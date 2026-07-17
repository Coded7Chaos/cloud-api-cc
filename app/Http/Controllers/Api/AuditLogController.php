<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AuditLogController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $data = $request->validate([
            'category' => ['nullable', 'string', 'in:usuarios,horarios'],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date'],
        ]);

        $logs = AuditLog::query()
            ->when($data['category'] ?? null, fn ($q, $category) => $q->where('category', $category))
            ->when($data['date_from'] ?? null, fn ($q, $date) => $q->whereDate('occurred_at', '>=', $date))
            ->when($data['date_to'] ?? null, fn ($q, $date) => $q->whereDate('occurred_at', '<=', $date))
            ->latest('occurred_at')
            ->latest('id')
            ->limit(200)
            ->get();

        return response()->json([
            'data' => $logs->map(fn (AuditLog $log) => [
                'id' => $log->id,
                'category' => $log->category,
                'action' => $log->action,
                'description' => $log->description,
                'actor' => $log->actor_name ? [
                    'id' => $log->actor_user_id,
                    'name' => $log->actor_name,
                    'email' => $log->actor_email,
                ] : null,
                'target' => $log->target_name ? [
                    'id' => $log->target_user_id,
                    'name' => $log->target_name,
                    'email' => $log->target_email,
                ] : null,
                'metadata' => $log->metadata,
                'occurred_at' => $log->occurred_at,
            ]),
        ]);
    }
}
