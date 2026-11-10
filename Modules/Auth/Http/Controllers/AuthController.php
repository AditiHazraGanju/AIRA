<?php

namespace Modules\Auth\Http\Controllers;

use App\Helpers\LoggerHelper;
use App\Http\Controllers\Controller;
use App\Models\BeneficiaryUser;
use App\Models\CommunityEntrepreneur;
use App\Models\LoginLog;
use App\Models\State;
use App\Models\User;
use App\Services\EncryptionService;
use App\Services\MessageService;
use App\Services\ResponseService;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Tymon\JWTAuth\Facades\JWTAuth;

/**
 * Aira end-user authentication: mobile number + OTP login, registration and
 * the user's own profile. Login issues a JWT that the Aira web app and the
 * healthtalk-relay send on every request.
 */
class AuthController extends Controller
{
    /**
     * How long after a successful verify-otp an unregistered number may
     * register (time to fill in the registration form).
     */
    private const REGISTRATION_WINDOW_MINUTES = 30;

    /** Wrong guesses allowed per OTP before it is voided. */
    private const MAX_OTP_ATTEMPTS = 5;

    /** Cache key marking a number whose OTP was verified but has no account yet. */
    private static function registrationAllowedKey(string $encryptedMobile): string
    {
        return 'aira:registration-allowed:' . hash('sha256', $encryptedMobile);
    }

    public function sendOtp(Request $request)
    {
        try {
            LoggerHelper::logInfo(
                message: 'Request received',
                context: __CLASS__,
                fn: __FUNCTION__,
                custom_data: ['request_data' => $request->all()]
            );
            // Validate the request data
            // role_slug is part of the Aira app's request; only Aira users log in here.
            $validatedData = $request->validate(
                [
                    'mobile_no' => 'required|max:15',
                    'channel' => 'nullable|string|max:10|in:whatsapp,sms',
                    'role_slug' => 'required|string|in:ai-chatbot',
                ],
                [
                    'mobile_no.required' => 'Mobile number is required.',
                    'mobile_no.max' => 'Mobile number must not exceed 15 characters.',
                    'channel.string' => 'Channel must be a string.',
                    'channel.max' => 'Channel must not exceed 10 characters.',
                    'channel.in' => 'Invalid channel provided. Must be whatsapp or sms.',
                    'role_slug.required' => 'Role slug is required.',
                    'role_slug.in' => 'Invalid role slug provided. Must be ai-chatbot.',
                ]
            );

            $mobile_no = (string) $validatedData['mobile_no'];

            // Encrypt the mobile number before querying
            $encrypted_mobile_no = EncryptionService::do_encrypt($mobile_no);

            // Local development uses a fixed OTP and sends nothing; every other
            // environment generates a random one and sends it by SMS/WhatsApp.
            if (app()->environment('local')) {
                $otp = 102025;
                $response = true;
            } else {
                $otp = random_int(100000, 999999);
                $response = MessageService::sendOtpMessage(mobile_no: $mobile_no, otp: $otp, channel: $validatedData['channel'] ?? 'whatsapp');

                LoggerHelper::logInfo(
                    message: 'OTP send attempt result',
                    context: __CLASS__,
                    fn: __FUNCTION__,
                    custom_data: ['otp_sent' => $response, 'mobile_no_encrypted' => true]
                );
            }
            if ($response) {
                // Check and invalid previous OTPs for the same mobile number
                LoginLog::where('mobile_no', $encrypted_mobile_no)
                    ->where('is_active', true)
                    ->where('is_deleted', false)
                    ->update(['is_active' => false, 'is_deleted' => true, 'deleted_on' => now()]);

                // Log the OTP details in the database
                // Encrypt the mobile number before storing
                $loginLog = LoginLog::create([
                    'mobile_no' => $encrypted_mobile_no,
                    'otp' => $otp,
                    'valid_upto' => now()->addMinutes(5), // Ensure 5 is an integer
                    'retry_count' => 0,
                    'login_datetime' => now(),
                    // 'otp_msg_type' => $validatedData['channel'] ?? 'whatsapp',
                    'created_on' => now(),
                    'created_by' => null,
                    'is_deleted' => false,
                    'is_active' => true,
                ]);

                LoggerHelper::logInfo(
                    message: 'LoginLog created for OTP',
                    context: __CLASS__,
                    fn: __FUNCTION__,
                    custom_data: ['login_log_id' => $loginLog->id, 'otp_valid_upto' => $loginLog->valid_upto]
                );

                // Return the successful OTP sending response
                return ResponseService::success('OTP sent successfully', 200, ['message' => 'OTP sent successfully']);
            } else {
                // Return the failed OTP sending attempt response
                return ResponseService::error('Failed to send OTP', 500, ['error' => 'Failed to send OTP']);
            }
        } catch (ValidationException $e) {
            LoggerHelper::logCritical(
                message: 'Validation failed',
                context: __CLASS__,
                fn: __FUNCTION__,
                custom_data: [
                    'exception' => $e,
                    'error_message' => $e->getMessage(),
                    'errors' => $e->errors()
                ]
            );
            return ResponseService::error('Validation failed', 422, $e->errors());
        } catch (Exception $e) {
            LoggerHelper::logCritical(
                message: 'Exception occurred',
                context: __CLASS__,
                fn: __FUNCTION__,
                custom_data: [
                    'exception' => $e,
                    'error_message' => $e->getMessage()
                ]
            );
            return ResponseService::error('Failed to send OTP', 500, ['error' => $e->getMessage()]);
        }
    }

