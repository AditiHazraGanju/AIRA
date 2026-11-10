<?php

namespace Modules\HealthTalk\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Services\ApiService;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class ContentLibraryController extends Controller
{
    /**
     * Display the content library - everything the AI is allowed to answer from.
     */
    public function index()
    {
        $response = ApiService::call('get-all-healthtalk-content');

        $contents = [];
        if ($response->successful() && isset($response['data']['contents'])) {
            $contents = $response['data']['contents'];
        }

        // Starter Purpose taxonomy shown in the "Purpose / Tags" select (Category > Sub-category).
        // Admins can also type a new tag directly (Select2 "tags" mode), so this is a
        // convenience default, not a hard restriction.
        // TODO: once the taxonomy needs to be managed from the admin itself, replace this
        // with ApiService::call('get-all-healthtalk-purposes').
        $purposeGroups = $this->defaultPurposeGroups();

        return view('healthtalk::content.index', compact('contents', 'purposeGroups'));
    }

    /**
     * Store a newly added content item - either typed text or an uploaded PDF.
     */
    public function store(Request $request)
    {
        try {
            $validated = $request->validate([
                'title' => 'required|string|max:255',
                'content_type' => 'required|in:text,pdf',
                'body' => 'required_if:content_type,text|nullable|string',
                'file' => 'required_if:content_type,pdf|nullable|file|mimes:pdf|max:20480', // 20 MB
                'purposes' => 'required|array|min:1',
                'purposes.*' => 'string|max:150',
            ], [
                'purposes.required' => 'Select or add at least one Purpose tag for this content.',
                'file.required_if' => 'Please attach a PDF document.',
                'body.required_if' => 'Please enter the content text.',
            ]);

            $response = ApiService::call(
                name: 'store-healthtalk-content',
                params: [
                    'title' => $validated['title'],
                    'content_type' => $validated['content_type'],
                    'body' => $validated['body'] ?? null,
                    'file' => $request->file('file'),
                    'purposes' => $validated['purposes'],
                    'is_active' => $request->boolean('is_active', true),
                ],
            );

            if ($response->successful()) {
                session()->flash('toaster', [
                    'type' => 'success',
                    'message' => 'Content added to the library successfully!',
                    'duration' => 1500,
                ]);
            } else {
                Log::error('Failed to store HealthTalk content: '.json_encode($response['errors'] ?? $response->body()));
                session()->flash('toaster', [
                    'type' => 'error',
                    'message' => $response['message'] ?? 'Failed to add content. Please try again.',
                    'duration' => 3000,
                ]);
            }

            return redirect()->route('healthtalk.content.index');
        } catch (ValidationException $ve) {
            session()->flash('toaster', [
                'type' => 'error',
                'message' => 'Validation error: '.implode(', ', collect($ve->errors())->flatten()->toArray()),
                'duration' => 3000,
            ]);

            return redirect()->back()->withInput();
        } catch (Exception $e) {
            Log::error('Error storing HealthTalk content: '.$e->getMessage());
            session()->flash('toaster', [
                'type' => 'error',
                'message' => 'An error occurred while adding the content.',
                'duration' => 3000,
            ]);

            return redirect()->back()->withInput();
        }
    }

    /**
     * Update an existing content item. The file is optional on edit -
     * leaving it empty keeps the previously uploaded document.
     */
    public function update(Request $request, $id)
    {
        try {
            $validated = $request->validate([
                'title' => 'required|string|max:255',
                'content_type' => 'required|in:text,pdf',
                'body' => 'required_if:content_type,text|nullable|string',
                'file' => 'nullable|file|mimes:pdf|max:20480',
                'purposes' => 'required|array|min:1',
                'purposes.*' => 'string|max:150',
            ]);

            $response = ApiService::call(
                name: 'update-healthtalk-content',
                params: [
                    'title' => $validated['title'],
                    'content_type' => $validated['content_type'],
                    'body' => $validated['body'] ?? null,
                    'file' => $request->file('file'),
                    'purposes' => $validated['purposes'],
                    'is_active' => $request->boolean('is_active', true),
                ],
                urlParams: ['id' => $id],
            );

            if ($response->successful()) {
                session()->flash('toaster', [
                    'type' => 'success',
                    'message' => 'Content updated successfully!',
                    'duration' => 1500,
                ]);
            } else {
                Log::error('Failed to update HealthTalk content: '.json_encode($response['errors'] ?? $response->body()));
                session()->flash('toaster', [
                    'type' => 'error',
                    'message' => $response['message'] ?? 'Failed to update content.',
                    'duration' => 3000,
                ]);
            }

            return redirect()->route('healthtalk.content.index');
        } catch (ValidationException $ve) {
            session()->flash('toaster', [
                'type' => 'error',
                'message' => 'Validation error: '.implode(', ', collect($ve->errors())->flatten()->toArray()),
                'duration' => 3000,
            ]);

            return redirect()->back();
        } catch (Exception $e) {
            Log::error('Error updating HealthTalk content: '.$e->getMessage());
            session()->flash('toaster', [
                'type' => 'error',
                'message' => 'Failed to update content.',
                'duration' => 3000,
            ]);

            return redirect()->back();
        }
    }

    /**
     * Enable/disable a content item without deleting it - disabled content
     * is excluded when the AI's context is assembled.
     */
    public function toggleStatus(Request $request, $id)
    {
        try {
            $validated = $request->validate(['status' => 'required|boolean']);

            $response = ApiService::call(
                name: 'update-healthtalk-content',
                params: [
                    'is_active' => $validated['status'],
                ],
                urlParams: ['id' => $id],
            );

            if ($response->successful()) {
                return response()->json(['success' => true, 'message' => 'Status updated successfully!']);
            }

            return response()->json([
                'success' => false,
                'message' => 'Failed to update status.',
                'errors' => $response['errors'] ?? 'Unknown error',
            ], 422);
        } catch (Exception $e) {
            Log::error('Error toggling HealthTalk content status: '.$e->getMessage());

            return response()->json(['success' => false, 'message' => 'Failed to update status.'], 500);
        }
    }

    /**
     * Remove a content item permanently.
     */
    public function destroy($id)
    {
        try {
            $response = ApiService::call(
                name: 'delete-healthtalk-content',
                params: [],
                urlParams: ['id' => $id],
            );

            if ($response->successful()) {
                return response()->json(['success' => true, 'message' => 'Content deleted successfully!']);
            }

            return response()->json([
                'success' => false,
                'message' => 'Failed to delete content.',
                'errors' => $response['errors'] ?? 'Unknown error',
            ], 422);
        } catch (Exception $e) {
            Log::error('Error deleting HealthTalk content: '.$e->getMessage());

            return response()->json(['success' => false, 'message' => 'Failed to delete content.'], 500);
        }
    }

    private function defaultPurposeGroups(): array
    {
        return [
            'Hospicash' => ['Product Overview', 'Enrollment', 'Claims', 'Pricing'],
            'Products' => ['General', 'Comparison'],
            'Support' => ['FAQ', 'Troubleshooting'],
            'Onboarding' => ['CE Training', 'Sales Pitch'],
        ];
    }
}
