<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Reports\ProductionAnalyticsService;
use App\Support\CsvExport;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportsController extends Controller
{
    public function __construct(
        private readonly ProductionAnalyticsService $analytics,
    ) {
    }

    public function index(Request $request): View
    {
        return view('admin.reports.index', [
            'report' => $this->analytics->build($this->analytics->filtersFromRequest($request)),
        ]);
    }

    public function export(Request $request): StreamedResponse
    {
        $export = $this->analytics->export(
            dataset: (string) $request->query('dataset', 'summary'),
            filters: $this->analytics->filtersFromRequest($request),
        );

        return CsvExport::download(
            filename: $export['filename'],
            headers: $export['headers'],
            rows: $export['rows'],
        );
    }
}