    /**
     * Verify OTP for AI Chatbot login.
     *
     * Determines the user type based on phone number lookup:
     * - Searches the User table by encrypted phone number
     * - If found, checks BeneficiaryUser / CommunityEntrepreneur tables to classify as 'beneficiary' or 'ce'
     * - If not found in User table, returns 'guest' so the frontend can prompt for name and call register
     *
     * @param  Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function chatbotVerifyOtp(Request $request)
    {
        try {
            LoggerHelper::logInfo(
                message: 'Chatbot verify OTP request received',
                context: __CLASS__,
                fn: __FUNCTION__,
                custom_data: ['request_data' => $request->all()]
            );

            // Validate the request data
            $validatedData = $request->validate([
                'mobile_no' => 'required|max:15',
                'otp' => 'required|digits:6|numeric',
            ], [
                'mobile_no.required' => 'Mobile number is required.',
                'mobile_no.max' => 'Mobile number must not exceed 15 characters.',
                'otp.required' => 'OTP is required.',
                'otp.digits' => 'OTP must be a 6-digit number.',
                'otp.numeric' => 'OTP must be numeric.',
            ]);

            $mobile_no = $validatedData['mobile_no'];
            $otp = $validatedData['otp'];

            // Convert mobile_no to string if it's an integer
            if (is_int($mobile_no)) {
                $mobile_no = (string) $mobile_no;
            }

            // Encrypt the mobile number before querying
            $encrypted_mobile_no = EncryptionService::do_encrypt($mobile_no);

            if (is_string($otp) && ctype_digit($otp)) {
                $otp = (int) $otp;
            }

            // Get the latest OTP entry for the given mobile number
            $latestOtpEntry = LoginLog::where('mobile_no', $encrypted_mobile_no)
                ->where('is_active', true)
                ->where('is_deleted', false)
                ->where('valid_upto', '>=', now())
                ->orderBy('login_datetime', 'desc')
                ->first();

            // If no OTP entry found
            if (!$latestOtpEntry) {
                LoggerHelper::logCritical(
                    message: 'Chatbot: No valid OTP entry found',
                    context: __CLASS__,
                    fn: __FUNCTION__,
                    custom_data: ['otp_entry_found' => false]
                );
                return ResponseService::error('No valid OTP found or OTP has expired', 404, ['error' => 'No valid OTP found or OTP has expired']);
            } elseif ($latestOtpEntry->otp !== $otp) {
                // Limit guesses: after MAX_OTP_ATTEMPTS wrong tries the OTP is
                // void and the user must request a new one.
                $latestOtpEntry->retry_count = (int) $latestOtpEntry->retry_count + 1;
                if ($latestOtpEntry->retry_count >= self::MAX_OTP_ATTEMPTS) {
                    $latestOtpEntry->is_active = false;
                    $latestOtpEntry->is_deleted = true;
                    $latestOtpEntry->deleted_on = now();
                }
                $latestOtpEntry->save();

                LoggerHelper::logCritical(
                    message: 'Chatbot: OTP mismatch',
                    context: __CLASS__,
                    fn: __FUNCTION__,
                    custom_data: ['otp_mismatch' => true, 'retry_count' => $latestOtpEntry->retry_count]
                );
                return ResponseService::error('Invalid OTP', 400, ['error' => 'Invalid OTP']);
            }

            // Mark the OTP entry as deleted and inactive
            $latestOtpEntry->is_deleted = true;
            $latestOtpEntry->deleted_on = now();
            $latestOtpEntry->is_active = false;
            $latestOtpEntry->save();

            LoggerHelper::logInfo(
                message: 'Chatbot: OTP entry marked as used',
                context: __CLASS__,
                fn: __FUNCTION__,
                custom_data: ['otp_verified' => true]
            );

            // Search for the user in the central User table by encrypted phone number
            $sh_user_data = User::where('mobile', $encrypted_mobile_no)->first();

            if ($sh_user_data === null) {
                // User does not exist in the User table → Guest
                LoggerHelper::logInfo(
                    message: 'Chatbot: User not found in central table - Guest user',
                    context: __CLASS__,
                    fn: __FUNCTION__,
                    custom_data: ['user_type' => 'guest']
                );

                // Lets this number register (chatbotRegisterGuest) for a while.
                Cache::put(
                    self::registrationAllowedKey($encrypted_mobile_no),
                    true,
                    now()->addMinutes(self::REGISTRATION_WINDOW_MINUTES)
                );

                return ResponseService::success('OTP verified. User not registered.', 200, [
                    'user_type' => 'guest',
                    'is_registered' => false,
                    'user_data' => null,
                    'token' => null,
                ]);
            }

            // Logging in through the chatbot makes this a AIRA user.
            $this->markAsTaraUser($sh_user_data);
            $this->fillProfileFromBeneficiary($sh_user_data);

            // User exists — determine their type using the user's ID
            $user_type = 'user'; // default: exists in User table but not in BeneficiaryUser or CE
            $user_data = [
                'sh_user_id' => $sh_user_data->id,
                'name' => $sh_user_data->name ?? null,
            ];

            // Check if user is a Beneficiary
            $beneficiary = BeneficiaryUser::where('sh_user_id', $sh_user_data->id)->first();
            if ($beneficiary) {
                $user_type = 'beneficiary';
                $user_data['saathi_beneficiary_id'] = $beneficiary->beneficiary_id;
                $user_data['name'] = $beneficiary->name ?? $sh_user_data->name ?? null;
            }

            // Check if user is a Community Entrepreneur
            $ce = CommunityEntrepreneur::where('sh_user_id', $sh_user_data->id)->first();
            if ($ce) {
                $user_type = 'ce';
                $user_data['saathi_ce_id'] = $ce->community_entrepreneur_id;
                $user_data['name'] = $ce->name ?? $sh_user_data->name ?? null;
            }

            // If user is both beneficiary and CE, mark as both
            if ($beneficiary && $ce) {
                $user_type = 'beneficiary_and_ce';
            }

            // Generate JWT token
            $token = null;
            try {
                $token = JWTAuth::fromUser($sh_user_data);
                LoggerHelper::logInfo(
                    message: 'Chatbot: JWT token generated',
                    context: __CLASS__,
                    fn: __FUNCTION__,
                    custom_data: ['token_generated' => true, 'user_type' => $user_type]
                );
            } catch (Exception $tokenException) {
                LoggerHelper::logCritical(
                    message: 'Chatbot: JWT Token generation failed',
                    context: __CLASS__,
                    fn: __FUNCTION__,
                    custom_data: ['exception' => $tokenException]
                );
                return ResponseService::error('Token generation failed', 500, ['error' => 'Unable to generate authentication token']);
            }

            LoggerHelper::logInfo(
                message: 'Chatbot: User verified successfully',
                context: __CLASS__,
                fn: __FUNCTION__,
                custom_data: [
                    'sh_user_id' => $sh_user_data->id,
                    'user_type' => $user_type,
                ]
            );

            return ResponseService::success('User verified successfully', 200, [
                'user_type' => $user_type,
                'is_registered' => true,
                'user_data' => $user_data,
                'token' => $token,
            ]);

        } catch (ValidationException $e) {
            LoggerHelper::logCritical(
                message: 'Chatbot: Validation failed',
                context: __CLASS__,
                fn: __FUNCTION__,
                custom_data: [
                    'exception' => $e,
                    'error_message' => $e->getMessage(),
                    'errors' => $e->errors()
                ]
            );
            return ResponseService::error('Validation failed', 422, $e->errors());
        } catch (Exception $e) {
            LoggerHelper::logCritical(
                message: 'Chatbot: Exception occurred during OTP verification',
                context: __CLASS__,
                fn: __FUNCTION__,
                custom_data: [
                    'exception' => $e,
                    'error_message' => $e->getMessage()
                ]
            );
            return ResponseService::error('Failed to verify OTP', 500, ['error' => $e->getMessage()]);
        }
    }

    /**
     * Register a guest user for the AI Chatbot.
     *
     * Called after chatbotVerifyOtp returns user_type='guest'.
     * Creates the user in the central User table with name and encrypted phone number,
     * then returns a JWT token.
     *
     * @param  Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function chatbotRegisterGuest(Request $request)
    {
        try {
            LoggerHelper::logInfo(
                message: 'Chatbot guest registration request received',
                context: __CLASS__,
                fn: __FUNCTION__,
                custom_data: ['request_data' => $request->except(['id_number', 'file'])]
            );

            // Validate the request data (multipart: the ID proof is a file)
            $validatedData = $request->validate(
                ['mobile_no' => 'required|max:15'] + $this->chatbotProfileRules(),
                [
                    'mobile_no.required' => 'Mobile number is required.',
                    'mobile_no.max' => 'Mobile number must not exceed 15 characters.',
                ] + $this->chatbotProfileMessages()
            );

            $mobile_no = $validatedData['mobile_no'];
            $file = $validatedData['file'] ?? null;

            // Convert mobile_no to string if it's an integer
            if (is_int($mobile_no)) {
                $mobile_no = (string) $mobile_no;
            }

            // Encrypt the mobile number before querying/storing
            $encrypted_mobile_no = EncryptionService::do_encrypt($mobile_no);

            // Registration hands out a token, so it needs proof that the caller
            // owns this number: a successful verify-otp for it in the last
            // REGISTRATION_WINDOW_MINUTES (see chatbotVerifyOtp). Without this,
            // anyone could get any user's token by posting their mobile number.
            $registrationKey = self::registrationAllowedKey($encrypted_mobile_no);
            if (! Cache::has($registrationKey)) {
                LoggerHelper::logCritical(
                    message: 'Chatbot: registration without a verified OTP refused',
                    context: __CLASS__,
                    fn: __FUNCTION__,
                );

                return ResponseService::error('Please verify your mobile number with an OTP first.', 403);
            }

            // Check if user already exists (prevent duplicate registration)
            $existing_user = User::where('mobile', $encrypted_mobile_no)->first();
            if ($existing_user) {
                Cache::forget($registrationKey);

                LoggerHelper::logInfo(
                    message: 'Chatbot: User already exists, skipping registration',
                    context: __CLASS__,
                    fn: __FUNCTION__,
                    custom_data: ['sh_user_id' => $existing_user->id]
                );

                // An existing Saathi user registering through the chatbot is now a AIRA user.
                $this->markAsTaraUser($existing_user);
                $this->fillProfileFromBeneficiary($existing_user);

                // Generate JWT token for the existing user
                $token = JWTAuth::fromUser($existing_user);

                // Determine user type
                $user_type = 'user';
                $user_data = [
                    'sh_user_id' => $existing_user->id,
                    'name' => $existing_user->name ?? null,
                ];

                $beneficiary = BeneficiaryUser::where('sh_user_id', $existing_user->id)->first();
                if ($beneficiary) {
                    $user_type = 'beneficiary';
                    $user_data['saathi_beneficiary_id'] = $beneficiary->beneficiary_id;
                    $user_data['name'] = $beneficiary->name ?? $existing_user->name ?? null;
                }

                $ce = CommunityEntrepreneur::where('sh_user_id', $existing_user->id)->first();
                if ($ce) {
                    $user_type = 'ce';
                    $user_data['saathi_ce_id'] = $ce->community_entrepreneur_id;
                    $user_data['name'] = $ce->name ?? $existing_user->name ?? null;
                }

                if ($beneficiary && $ce) {
                    $user_type = 'beneficiary_and_ce';
                }

                return ResponseService::success('User already registered', 200, [
                    'user_type' => $user_type,
                    'is_registered' => true,
                    'user_data' => $user_data,
                    'token' => $token,
                ]);
            }

            // Create User in the central User table, with the ID proof saved in
            // the same transaction so a failed upload leaves no half-registered user.
            $sh_user_data = DB::transaction(function () use ($validatedData, $encrypted_mobile_no, $file) {
                $user = User::create([
                    'mobile' => $encrypted_mobile_no,
                    'is_tara_user' => true, // registered through the AIRA chatbot
                ] + $this->chatbotProfileAttributes($validatedData));

                if ($file !== null) {
                    $this->storeChatbotIdProof($user, $file);
                    $user->save();
                }

                return $user;
            });

            LoggerHelper::logInfo(
                message: 'Chatbot: Guest user registered in central table',
                context: __CLASS__,
                fn: __FUNCTION__,
                custom_data: ['sh_user_id' => $sh_user_data->id]
            );

            if ($sh_user_data === null) {
                return ResponseService::error('Unable to register user', 422);
            }

            // One registration per verified OTP.
            Cache::forget($registrationKey);

            // Generate JWT token for the newly created user
            $token = null;
            try {
                $token = JWTAuth::fromUser($sh_user_data);
                LoggerHelper::logInfo(
                    message: 'Chatbot: JWT token generated for new guest user',
                    context: __CLASS__,
                    fn: __FUNCTION__,
                    custom_data: ['token_generated' => true]
                );
            } catch (Exception $tokenException) {
                LoggerHelper::logCritical(
                    message: 'Chatbot: JWT Token generation failed for new guest user',
                    context: __CLASS__,
                    fn: __FUNCTION__,
                    custom_data: ['exception' => $tokenException]
                );
                return ResponseService::error('Token generation failed', 500, ['error' => 'Unable to generate authentication token']);
            }

            return ResponseService::success('User registered successfully', 201, [
                'user_type' => 'guest',
                'is_registered' => true,
                'user_data' => [
                    'sh_user_id' => $sh_user_data->id,
                    'name' => $sh_user_data->name,
                ],
                'token' => $token,
            ]);

        } catch (ValidationException $e) {
            LoggerHelper::logCritical(
                message: 'Chatbot: Validation failed during guest registration',
                context: __CLASS__,
                fn: __FUNCTION__,
                custom_data: [
                    'exception' => $e,
                    'error_message' => $e->getMessage(),
                    'errors' => $e->errors()
                ]
            );
            return ResponseService::error('Validation failed', 422, $e->errors());
        } catch (Exception $e) {
            LoggerHelper::logCritical(
                message: 'Chatbot: Exception occurred during guest registration',
                context: __CLASS__,
                fn: __FUNCTION__,
                custom_data: [
                    'exception' => $e,
                    'error_message' => $e->getMessage()
                ]
            );
            return ResponseService::error('Failed to register user', 500, ['error' => $e->getMessage()]);
        }
    }

    /**
     * Get all states for dropdown
     */
    public function getAllStates()
    {
        try {
            Log::info('getAllStates -> Fetching all states');

            $states = State::where('is_active', true)
                ->orderBy('state_name', 'asc')
                ->get(['state_id', 'state_name', 'state_code']);

            Log::info('States fetched successfully', ['count' => $states->count()]);
            return ResponseService::success('States retrieved successfully', 200, $states);
        } catch (Exception $e) {
            Log::error('getAllStates -> Exception: ' . $e->getMessage());
            return ResponseService::error('Failed to retrieve states', 500, $e->getMessage());
        }
    }

