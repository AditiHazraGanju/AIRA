<?php

namespace Modules\HealthTalk\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Services\ApiService;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class GuardrailController extends Controller
{
    /**
     * List every "what the AI can't talk about" rule, plus the default message
     * the AI falls back to (per language) whenever a rule is hit.
     */
    public function index()
    {
        $rulesResponse = ApiService::call('get-all-healthtalk-guardrails');
        $rules = ($rulesResponse->successful() && isset($rulesResponse['data']['guardrails']))
            ? $rulesResponse['data']['guardrails']
            : [];

        $settingsResponse = ApiService::call('get-healthtalk-guardrail-settings');
        $defaultMessages = ($settingsResponse->successful() && isset($settingsResponse['data']['default_messages']))
            ? $settingsResponse['data']['default_messages']
            : [];

        $languages = $this->supportedLanguages();

        return view('healthtalk::guardrails.index', compact('rules', 'defaultMessages', 'languages'));
    }

    /**
     * Add a new guardrail rule - free text describing what the AI must not tell users.
     */
    public function store(Request $request)
    {
        try {
            $validated = $request->validate([
                'rule_text' => 'required|string|max:1000',
            ], [
                'rule_text.required' => 'Describe what the AI must not talk about.',
            ]);

            $response = ApiService::call(
                name: 'store-healthtalk-guardrail',
                params: [
                    'rule_text' => $validated['rule_text'],
                    'is_active' => true,
                ],
            );

            session()->flash('toaster', $response->successful()
                ? ['type' => 'success', 'message' => 'Guardrail added successfully!', 'duration' => 1500]
                : ['type' => 'error', 'message' => 'Failed to add guardrail.', 'duration' => 3000]);

            return redirect()->route('healthtalk.guardrails.index');
        } catch (ValidationException $ve) {
            session()->flash('toaster', [
                'type' => 'error',
                'message' => 'Validation error: '.implode(', ', collect($ve->errors())->flatten()->toArray()),
                'duration' => 3000,
            ]);

            return redirect()->back();
        } catch (Exception $e) {
            Log::error('Error storing guardrail: '.$e->getMessage());
            session()->flash('toaster', ['type' => 'error', 'message' => 'Failed to add guardrail.', 'duration' => 3000]);

            return redirect()->back();
        }
    }

    /**
     * Update an existing guardrail rule.
     */
    public function update(Request $request, $id)
    {
        try {
            $validated = $request->validate([
                'rule_text' => 'required|string|max:1000',
            ]);

            $response = ApiService::call(
                name: 'update-healthtalk-guardrail',
                params: [
                    'rule_text' => $validated['rule_text'],
                    'is_active' => $request->boolean('is_active', false),
                ],
                urlParams: ['id' => $id],
            );

            session()->flash('toaster', $response->successful()
                ? ['type' => 'success', 'message' => 'Guardrail updated successfully!', 'duration' => 1500]
                : ['type' => 'error', 'message' => 'Failed to update guardrail.', 'duration' => 3000]);

            return redirect()->route('healthtalk.guardrails.index');
        } catch (ValidationException $ve) {
            session()->flash('toaster', [
                'type' => 'error',
                'message' => 'Validation error: '.implode(', ', collect($ve->errors())->flatten()->toArray()),
                'duration' => 3000,
            ]);

            return redirect()->back();
        } catch (Exception $e) {
            Log::error('Error updating guardrail: '.$e->getMessage());
            session()->flash('toaster', ['type' => 'error', 'message' => 'Failed to update guardrail.', 'duration' => 3000]);

            return redirect()->back();
        }
    }

    /**
     * Remove a guardrail rule.
     */
    public function destroy($id)
    {
        try {
            $response = ApiService::call(
                name: 'delete-healthtalk-guardrail',
                params: [],
                urlParams: ['id' => $id],
            );

            if ($response->successful()) {
                return response()->json(['success' => true, 'message' => 'Guardrail deleted successfully!']);
            }

            return response()->json(['success' => false, 'message' => 'Failed to delete guardrail.'], 422);
        } catch (Exception $e) {
            Log::error('Error deleting guardrail: '.$e->getMessage());

            return response()->json(['success' => false, 'message' => 'Failed to delete guardrail.'], 500);
        }
    }

    /**
     * Save the default message the AI sends whenever a guardrail is hit - one per language,
     * so the refusal is understandable in whichever language the conversation is in.
     */
    public function updateSettings(Request $request)
    {
        try {
            $validated = $request->validate([
                'default_messages' => 'required|array',
                'default_messages.*' => 'nullable|string|max:500',
            ]);

            $response = ApiService::call(
                name: 'update-healthtalk-guardrail-settings',
                params: [
                    'default_messages' => $validated['default_messages'],
                ],
            );

            session()->flash('toaster', $response->successful()
                ? ['type' => 'success', 'message' => 'Default guardrail message saved!', 'duration' => 1500]
                : ['type' => 'error', 'message' => 'Failed to save default message.', 'duration' => 3000]);

            return redirect()->route('healthtalk.guardrails.index');
        } catch (ValidationException $ve) {
            session()->flash('toaster', [
                'type' => 'error',
                'message' => 'Validation error: '.implode(', ', collect($ve->errors())->flatten()->toArray()),
                'duration' => 3000,
            ]);

            return redirect()->back();
        } catch (Exception $e) {
            Log::error('Error saving guardrail settings: '.$e->getMessage());
            session()->flash('toaster', ['type' => 'error', 'message' => 'Failed to save default message.', 'duration' => 3000]);

            return redirect()->back();
        }
    }

    private function supportedLanguages(): array
    {
        return [
            'ENG' => 'English',
            'HIN' => 'Hindi',
            'MAR' => 'Marathi',
            'BEN' => 'Bengali',
        ];
    }
}
