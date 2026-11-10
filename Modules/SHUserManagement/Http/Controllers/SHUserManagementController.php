<?php

namespace Modules\SHUserManagement\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Exception;

// Services and Helpers
use App\Services\ApiService;
use App\Helpers\LoggerHelper;

class SHUserManagementController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        return view('shusermanagement::index');
    }

    /**
     * Server-side DataTable data, proxied from the Saathi API.
     */
    public function datatable(Request $request)
    {
        try {
            $response = ApiService::call('get-sh-users-datatable', $request->all());

            if ($response->successful()) {
                return response()->json($response->json());
            }

            LoggerHelper::logError(
                message: 'Failed to fetch SH users datatable data',
                context: __CLASS__,
                fn: __FUNCTION__,
                custom_data: [
                    'status' => $response->status(),
                    'response_message' => $response->json('message') ?? 'N/A',
                ]
            );

            return response()->json(['error' => 'Failed to fetch datatable data.'], 500);
        } catch (Exception $e) {
            LoggerHelper::logError(
                message: 'Exception occurred while fetching SH users datatable data',
                context: __CLASS__,
                fn: __FUNCTION__,
                custom_data: [
                    'exception' => $e,
                    'error_message' => $e->getMessage(),
                ]
            );

            return response()->json(['error' => 'Failed to fetch datatable data.'], 500);
        }
    }
}