    /**
     * Flags a user as a AIRA (AI chatbot) user, once. A failure here is
     * logged but never blocks the login itself.
     */
    /**
     * Profile of the logged-in AIRA chatbot user, from the central users table.
     */
    public function chatbotProfile(Request $request)
    {
        try {
            $user = JWTAuth::parseToken()->authenticate();

            // Also here, not just at login: tokens last a year, so users who are
            // already logged in may not log in again for a long time.
            $this->fillProfileFromBeneficiary($user);

            return ResponseService::success('Profile retrieved successfully', 200, $this->chatbotProfileData($user));
        } catch (Exception $e) {
            LoggerHelper::logCritical(
                message: 'Chatbot: Exception occurred while fetching profile',
                context: __CLASS__,
                fn: __FUNCTION__,
                custom_data: ['exception' => $e, 'error_message' => $e->getMessage()]
            );
            return ResponseService::error('Failed to retrieve profile', 500, ['error' => $e->getMessage()]);
        }
    }

    /**
     * Updates the logged-in AIRA chatbot user's profile. Multipart, same fields
     * as registration. A new file replaces the ID proof; remove_file=1 without
     * a file removes it.
     */
    public function chatbotUpdateProfile(Request $request)
    {
        try {
            $user = JWTAuth::parseToken()->authenticate();

            LoggerHelper::logInfo(
                message: 'Chatbot profile update request received',
                context: __CLASS__,
                fn: __FUNCTION__,
                custom_data: ['sh_user_id' => $user->id, 'request_data' => $request->except(['id_number', 'file'])]
            );

            $validatedData = $request->validate(
                $this->chatbotProfileRules() + ['remove_file' => 'nullable|boolean'],
                $this->chatbotProfileMessages()
            );
            $file = $validatedData['file'] ?? null;
            $previousFile = $user->id_proof_file;

            DB::transaction(function () use ($user, $validatedData, $file, $request) {
                $user->fill($this->chatbotProfileAttributes($validatedData));
                if ($file !== null) {
                    $this->storeChatbotIdProof($user, $file);
                } elseif ($request->boolean('remove_file')) {
                    $user->id_proof_file = null;
                }
                $user->save();
            });

            // Delete a replaced or removed ID proof only once the change is saved,
            // and only AIRA's own uploads: a file copied from the beneficiary
            // record is still that record's ID proof.
            if ($previousFile && $previousFile !== $user->id_proof_file && str_starts_with($previousFile, 'tara_')) {
                Storage::disk('local')->delete('uploads/documents/id_proof/' . $previousFile);
            }

            LoggerHelper::logInfo(
                message: 'Chatbot: Profile updated',
                context: __CLASS__,
                fn: __FUNCTION__,
                custom_data: ['sh_user_id' => $user->id]
            );

            return ResponseService::success('Profile updated successfully', 200, $this->chatbotProfileData($user->fresh()));
        } catch (ValidationException $e) {
            LoggerHelper::logCritical(
                message: 'Chatbot: Validation failed during profile update',
                context: __CLASS__,
                fn: __FUNCTION__,
                custom_data: ['exception' => $e, 'error_message' => $e->getMessage(), 'errors' => $e->errors()]
            );
            return ResponseService::error('Validation failed', 422, $e->errors());
        } catch (Exception $e) {
            LoggerHelper::logCritical(
                message: 'Chatbot: Exception occurred during profile update',
                context: __CLASS__,
                fn: __FUNCTION__,
                custom_data: ['exception' => $e, 'error_message' => $e->getMessage()]
            );
            return ResponseService::error('Failed to update profile', 500, ['error' => $e->getMessage()]);
        }
    }

