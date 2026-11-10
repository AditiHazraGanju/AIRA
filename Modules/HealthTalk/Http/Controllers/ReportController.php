<?php

namespace Modules\HealthTalk\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Services\ApiService;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * HealthTalk AI > Conversation Reports: how people use Aira, how they rate
 * her, which answers they marked wrong and what the Content Library is
 * missing. The page loads its data over AJAX through these proxies.
 */
class ReportController extends Controller
{
    public function index()
    {
        return view('healthtalk::reports.index');
    }

    /** KPI cards, charts and the unanswered-questions list. */
    public function overview(Request $request)
    {
        return $this->proxy('get-healthtalk-admin-overview', [
            'date_range' => (string) $request->query('date_range', ''),
        ]);
    }

    /** DataTables server-side: conversations. */
    public function sessionsDatatable(Request $request)
    {
        return $this->proxy('get-healthtalk-admin-sessions-datatable', $request->except('_token'), datatable: true);
    }

    /** One conversation's transcript, for the viewer modal. */
    public function session(string $uuid)
    {
        if (! preg_match('/^[0-9a-f-]{36}$/i', $uuid)) {
            return response()->json(['success' => false, 'message' => 'Conversation not found'], 404);
        }

        return $this->proxy('get-healthtalk-admin-session', [], ['uuid' => $uuid]);
    }

    /** DataTables server-side: answers users marked as wrong. */
    public function feedbackDatatable(Request $request)
    {
        return $this->proxy('get-healthtalk-admin-feedback-datatable', $request->except('_token'), datatable: true);
    }

    private function proxy(string $name, array $params, ?array $urlParams = null, bool $datatable = false)
    {
        try {
            $response = ApiService::call($name, $params, $urlParams);

            if ($response->successful()) {
                return response()->json($response->json());
            }

            Log::warning('HealthTalk report API call failed', ['name' => $name, 'status' => $response->status()]);
            $message = $response->status() === 403
                ? 'The API refused admin access. Check HEALTHTALK_ADMIN_USER_IDS in the API .env.'
                : 'Failed to load report data.';

            return $datatable
                ? response()->json(['draw' => (int) ($params['draw'] ?? 0), 'recordsTotal' => 0, 'recordsFiltered' => 0, 'data' => [], 'error' => $message])
                : response()->json(['success' => false, 'message' => $message], $response->status());
        } catch (Exception $e) {
            Log::error('HealthTalk report API exception', ['name' => $name, 'error' => $e->getMessage()]);

            return response()->json(['success' => false, 'message' => 'Failed to load report data.'], 500);
        }
    }
}
