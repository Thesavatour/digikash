<?php

namespace App\Http\Controllers\Frontend;

use App\Enums\KycStatus;
use App\Exceptions\NotifyErrorException;
use App\Http\Controllers\Controller;
use App\Models\Admin;
use App\Models\KycSubmission;
use App\Models\KycTemplate;
use App\Notifications\TemplateNotification;
use App\Services\Kyc\Contracts\KycLiveVerifier;
use App\Services\Kyc\Drivers\DiditKycLiveVerifier;
use App\Traits\FileManageTrait;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Throwable;

class KycSubmissionController extends Controller
{
    use FileManageTrait;

    public function __construct(
        protected KycLiveVerifier $kycLiveVerifier
    ) {}

    public function kycVerify()
    {
        $kycTemplates = KycTemplate::where('status', true)
            ->whereJsonContains('applicable_to', auth()->user()->role->value)
            ->get();

        return view('frontend.user.setting.kyc_verify', compact('kycTemplates'));
    }

    public function templateDetails($id)
    {
        $template = KycTemplate::findOrFail($id);

        return view('frontend.user.setting.partials._kyc_template_details', compact('template'))->render();
    }

    /**
     * @throws Throwable
     * @throws NotifyErrorException
     */
    public function kycSubmit(Request $request)
    {
        // Demo-mode lockdown: the seeded demo accounts are already pre-stamped
        // with the KYC status they're meant to demonstrate (approved /
        // pending). Letting an evaluator re-submit would overwrite that
        // staging data and pollute admin review queues for the next visitor.
        if (isDemoProtectedAccount(auth()->user()?->email)) {
            notifyEvs('error', __('KYC submissions are disabled for the shared demo account.'));

            return redirect()->back();
        }

        // Retrieve the KYC template based on the template_id input and the user's role.
        $templateId  = $request->input('template_id');
        $note        = $request->input('note') ?? null;
        $kycTemplate = KycTemplate::active()
            ->where('id', $templateId)
            ->whereJsonContains('applicable_to', auth()->user()->role->value)
            ->firstOrFail();

        $liveEnabled = $this->kycLiveVerifier->isEnabled();
        $liveSession = $liveEnabled ? $this->kycLiveVerifier->startSession(auth()->user()) : null;
        $isDidit = $liveEnabled && ($liveSession['mode'] ?? null) === 'redirect';
        $isBuiltinCamera = $liveEnabled && ($liveSession['mode'] ?? null) === 'camera';

        // Build dynamic validation rules based on the template fields.
        $rules = [];
        foreach ($kycTemplate->fields as $index => $field) {
            // Define the field key in the request.
            $fieldKey = "credentials.{$field['label']}";

            // Check for the 'required' flag allowing for both string 'true' and boolean true.
            $isRequired = isset($field['required']) && ($field['required'] === 'true' || $field['required'] === true);

            // Start with either a required or nullable rule.
            $rules[$fieldKey] = $isRequired ? ['required'] : ['nullable'];

            // Add additional rules based on the field type.
            if (isset($field['type']) && $field['type'] === 'file') {
                $rules[$fieldKey][] = 'file';
                // When live document is provided, template file fields may be satisfied by camera capture.
                if ($isBuiltinCamera && $request->hasFile('credentials.live_document')) {
                    $rules[$fieldKey] = ['nullable', 'file'];
                }
            } else {
                $rules[$fieldKey][] = 'string';
            }
        }

        // Live capture fields (builtin camera only — Didit uses hosted flow).
        if ($isBuiltinCamera) {
            $requireSelfie = ! empty($liveSession['require_selfie']);
            $rules['credentials.live_selfie'] = array_filter([
                $requireSelfie ? 'required' : 'nullable',
                'file',
                'mimetypes:video/webm,video/mp4,video/quicktime,image/jpeg,image/png,image/webp',
                'max:20480',
            ]);
            $rules['credentials.live_document'] = [
                'nullable',
                'file',
                'mimes:jpg,jpeg,png,webp',
                'max:10240',
            ];
        }

        if ($isDidit) {
            notifyEvs('info', __('Use “Continue with Didit” to start hosted verification.'));

            return redirect()->back();
        }

        // Validate the request with the dynamic rules.
        $validatedData = $request->validate($rules);

        if ($isBuiltinCamera && ! empty($liveSession['require_document'])) {
            $hasLiveDoc = $request->hasFile('credentials.live_document');
            $hasTemplateFile = false;
            foreach ($kycTemplate->fields as $field) {
                if (($field['type'] ?? null) === 'file' && $request->hasFile('credentials.'.$field['label'])) {
                    $hasTemplateFile = true;
                    break;
                }
            }
            if (! $hasLiveDoc && ! $hasTemplateFile) {
                throw ValidationException::withMessages([
                    'credentials.live_document' => __('Please capture a document photo or upload a document file.'),
                ]);
            }
        }

        DB::beginTransaction();

        try {
            $user               = auth()->user();
            $existingSubmission = $user->kycSubmission;

            // Prevent a new submission if one already exists and is not rejected.
            if ($existingSubmission && $existingSubmission->status !== KycStatus::REJECTED) {
                throw new NotifyErrorException(__('You already have an active KYC submission.'));
            }

            // Update an existing rejected submission or create a new one.
            if ($existingSubmission && $existingSubmission->status === KycStatus::REJECTED) {
                $submission = $existingSubmission;
                $submission->update([
                    'notes'  => $note,
                    'status' => KycStatus::PENDING,
                ]);
            } else {
                $submission = KycSubmission::create([
                    'kyc_template_id' => $kycTemplate->id,
                    'notes'           => $note,
                    'user_id'         => $user->id,
                    'status'          => KycStatus::PENDING,
                ]);
            }

            // Process each field's data.
            $submissionData = [];
            foreach ($kycTemplate->fields as $index => $field) {
                $value = $validatedData['credentials'][$field['label']] ?? null;

                // If the field is a file and a file was uploaded, handle the file upload.
                if (isset($field['type']) && $field['type'] === 'file' && $request->hasFile("credentials.{$field['label']}")) {
                    $file  = $request->file("credentials.{$field['label']}");
                    $value = $this->uploadFile($file);
                }
                $submissionData[$field['label']] = $value;
            }

            // Live captures (stored alongside template fields for admin review).
            if ($isBuiltinCamera) {
                if ($request->hasFile('credentials.live_selfie')) {
                    $submissionData['live_selfie'] = $this->uploadFile($request->file('credentials.live_selfie'));
                }
                if ($request->hasFile('credentials.live_document')) {
                    $liveDocPath = $this->uploadFile($request->file('credentials.live_document'));
                    $submissionData['live_document'] = $liveDocPath;

                    // Fill empty required template file slots with the live document capture.
                    foreach ($kycTemplate->fields as $field) {
                        if (($field['type'] ?? null) !== 'file') {
                            continue;
                        }
                        $label = $field['label'];
                        if (empty($submissionData[$label])) {
                            $submissionData[$label] = $liveDocPath;
                        }
                    }
                }

                $submissionData['live_verification'] = [
                    'driver'      => $this->kycLiveVerifier->driver(),
                    'captured_at' => now()->toIso8601String(),
                    'user_agent'  => substr((string) $request->userAgent(), 0, 500),
                ];
            }

            // Update the submission with the processed data.
            $submission->update(['submission_data' => $submissionData]);

            // Notify admin
            $admins = Admin::permission('kyc-notification')->get();
            Notification::send($admins, new TemplateNotification(
                identifier: 'kyc_admin_notify_submission',
                data: [
                    'user'     => auth()->user()->name,
                    'kyc_type' => $kycTemplate->title,
                ],
                sender: auth()->user(),
                action: route('admin.kyc.pending')
            ));

            DB::commit();

            // Notify success and redirect back.
            notifyEvs('success', __('KYC submission received successfully!'));

            return redirect()->back();
        } catch (Throwable $e) {
            DB::rollBack();
            Log::error('KYC Submission error: '.$e->getMessage());
            throw new NotifyErrorException(__('Failed to submit KYC: ').$e->getMessage());
        }
    }

