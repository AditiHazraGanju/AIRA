<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Exception;
use Carbon\Carbon;

// Import Services
use App\Services\EncryptionService;
use App\Helpers\LoggerHelper;

// Import Models
use App\Models\User;


class SHUserApiController extends Controller
{
    /**
     * Max rows per request. The users table has 1L+ rows, so the admin
     * panel's CSV export fetches in chunks of this size instead of all at once.
     */
    private const MAX_PAGE_LENGTH = 5000;

    public function getDatatableData(Request $request)
    {
        try {
            $draw = $request->input('draw');
            $start = max((int) $request->input('start', 0), 0);
            $length = (int) $request->input('length', 10);
            $search = trim((string) $request->input('search.value', ''));
            $order = $request->input('order');

            $dateRange = $request->input('date_range');

            if ($length <= 0) {
                $length = 10;
            }
            $length = min($length, self::MAX_PAGE_LENGTH);

            $query = User::query()
                ->where(function ($q) {
                    $q->where('users.is_deleted', false)
                        ->orWhereNull('users.is_deleted');
                })
                ->select([
                    'users.id',
                    'users.name',
                    'users.email',
                    'users.mobile',
                    'users.age',
                    'users.gender',
                    'users.pincode',
                    'users.created_at',
                ]);

            // Save the base query count without search and filters
            $recordsTotal = $query->count();

            // Apply Filters (dates picked in the admin panel are IST)
            if (!empty($dateRange)) {
                $dates = explode(' to ', $dateRange);
                $appTimezone = config('app.timezone');
                $startDate = Carbon::parse($dates[0], 'Asia/Kolkata')->startOfDay()->setTimezone($appTimezone);
                $endDate = Carbon::parse($dates[1] ?? $dates[0], 'Asia/Kolkata')->endOfDay()->setTimezone($appTimezone);
                $query->whereBetween('users.created_at', [$startDate, $endDate]);
            }

            // Apply Search
            if ($search !== '') {
                $lowerSearch = strtolower($search);

                // Mobile is stored encrypted (deterministic), so it can only be matched exactly
                $mobileDigits = preg_replace('/\D/', '', $search);
                if (strlen($mobileDigits) === 12 && str_starts_with($mobileDigits, '91')) {
                    $mobileDigits = substr($mobileDigits, 2);
                }
                $encryptedMobile = strlen($mobileDigits) === 10 ? EncryptionService::do_encrypt($mobileDigits) : null;

                // Age is matched exactly when the search is a 1-3 digit number
                $age = (ctype_digit($search) && strlen($search) <= 3) ? (int) $search : null;

                $query->where(function ($q) use ($lowerSearch, $encryptedMobile, $age) {
                    $q->whereRaw('LOWER(CAST(users.name AS TEXT)) LIKE ?', ["%{$lowerSearch}%"])
                        ->orWhereRaw('LOWER(CAST(users.email AS TEXT)) LIKE ?', ["%{$lowerSearch}%"])
                        ->orWhereRaw('CAST(users.id AS TEXT) LIKE ?', ["%{$lowerSearch}%"])
                        ->orWhereRaw('CAST(users.pincode AS TEXT) LIKE ?', ["%{$lowerSearch}%"]);

                    if ($encryptedMobile) {
                        $q->orWhere('users.mobile', $encryptedMobile);
                    }

                    if ($age !== null) {
                        $q->orWhere('users.age', $age);
                    }
                });
            }

            $recordsFiltered = $query->count();

            // Apply Sorting
            $orderColumnIndex = $order[0]['column'] ?? 7;
            $orderDir = strtolower($order[0]['dir'] ?? 'desc') === 'asc' ? 'asc' : 'desc';

            // Map index to column name based on frontend definition (mobile is encrypted, not sortable)
            $columnsMap = [
                0 => 'users.id',
                1 => 'users.name',
                2 => 'users.email',
                4 => 'users.age',
                5 => 'users.gender',
                6 => 'users.pincode',
                7 => 'users.created_at',
            ];

            $orderByColumn = $columnsMap[$orderColumnIndex] ?? 'users.created_at';

            // Tie-break on id so pages stay stable when sort values repeat
            $query->orderBy($orderByColumn, $orderDir)->orderBy('users.id', $orderDir);

            // Apply Pagination
            $query->skip($start)->take($length);

            $sh_users = $query->get();

            // Modify each SH user
            $data = $sh_users->map(function ($sh_user) {
                return [
                    'id' => $sh_user->id,
                    'name' => $sh_user->name,
                    'email' => $sh_user->email,
                    // Decrypt mobile number
                    'mobile' => !empty($sh_user->mobile) ? EncryptionService::do_decrypt($sh_user->mobile) : null,
                    'age' => $sh_user->age,
                    'gender' => $sh_user->gender,
                    'pincode' => $sh_user->pincode,
                    // ISO 8601 with offset so the admin panel can convert to local time reliably
                    'joining_date' => $sh_user->created_at?->toIso8601String(),
                ];
            });

            return response()->json([
                'draw' => intval($draw),
                'recordsTotal' => $recordsTotal,
                'recordsFiltered' => $recordsFiltered,
                'data' => $data,
            ]);
        } catch (Exception $e) {
            LoggerHelper::logCritical(
                message: 'Exception occurred in getDatatableData',
                context: __CLASS__,
                fn: __FUNCTION__,
                custom_data: [
                    'exception' => $e,
                    'error_message' => $e->getMessage()
                ]
            );
            return response()->json([
                'draw' => intval($request->input('draw')),
                'recordsTotal' => 0,
                'recordsFiltered' => 0,
                'data' => [],
                'error' => 'Failed to load data.'
            ]);
        }
    }
}
