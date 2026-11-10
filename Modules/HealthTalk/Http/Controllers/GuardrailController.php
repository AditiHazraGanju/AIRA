<?php

namespace Modules\HealthTalk\Http\Controllers;

use App\Helpers\LoggerHelper;
use App\Http\Controllers\Controller;
use App\Models\HealthTalkGuardrail;
use App\Models\HealthTalkSetting;
use App\Services\ResponseService;
use Carbon\Carbon;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class GuardrailController extends Controller
{
    /** Language keys used across this app's multilingual JSON columns. */
    private const LANGUAGES = ['ENG', 'HIN', 'MAR', 'BEN'];

    /**
     * GET /api/v1/healthtalk/guardrails/all
     */
    public function getAllHealthTalkGuardrails()
    {
        try {
            LoggerHelper::logInfo(message: 'Request received', context: __CLASS__, fn: __FUNCTION__);

            $rules = HealthTalkGuardrail::where('is_deleted', false)
                ->orderByDesc('guardrail_id')
                ->get()
                ->map(fn ($rule) => [
                    'id' => $rule->guardrail_id,
                    'rule_text' => $rule->rule_text,
                    'is_active' => (bool) $rule->is_active,
                ]);

            return ResponseService::success(
                $rules->count().' guardrails retrieved successfully',
                200,
                ['guardrails' => $rules->values()]
            );
        } catch (Exception $e) {
            LoggerHelper::logCritical(
                message: 'Exception occurred',
                context: __CLASS__,
                fn: __FUNCTION__,
                custom_data: ['exception' => $e, 'error_message' => $e->getMessage()]
            );

            return ResponseService::error('Failed to retrieve guardrails', 500, ['error' => $e->getMessage()]);
        }
    }

    /**
     * POST /api/v1/healthtalk/guardrails/store
     */
    public function store(Request $request)
    {
        try {
            $validated = $request->validate([
                'rule_text' => 'required|string|max:1000',
                'created_by' => 'nullable|numeric',
            ]);

            $rule = HealthTalkGuardrail::create([
                'rule_text' => $validated['rule_text'],
                'is_active' => true,
                'is_deleted' => false,
                'created_on' => Carbon::now(),
                'created_by' => $validated['created_by'] ?? null,
            ]);

            LoggerHelper::logInfo(
                message: 'Guardrail created',
                context: __CLASS__,
                fn: __FUNCTION__,
                custom_data: ['guardrail_id' => $rule->guardrail_id]
            );

            return ResponseService::success('Guardrail added successfully', 201, ['guardrail_id' => $rule->guardrail_id]);
        } catch (ValidationException $ve) {
            return ResponseService::error('Validation error', 422, ['errors' => $ve->errors()]);
        } catch (Exception $e) {
            LoggerHelper::logCritical(
                message: 'Exception occurred',
                context: __CLASS__,
                fn: __FUNCTION__,
                custom_data: ['exception' => $e, 'error_message' => $e->getMessage()]
            );

            return ResponseService::error('Failed to add guardrail', 500, ['error' => $e->getMessage()]);
        }
    }

    /**
     * PUT /api/v1/healthtalk/guardrails/update/{id}
     */
    public function update(Request $request, $id)
    {
        try {
            $validated = $request->validate([
                'rule_text' => 'required|string|max:1000',
                'is_active' => 'nullable|boolean',
                'updated_by' => 'nullable|numeric',
            ]);

            $rule = HealthTalkGuardrail::find($id);
            if (! $rule || $rule->is_deleted) {
                return ResponseService::error('Guardrail not found', 404);
            }

            $rule->rule_text = $validated['rule_text'];
            if (array_key_exists('is_active', $validated)) {
                $rule->is_active = $request->boolean('is_active', false);
            }
            $rule->modified_on = Carbon::now();
            $rule->modified_by = $validated['updated_by'] ?? null;
            $rule->save();

            return ResponseService::success('Guardrail updated successfully', 200);
        } catch (ValidationException $ve) {
            return ResponseService::error('Validation error', 422, ['errors' => $ve->errors()]);
        } catch (Exception $e) {
            LoggerHelper::logCritical(
                message: 'Exception occurred',
                context: __CLASS__,
                fn: __FUNCTION__,
                custom_data: ['exception' => $e, 'error_message' => $e->getMessage()]
            );

            return ResponseService::error('Failed to update guardrail', 500, ['error' => $e->getMessage()]);
        }
    }

    /**
     * DELETE /api/v1/healthtalk/guardrails/delete/{id}
     */
    public function destroy(Request $request, $id)
    {
        try {
            $validated = $request->validate(['deleted_by' => 'nullable|numeric']);

            $rule = HealthTalkGuardrail::find($id);
            if (! $rule || $rule->is_deleted) {
                return ResponseService::error('Guardrail not found', 404);
            }

            $rule->is_active = false;
            $rule->is_deleted = true;
            $rule->deleted_on = Carbon::now();
            $rule->deleted_by = $validated['deleted_by'] ?? null;
            $rule->save();

            return ResponseService::success('Guardrail deleted successfully', 200);
        } catch (Exception $e) {
            LoggerHelper::logCritical(
                message: 'Exception occurred',
                context: __CLASS__,
                fn: __FUNCTION__,
                custom_data: ['exception' => $e, 'error_message' => $e->getMessage()]
            );

            return ResponseService::error('Failed to delete guardrail', 500, ['error' => $e->getMessage()]);
        }
    }

    /**
     * GET /api/v1/healthtalk/guardrails/settings
     * Returns the default "guardrail hit" message per language.
     */
    public function getSettings()
    {
        try {
            $value = HealthTalkSetting::getValue('guardrail_default_message');

            return ResponseService::success('Guardrail settings retrieved successfully', 200, [
                'default_messages' => $value,
            ]);
        } catch (Exception $e) {
            LoggerHelper::logCritical(
                message: 'Exception occurred',
                context: __CLASS__,
                fn: __FUNCTION__,
                custom_data: ['exception' => $e, 'error_message' => $e->getMessage()]
            );

            return ResponseService::error('Failed to retrieve guardrail settings', 500, ['error' => $e->getMessage()]);
        }
    }

    /**
     * PUT /api/v1/healthtalk/guardrails/settings
     */
    public function updateSettings(Request $request)
    {
        try {
            $rules = ['default_messages' => 'required|array'];
            foreach (self::LANGUAGES as $lang) {
                $rules["default_messages.{$lang}"] = 'nullable|string|max:500';
            }
            $rules['updated_by'] = 'nullable|numeric';

            $validated = $request->validate($rules);

            $setting = HealthTalkSetting::where('setting_key', 'guardrail_default_message')->first();
            if (! $setting) {
                $setting = new HealthTalkSetting(['setting_key' => 'guardrail_default_message']);
            }

            $setting->setting_value = $validated['default_messages'];
            $setting->modified_on = Carbon::now();
            $setting->modified_by = $validated['updated_by'] ?? null;
            $setting->save();

            return ResponseService::success('Default guardrail message saved', 200);
        } catch (ValidationException $ve) {
            return ResponseService::error('Validation error', 422, ['errors' => $ve->errors()]);
        } catch (Exception $e) {
            LoggerHelper::logCritical(
                message: 'Exception occurred',
                context: __CLASS__,
                fn: __FUNCTION__,
                custom_data: ['exception' => $e, 'error_message' => $e->getMessage()]
            );

            return ResponseService::error('Failed to save guardrail settings', 500, ['error' => $e->getMessage()]);
        }
    }
}