    /**
     * Save template fields as a pending KYC submission, create a Didit session,
     * and redirect the user to Didit's hosted verification URL.
     */
    public function diditStart(Request $request): RedirectResponse
    {
        if (isDemoProtectedAccount(auth()->user()?->email)) {
            notifyEvs('error', __('KYC submissions are disabled for the shared demo account.'));

            return redirect()->back();
        }

        if (! $this->kycLiveVerifier instanceof DiditKycLiveVerifier || ! $this->kycLiveVerifier->isEnabled()) {
            notifyEvs('error', __('Didit verification is not enabled.'));

            return redirect()->back();
        }

        $templateId = $request->input('template_id');
        $note = $request->input('note') ?? null;
        $kycTemplate = KycTemplate::active()
            ->where('id', $templateId)
            ->whereJsonContains('applicable_to', auth()->user()->role->value)
            ->firstOrFail();

        $rules = [];
        foreach ($kycTemplate->fields as $field) {
            $fieldKey = "credentials.{$field['label']}";
            $isRequired = isset($field['required']) && ($field['required'] === 'true' || $field['required'] === true);
            $rules[$fieldKey] = $isRequired ? ['required'] : ['nullable'];
            $rules[$fieldKey][] = (isset($field['type']) && $field['type'] === 'file') ? 'file' : 'string';
        }

        $validatedData = $request->validate($rules);

        DB::beginTransaction();

        try {
            $user = auth()->user();
            $existingSubmission = $user->kycSubmission;

            if ($existingSubmission && $existingSubmission->status === KycStatus::PENDING && ! $this->isIncompleteDiditSubmission($existingSubmission)) {
                throw new NotifyErrorException(__('You already have an active KYC submission.'));
            }

            if ($existingSubmission && in_array($existingSubmission->status, [KycStatus::REJECTED, KycStatus::PENDING], true)
                && ($existingSubmission->status === KycStatus::REJECTED || $this->isIncompleteDiditSubmission($existingSubmission))) {
                $submission = $existingSubmission;
                $submission->update([
                    'kyc_template_id' => $kycTemplate->id,
                    'notes'           => $note,
                    'status'          => KycStatus::PENDING,
                ]);
            } else {
                $submission = KycSubmission::create([
                    'kyc_template_id' => $kycTemplate->id,
                    'notes'           => $note,
                    'user_id'         => $user->id,
                    'status'          => KycStatus::PENDING,
                ]);
            }

            $submissionData = [];
            foreach ($kycTemplate->fields as $field) {
                $value = $validatedData['credentials'][$field['label']] ?? null;
                if (isset($field['type']) && $field['type'] === 'file' && $request->hasFile("credentials.{$field['label']}")) {
                    $value = $this->uploadFile($request->file("credentials.{$field['label']}"));
                }
                $submissionData[$field['label']] = $value;
            }

            $session = $this->kycLiveVerifier->createSession(
                $user,
                route('user.settings.kyc.verify'),
                $submission->id
            );

            $submissionData['live_verification'] = [
                'driver'     => 'didit',
                'session_id' => $session['session_id'],
                'started_at' => now()->toIso8601String(),
                'user_agent' => substr((string) $request->userAgent(), 0, 500),
            ];

            $submission->update(['submission_data' => $submissionData]);

            // Admins are notified when Didit returns a decision (webhook), not on start.
            DB::commit();

            return redirect()->away($session['session_url']);
        } catch (NotifyErrorException $e) {
            DB::rollBack();
            throw $e;
        } catch (RuntimeException $e) {
            DB::rollBack();
            notifyEvs('error', $e->getMessage());

            return redirect()->back()->withInput();
        } catch (Throwable $e) {
            DB::rollBack();
            Log::error('Didit KYC start error: '.$e->getMessage());
            throw new NotifyErrorException(__('Failed to start Didit verification: ').$e->getMessage());
        }
    }