    /** Validation for the AIRA chatbot profile fields (registration and profile edit). */
    private function chatbotProfileRules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'email' => 'nullable|email|max:255',
            'dob' => 'required|date_format:Y-m-d|before:today|after:1900-01-01',
            'gender' => 'required|in:male,female,other',
            'pincode' => 'required|digits:6',
            'income' => 'required|string|max:50',
            'id_number' => 'nullable|digits_between:1,12',
            'file' => 'nullable|file|mimes:jpeg,png,jpg,pdf|max:5120',
            'state_id' => 'required|integer|exists:saathi_states,state_id',
            'city' => 'required|string|max:255',
            'address' => 'required|string|max:500',
            'landmark' => 'nullable|string|max:255',
        ];
    }


    private function chatbotProfileMessages(): array
    {
        return [
            'name.required' => 'Name is required.',
            'name.string' => 'Name must be a string.',
            'name.max' => 'Name must not exceed 255 characters.',
            'email.email' => 'Please enter a valid email address.',
            'email.max' => 'Email must not exceed 255 characters.',
            'dob.required' => 'Date of birth is required.',
            'dob.date_format' => 'Date of birth must be a valid date.',
            'dob.before' => 'Date of birth must be in the past.',
            'dob.after' => 'Date of birth must be a valid date.',
            'gender.required' => 'Gender is required.',
            'gender.in' => 'Gender must be either male, female, or other.',
            'pincode.required' => 'Pincode is required.',
            'pincode.digits' => 'Pincode must be 6 digits.',
            'income.required' => 'Income is required.',
            'income.max' => 'Income must not exceed 50 characters.',
            'id_number.digits_between' => 'ID number must be up to 12 digits.',
            'file.file' => 'File must be an image or PDF.',
            'file.mimes' => 'File must be a jpeg, png, jpg, or pdf.',
            'file.max' => 'File must not be larger than 5 MB.',
            'state_id.required' => 'State is required.',
            'state_id.integer' => 'State ID must be numeric.',
            'state_id.exists' => 'Selected state does not exist.',
            'city.required' => 'City is required.',
            'city.max' => 'City cannot exceed 255 characters.',
            'address.required' => 'Address is required.',
            'address.max' => 'Address cannot exceed 500 characters.',
            'landmark.max' => 'Landmark cannot exceed 255 characters.',
        ];
    }

    /** Maps validated chatbot profile input to users columns. */
    private function chatbotProfileAttributes(array $data): array
    {
        return [
            'name' => $data['name'],
            'email' => $data['email'] ?? null,
            'dob' => $data['dob'],
            'gender' => $data['gender'],
            'pincode' => $data['pincode'],
            'monthly_income_range' => $data['income'],
            'id_number' => $data['id_number'] ?? null,
            'state_id' => $data['state_id'],
            'state_name' => State::find($data['state_id'])?->state_name,
            'city' => $data['city'],
            'address' => $data['address'],
            'landmark' => $data['landmark'] ?? null,
        ];
    }

    /** Saves the ID proof to the local disk and sets it on the user (not saved). */
    private function storeChatbotIdProof(User $user, UploadedFile $file): void
    {
        // tara_ + sh_user_id + current timestamp in ms; extension from the file's content
        $fileName = 'tara_' . $user->id . '_' . round(microtime(true) * 1000) . '.' . $file->extension();
        $file->storeAs('uploads/documents/id_proof', $fileName, 'local');
        $user->id_proof_file = $fileName;

        LoggerHelper::logInfo(
            message: 'Chatbot: ID proof uploaded',
            context: __CLASS__,
            fn: __FUNCTION__,
            custom_data: ['sh_user_id' => $user->id, 'file_name' => $fileName]
        );
    }

    /** The chatbot user's profile as the chatbot's Profile page shows it. */
    private function chatbotProfileData(User $user): array
    {
        return [
            'sh_user_id' => $user->id,
            'name' => $user->name,
            'mobile' => $user->mobile ? EncryptionService::do_decrypt($user->mobile) : null,
            'email' => $user->email,
            'dob' => $user->dob?->format('Y-m-d'),
            'gender' => $user->gender,
            'pincode' => $user->pincode,
            'monthly_income_range' => $user->monthly_income_range,
            'id_number' => $user->id_number,
            'id_proof_file' => $user->id_proof_file,
            'address' => $user->address,
            'landmark' => $user->landmark,
            'state_id' => $user->state_id,
            'state_name' => $user->state_name,
            'city' => $user->city,
        ];
    }

    /**
     * Existing Saathi users have their details in saathi_beneficiary, not in
     * users. Copies the user's own (self) beneficiary record into the users
     * columns the chatbot profile reads. Only empty users columns are filled,
     * so nothing the user entered in AIRA is overwritten. Never throws.
     *
     * Runs only while the profile has never been completed (no dob: AIRA
     * registration and profile saves both require it). Otherwise a field the
     * user cleared on purpose would be copied back on the next load.
     */
    private function fillProfileFromBeneficiary(User $user): void
    {
        if ($user->dob !== null) {
            return;
        }

        try {
            // Family members share the sh_user_id; the self record has no related_to_beneficiary_id.
            $beneficiary = BeneficiaryUser::where('sh_user_id', $user->id)
                ->whereNull('related_to_beneficiary_id')
                ->where(fn ($q) => $q->where('is_deleted', false)->orWhereNull('is_deleted'))
                ->orderBy('beneficiary_id')
                ->first();
            if (! $beneficiary) {
                return;
            }

            $gender = strtolower(trim((string) $beneficiary->gender));
            $fromBeneficiary = [
                'name' => $beneficiary->name,
                'dob' => $beneficiary->dob?->format('Y-m-d'),
                'gender' => in_array($gender, ['male', 'female', 'other'], true) ? $gender : null,
                'pincode' => $beneficiary->pin,
                'monthly_income_range' => $beneficiary->monthly_income_range,
                'id_number' => $beneficiary->id_number,
                // Same folder (uploads/documents/id_proof) as AIRA uploads, so the file name carries over.
                'id_proof_file' => $beneficiary->aadhar,
                'address' => $beneficiary->address,
                'landmark' => $beneficiary->landmark,
                'state_id' => $beneficiary->state_id,
                'state_name' => $beneficiary->state_name,
                'city' => $beneficiary->city,
            ];

            $filled = [];
            foreach ($fromBeneficiary as $column => $value) {
                $value = is_string($value) ? trim($value) : $value;
                if (($value !== null && $value !== '') && ($user->{$column} === null || $user->{$column} === '')) {
                    $user->{$column} = $value;
                    $filled[] = $column;
                }
            }
            if (! $filled) {
                return;
            }

            $user->save();

            LoggerHelper::logInfo(
                message: 'Chatbot: Profile filled from beneficiary record',
                context: __CLASS__,
                fn: __FUNCTION__,
                custom_data: ['sh_user_id' => $user->id, 'beneficiary_id' => $beneficiary->beneficiary_id, 'columns' => $filled]
            );
        } catch (Exception $e) {
            // Drop any unsaved values so callers don't show data that isn't stored.
            $user->setRawAttributes($user->getRawOriginal(), true);

            LoggerHelper::logCritical(
                message: 'Chatbot: could not fill profile from beneficiary record',
                context: __CLASS__,
                fn: __FUNCTION__,
                custom_data: ['sh_user_id' => $user->id, 'exception' => $e, 'error_message' => $e->getMessage()]
            );
        }
    }


    private function markAsTaraUser(User $user): void
    {
        if ($user->is_tara_user) {
            return;
        }

        try {
            $user->is_tara_user = true;
            $user->save();
        } catch (Exception $e) {
            LoggerHelper::logCritical(
                message: 'Chatbot: could not mark user as AIRA user',
                context: __CLASS__,
                fn: __FUNCTION__,
                custom_data: ['sh_user_id' => $user->id, 'exception' => $e, 'error_message' => $e->getMessage()]
            );
        }
    }
}
