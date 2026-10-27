<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/** Laporan pelanggaran Content-Security-Policy dari browser, dicatat agar kebocoran aturan terlihat. */
class CspReportController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $report = (array) (json_decode((string) $request->getContent(), true)['csp-report'] ?? []);

        if ($report !== []) {
            Log::warning('CSP violation', array_map(
                fn ($value) => Str::limit((string) $value, 300),
                array_intersect_key($report, array_flip(['document-uri', 'violated-directive', 'effective-directive', 'blocked-uri', 'source-file', 'line-number', 'script-sample']))
            ));
        }

        return response()->noContent();
    }
}
