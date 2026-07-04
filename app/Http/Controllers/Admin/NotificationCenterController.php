<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\NotificationLog;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class NotificationCenterController extends Controller
{
    public function index(Request $request): View
    {
        $filters = $this->filters($request);
        $query = $this->filteredQuery($filters);

        $stats = [
            'total' => (clone $query)->count(),
            'sent' => (clone $query)->where('status', 'sent')->count(),
            'failed' => (clone $query)->where('status', 'failed')->count(),
            'skipped' => (clone $query)->where('status', 'skipped')->count(),
            'pending' => (clone $query)->where('status', 'pending')->count(),
        ];

        $logs = $query
            ->latest('created_at')
            ->paginate(25)
            ->withQueryString();

        return view('admin.notification-center.index', [
            'filters' => $filters,
            'logs' => $logs,
            'stats' => $stats,
            'channels' => $this->distinctValues('channel'),
            'types' => $this->distinctValues('type_key'),
        ]);
    }

    public function export(Request $request): StreamedResponse
    {
        $filters = $this->filters($request);
        $query = $this->filteredQuery($filters)->latest('created_at');
        $fileName = 'notification-center-'.now()->format('Ymd-His').'.csv';

        return response()->streamDownload(function () use ($query): void {
            $output = fopen('php://output', 'w');
            fwrite($output, "\xEF\xBB\xBF");
            fputcsv($output, [
                'created_at',
                'channel',
                'status',
                'type',
                'recipient_name',
                'recipient_email',
                'recipient_phone',
                'title',
                'body',
                'context_type',
                'context_id',
                'error',
            ]);

            $query->chunk(500, function ($logs) use ($output): void {
                foreach ($logs as $log) {
                    fputcsv($output, [
                        $log->created_at?->toDateTimeString(),
                        $log->channel,
                        $log->status,
                        $log->type_key ?: $log->notification_type,
                        $log->recipient_name,
                        $log->recipient_email,
                        $log->recipient_phone,
                        $log->title,
                        $log->body,
                        $log->context_type,
                        $log->context_id,
                        $log->error,
                    ]);
                }
            });

            fclose($output);
        }, $fileName, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function filters(Request $request): array
    {
        return [
            'q' => trim((string) $request->query('q', '')),
            'channel' => $request->query('channel'),
            'status' => $request->query('status'),
            'type' => $request->query('type'),
            'date_from' => $request->query('date_from'),
            'date_to' => $request->query('date_to'),
        ];
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return Builder<NotificationLog>
     */
    private function filteredQuery(array $filters): Builder
    {
        return NotificationLog::query()
            ->when($filters['q'], function (Builder $query, string $search): void {
                $query->where(function (Builder $query) use ($search): void {
                    $query
                        ->where('title', 'like', "%{$search}%")
                        ->orWhere('body', 'like', "%{$search}%")
                        ->orWhere('recipient_name', 'like', "%{$search}%")
                        ->orWhere('recipient_email', 'like', "%{$search}%")
                        ->orWhere('recipient_phone', 'like', "%{$search}%")
                        ->orWhere('type_key', 'like', "%{$search}%");
                });
            })
            ->when($filters['channel'], fn (Builder $query, string $channel) => $query->where('channel', $channel))
            ->when($filters['status'], fn (Builder $query, string $status) => $query->where('status', $status))
            ->when($filters['type'], fn (Builder $query, string $type) => $query->where('type_key', $type))
            ->when($filters['date_from'], fn (Builder $query, string $date) => $query->whereDate('created_at', '>=', $date))
            ->when($filters['date_to'], fn (Builder $query, string $date) => $query->whereDate('created_at', '<=', $date));
    }

    /**
     * @return array<int, string>
     */
    private function distinctValues(string $column): array
    {
        return NotificationLog::query()
            ->whereNotNull($column)
            ->distinct()
            ->orderBy($column)
            ->pluck($column)
            ->filter()
            ->values()
            ->all();
    }
}