    /**
     * Resume an incomplete Didit session (new Didit URL for the same pending submission).
     */
    public function diditResume(): RedirectResponse
    {
        if (! $this->kycLiveVerifier instanceof DiditKycLiveVerifier || ! $this->kycLiveVerifier->isEnabled()) {
            notifyEvs('error', __('Didit verification is not enabled.'));

            return redirect()->back();
        }

        $user = auth()->user();
        $submission = $user?->kycSubmission;

        if (! $submission || ! $this->isIncompleteDiditSubmission($submission)) {
            notifyEvs('error', __('No incomplete Didit verification to resume.'));

            return redirect()->route('user.settings.kyc.verify');
        }

        try {
            $session = $this->kycLiveVerifier->createSession(
                $user,
                route('user.settings.kyc.verify'),
                $submission->id
            );

            $data = is_array($submission->submission_data) ? $submission->submission_data : [];
            $live = is_array($data['live_verification'] ?? null) ? $data['live_verification'] : [];
            $data['live_verification'] = array_merge($live, [
                'driver'     => 'didit',
                'session_id' => $session['session_id'],
                'started_at' => now()->toIso8601String(),
                'resumed_at' => now()->toIso8601String(),
            ]);
            unset($data['live_verification']['didit_status'], $data['live_verification']['decision']);
            $submission->update(['submission_data' => $data]);

            return redirect()->away($session['session_url']);
        } catch (RuntimeException $e) {
            notifyEvs('error', $e->getMessage());

            return redirect()->back();
        }
    }

    /**
     * Cancel a pending / incomplete KYC attempt so the user can start fresh.
     */
    public function diditCancel(): RedirectResponse
    {
        $user = auth()->user();
        $submission = $user?->kycSubmission;

        if (! $submission || $submission->status !== KycStatus::PENDING) {
            notifyEvs('error', __('No pending KYC verification to cancel.'));

            return redirect()->route('user.settings.kyc.verify');
        }

        // Do not allow cancel once Didit already approved (webhook may race).
        $live = is_array($submission->submission_data['live_verification'] ?? null)
            ? $submission->submission_data['live_verification']
            : [];
        if (strtolower((string) ($live['didit_status'] ?? '')) === 'approved') {
            notifyEvs('error', __('This verification was already approved and cannot be cancelled.'));

            return redirect()->route('user.settings.kyc.verify');
        }

        $submission->delete();
        notifyEvs('success', __('KYC attempt cancelled. You can start verification again.'));

        return redirect()->route('user.settings.kyc.verify');
    }

    private function isIncompleteDiditSubmission(KycSubmission $submission): bool
    {
        return kyc_submission_awaiting_didit($submission);
    }
}
