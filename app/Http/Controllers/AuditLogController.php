<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AuditLogController extends Controller
{
    public function index(Request $request)
    {
        $logs = AuditLog::with('user')
            ->filter($request->only(['event', 'user_id', 'auditable_type', 'from', 'to']))
            ->latest()
            ->paginate(25);

        return view('audit.index', compact('logs'));
    }

    public function export(Request $request): StreamedResponse
    {
        $filename = 'audit-logs-'.now()->format('YmdHis').'.csv';

        $query = AuditLog::with('user')
            ->filter($request->only(['event', 'user_id', 'auditable_type', 'from', 'to']))
            ->latest();

        return response()->streamDownload(function () use ($query) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, ['ID', 'Event', 'User', 'Model', 'Model ID', 'Old Values', 'New Values', 'IP', 'Origin', 'Created At']);

            foreach ($query->cursor() as $log) {
                fputcsv($handle, [
                    $log->id,
                    $log->event,
                    $log->user?->email,
                    $log->auditable_type,
                    $log->auditable_id,
                    json_encode($log->old_values),
                    json_encode($log->new_values),
                    $log->ip_address,
                    $log->origin,
                    $log->created_at,
                ]);
            }

            fclose($handle);
        }, $filename, ['Content-Type' => 'text/csv']);
    }
}