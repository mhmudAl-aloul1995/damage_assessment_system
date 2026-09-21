<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ArcgisWebhookDelivery;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Yajra\DataTables\Facades\DataTables;

class ArcgisWebhookDeliveryController extends Controller
{
    public function index(): View
    {
        return view('admin.arcgis_webhook_deliveries.index');
    }

    public function data(): JsonResponse
    {
        $query = ArcgisWebhookDelivery::query()->latest();

        return DataTables::of($query)
            ->editColumn('status', fn (ArcgisWebhookDelivery $delivery): string => $this->statusBadge($delivery->status))
            ->editColumn('event_names', function (ArcgisWebhookDelivery $delivery): string {
                $events = $delivery->event_names ?? [];

                return $events === [] ? '-' : e(implode(', ', $events));
            })
            ->editColumn('summary', function (ArcgisWebhookDelivery $delivery): string {
                $summary = $delivery->summary ?? [];

                if ($summary === []) {
                    return '-';
                }

                return collect(['upserted', 'deleted', 'skipped'])
                    ->map(fn (string $key): string => $key.': '.(int) ($summary[$key] ?? 0))
                    ->implode(' | ');
            })
            ->editColumn('signature_present', fn (ArcgisWebhookDelivery $delivery): string => $delivery->signature_present ? 'Yes' : 'No')
            ->editColumn('error_message', fn (ArcgisWebhookDelivery $delivery): string => e(str($delivery->error_message ?? '-')->limit(180)))
            ->editColumn('started_at', fn (ArcgisWebhookDelivery $delivery): string => optional($delivery->started_at)->format('Y-m-d H:i:s') ?? '-')
            ->editColumn('finished_at', fn (ArcgisWebhookDelivery $delivery): string => optional($delivery->finished_at)->format('Y-m-d H:i:s') ?? '-')
            ->editColumn('duration_ms', fn (ArcgisWebhookDelivery $delivery): string => $delivery->duration_ms === null ? '-' : $delivery->duration_ms.' ms')
            ->rawColumns(['status'])
            ->make(true);
    }

    private function statusBadge(string $status): string
    {
        return match ($status) {
            'success' => '<span class="badge badge-light-success">Success</span>',
            'failed' => '<span class="badge badge-light-danger">Failed</span>',
            'rejected' => '<span class="badge badge-light-warning">Rejected</span>',
            default => '<span class="badge badge-light-info">Received</span>',
        };
    }
}
