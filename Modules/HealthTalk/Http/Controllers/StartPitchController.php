<?php

namespace Modules\HealthTalk\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Services\ApiService;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class StartPitchController extends Controller
{
    /**
     * Show the Introductory Message (Start Pitch) editor - one text block per
     * language, each with its already-generated audio, if any.
     */
    public function index()
    {
        $response = ApiService::call('get-healthtalk-start-pitch');

        $pitches = ($response->successful() && isset($response['data']['pitches']))
            ? $response['data']['pitches']
            : [];

        $languages = $this->supportedLanguages();

        return view('healthtalk::start-pitch.index', compact('pitches', 'languages'));
    }

    /**
     * Save the Introductory Message text for every language. Saving (re)generates
     * the speech audio server-side so the "Start Pitch" button in the app always
     * has audio ready to play instantly - nothing is generated on demand.
     */
    public function update(Request $request)
    {
        try {
            $validated = $request->validate([
                'pitches' => 'required|array',
                'pitches.*' => 'nullable|string|max:2000',
            ]);

            $response = ApiService::call(
                name: 'update-healthtalk-start-pitch',
                params: [
                    'pitches' => $validated['pitches'],
                ],
            );

            session()->flash('toaster', $response->successful()
                ? ['type' => 'success', 'message' => 'Introductory message saved and audio generated!', 'duration' => 2000]
                : ['type' => 'error', 'message' => $response['message'] ?? 'Failed to save the introductory message.', 'duration' => 3000]);

            return redirect()->route('healthtalk.start-pitch.index');
        } catch (ValidationException $ve) {
            session()->flash('toaster', [
                'type' => 'error',
                'message' => 'Validation error: '.implode(', ', collect($ve->errors())->flatten()->toArray()),
                'duration' => 3000,
            ]);

            return redirect()->back()->withInput();
        } catch (Exception $e) {
            Log::error('Error saving Start Pitch: '.$e->getMessage());
            session()->flash('toaster', ['type' => 'error', 'message' => 'Failed to save the introductory message.', 'duration' => 3000]);

            return redirect()->back()->withInput();
        }
    }

    /**
     * Generate a one-off preview clip for text that hasn't been saved yet,
     * so the admin can listen before committing to it.
     */
    public function preview(Request $request)
    {
        try {
            $validated = $request->validate([
                'text' => 'required|string|max:2000',
                'language' => 'required|string|max:5',
            ]);

            $response = ApiService::call(
                name: 'preview-healthtalk-start-pitch',
                params: [
                    'text' => $validated['text'],
                    'language' => $validated['language'],
                ],
            );

            if ($response->successful()) {
                return response()->json([
                    'success' => true,
                    'audio_url' => $response['data']['audio_url'] ?? null,
                ]);
            }

            return response()->json([
                'success' => false,
                'message' => $response['message'] ?? 'Could not generate a preview right now.',
            ], 422);
        } catch (ValidationException $ve) {
            return response()->json(['success' => false, 'message' => $ve->getMessage()], 422);
        } catch (Exception $e) {
            Log::error('Error generating Start Pitch preview: '.$e->getMessage());

            return response()->json(['success' => false, 'message' => 'Could not generate a preview right now.'], 500);
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
