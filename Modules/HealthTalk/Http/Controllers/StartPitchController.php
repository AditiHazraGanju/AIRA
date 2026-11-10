<?php

namespace Modules\HealthTalk\Http\Controllers;

use App\Helpers\LoggerHelper;
use App\Http\Controllers\Controller;
use App\Models\HealthTalkSetting;
use App\Services\ResponseService;
use Carbon\Carbon;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\Storage;
use Google\Cloud\TextToSpeech\V1\Client\TextToSpeechClient;
use Google\Cloud\TextToSpeech\V1\SynthesisInput;
use Google\Cloud\TextToSpeech\V1\VoiceSelectionParams;
use Google\Cloud\TextToSpeech\V1\AudioConfig;
use Google\Cloud\TextToSpeech\V1\AudioEncoding;
use Google\Cloud\TextToSpeech\V1\SynthesizeSpeechRequest;

class StartPitchController extends Controller
{
    private const LANGUAGES = ['ENG', 'HIN', 'MAR', 'BEN'];

    /**
     * GET /api/v1/healthtalk/start-pitch
     */
    public function index()
    {
        try {
            $value = HealthTalkSetting::getValue('start_pitch');

            $pitches = [];
            foreach (self::LANGUAGES as $lang) {
                $audioPath = $value[$lang]['audio_path'] ?? null;
                $pitches[$lang] = [
                    'text' => $value[$lang]['text'] ?? '',
                    'audio_url' => $audioPath ? Storage::disk('gcs')->url($audioPath) : null,
                ];
            }

            return ResponseService::success('Start Pitch retrieved successfully', 200, ['pitches' => $pitches]);
        } catch (Exception $e) {
            LoggerHelper::logCritical(
                message: 'Exception occurred',
                context: __CLASS__,
                fn: __FUNCTION__,
                custom_data: ['exception' => $e, 'error_message' => $e->getMessage()]
            );

            return ResponseService::error('Failed to retrieve Start Pitch', 500, ['error' => $e->getMessage()]);
        }
    }

    /**
     * PUT /api/v1/healthtalk/start-pitch
     *
     * Saves the translated texts and generates Text-to-Speech audio
     * for each language, storing the resulting .mp3 files in GCS.
     */
    public function update(Request $request)
    {
        try {
            $rules = ['pitches' => 'required|array'];
            foreach (self::LANGUAGES as $lang) {
                $rules["pitches.{$lang}"] = 'nullable|string|max:2000';
            }
            $rules['updated_by'] = 'nullable|numeric';

            $validated = $request->validate($rules);

            $setting = HealthTalkSetting::where('setting_key', 'start_pitch')->first();
            if (! $setting) {
                $setting = new HealthTalkSetting(['setting_key' => 'start_pitch']);
            }

            $existing = $setting->setting_value ?? [];
            $updated = [];
            foreach (self::LANGUAGES as $lang) {
                $newText = $validated['pitches'][$lang] ?? '';
                $oldText = $existing[$lang]['text'] ?? '';
                $audioPath = $existing[$lang]['audio_path'] ?? null;

                if ($newText !== $oldText) {
                    // Generate new audio if text changed
                    $newAudioPath = $this->generateAudio($newText, $lang);
                    if ($newAudioPath) {
                        // Delete old audio from GCS if it exists
                        if ($audioPath) {
                            Storage::disk('gcs')->delete($audioPath);
                        }
                        $audioPath = $newAudioPath;
                    } elseif (empty(trim($newText))) {
                        // If text is cleared, delete the audio
                        if ($audioPath) {
                            Storage::disk('gcs')->delete($audioPath);
                            $audioPath = null;
                        }
                    }
                }

                $updated[$lang] = [
                    'text' => $newText,
                    'audio_path' => $audioPath,
                ];
            }

            $setting->setting_value = $updated;
            $setting->modified_on = Carbon::now();
            $setting->modified_by = $validated['updated_by'] ?? null;
            $setting->save();

            LoggerHelper::logInfo(
                message: 'Start Pitch text and audio saved successfully',
                context: __CLASS__,
                fn: __FUNCTION__
            );

            return ResponseService::success('Introductory message saved', 200);
        } catch (ValidationException $ve) {
            return ResponseService::error('Validation error', 422, ['errors' => $ve->errors()]);
        } catch (Exception $e) {
            LoggerHelper::logCritical(
                message: 'Exception occurred',
                context: __CLASS__,
                fn: __FUNCTION__,
                custom_data: ['exception' => $e, 'error_message' => $e->getMessage()]
            );

            return ResponseService::error('Failed to save Start Pitch', 500, ['error' => $e->getMessage()]);
        }
    }

    /**
     * POST /api/v1/healthtalk/start-pitch/preview
     */
    public function preview(Request $request)
    {
        try {
            $validated = $request->validate([
                'text' => 'required|string|max:2000',
                'language' => 'required|string|max:5',
            ]);

            $audioUrl = $this->generateAudio($validated['text'], $validated['language'], true);
            
            if ($audioUrl) {
                return ResponseService::success('Preview generated', 200, ['audio_url' => $audioUrl]);
            }

            return ResponseService::error('Could not generate voice preview.', 500);
        } catch (ValidationException $ve) {
            return ResponseService::error('Validation error', 422, ['errors' => $ve->errors()]);
        }
    }

    /**
     * Helper to generate TTS audio and save it to GCS.
     */
    private function generateAudio(string $text, string $lang, bool $returnBase64 = false): ?string
    {
        if (empty(trim($text))) {
            return null;
        }

        try {
            $client = new TextToSpeechClient();
            $input = (new SynthesisInput())->setText($text);

            $voiceMap = [
                'HIN' => ['code' => 'hi-IN', 'name' => 'hi-IN-Neural2-A'],
                'MAR' => ['code' => 'mr-IN', 'name' => 'mr-IN-Standard-A'],
                'BEN' => ['code' => 'bn-IN', 'name' => 'bn-IN-Standard-A'],
                'ENG' => ['code' => 'en-IN', 'name' => 'en-IN-Neural2-A'],
            ];

            $v = $voiceMap[$lang] ?? $voiceMap['ENG'];

            $voice = (new VoiceSelectionParams())
                ->setLanguageCode($v['code'])
                ->setName($v['name']);

            $audioConfig = (new AudioConfig())
                ->setAudioEncoding(AudioEncoding::MP3);

            $request = new SynthesizeSpeechRequest();
            $request->setInput($input);
            $request->setVoice($voice);
            $request->setAudioConfig($audioConfig);

            $response = $client->synthesizeSpeech($request);
            $audioContent = $response->getAudioContent();
            
            $client->close();

            if ($returnBase64) {
                return 'data:audio/mp3;base64,' . base64_encode($audioContent);
            }

            $fileName = 'healthtalk/start-pitch/pitch_' . strtolower($lang) . '_' . time() . '.mp3';
            Storage::disk('gcs')->put($fileName, $audioContent);
            
            return $fileName;
        } catch (Exception $e) {
            LoggerHelper::logCritical('TTS failed', __CLASS__, __FUNCTION__, ['error' => $e->getMessage()]);
            return null;
        }
    }
}
