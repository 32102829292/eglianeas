<?php

use App\Http\Controllers\Admin\AboutController;
use App\Http\Controllers\Admin\ActivityLogController;
use App\Http\Controllers\Admin\AnnouncementController;
use App\Http\Controllers\Admin\BillingController;
use App\Http\Controllers\Admin\BillingController as AdminBillingController;
use App\Http\Controllers\Admin\BirFormsController as AdminBirFormsController;
use App\Http\Controllers\Admin\BirFormTypeController;
use App\Http\Controllers\Admin\ChatbotController as AdminChatbotController;
use App\Http\Controllers\Admin\ClientController as AdminClientController;
use App\Http\Controllers\Admin\CollectionController as AdminCollectionController;
use App\Http\Controllers\Admin\ConfidentialityController as AdminConfidentialityController;
use App\Http\Controllers\Admin\DashboardController as AdminDashboardController;
use App\Http\Controllers\Admin\DistributionController as AdminDistributionController;
use App\Http\Controllers\Admin\ImpersonateController;
use App\Http\Controllers\Admin\KaizenConcernController;
use App\Http\Controllers\Admin\OtherServiceController as AdminOtherServiceController;
use App\Http\Controllers\Admin\PriorityItemController;
use App\Http\Controllers\Admin\ProfileController as AdminProfileController;
use App\Http\Controllers\Admin\ServiceTrackerController as AdminServiceTrackerController;
use App\Http\Controllers\Admin\SurveyController as AdminSurveyController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Admin\WeeklyBookkeepingController as AdminWeeklyBookkeepingController;
use App\Http\Controllers\Auth\SecurityController;
use App\Http\Controllers\Auth\WebauthnController;
use App\Http\Controllers\ChatbotController;
use App\Http\Controllers\Client\ApprovalController as ClientApprovalController;
use App\Http\Controllers\Client\BillingController as ClientBillingController;
use App\Http\Controllers\Client\CollectionController as ClientCollectionController;
use App\Http\Controllers\Client\DashboardController as ClientDashboardController;
use App\Http\Controllers\Client\DistributionController as ClientDistributionController;
use App\Http\Controllers\Client\GeocodeController as ClientGeocodeController;
use App\Http\Controllers\Client\OtherServiceController as ClientOtherServiceController;
use App\Http\Controllers\Client\ProfileController as ClientProfileController;
use App\Http\Controllers\Client\ServiceTrackerController as ClientServiceTrackerController;
use App\Http\Controllers\Client\SurveyController as ClientSurveyController;
use App\Http\Controllers\DailyJournalController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\PushSubscriptionController;
use App\Http\Controllers\TermsController;
use App\Models\AboutContent;
use App\Models\Announcement;
use App\Models\CompanyCertificate;
use App\Models\CoreValue;
use App\Models\CorViewLog;
use App\Models\Document;
use App\Models\TeamMember;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;

Route::get('/', HomeController::class)->name('home');

Route::get('/chatbot/config', [ChatbotController::class, 'config'])->name('chatbot.config');

require __DIR__.'/auth.php';

Route::get('/terms', [TermsController::class, 'show'])->name('terms');

Route::middleware('auth')->post('/terms/acknowledge', [AdminConfidentialityController::class, 'acknowledge'])
    ->name('terms.acknowledge.store');

Route::view('/help', 'help')->name('help');

Route::get('/about', function () {
    $about = AboutContent::instance();
    $coreValues = CoreValue::ordered()->get();
    $certificates = CompanyCertificate::ordered()->get();
    $teamMembers = TeamMember::ordered()->get();

    return view('about', compact('about', 'coreValues', 'certificates', 'teamMembers'));
})->name('about.public');

Route::get('/certificates/{certificate}/file', function (CompanyCertificate $certificate) {
    abort_unless(Storage::disk('supabase')->exists($certificate->file_path), 404);
    $temporaryUrl = Storage::disk('supabase')->temporaryUrl($certificate->file_path, now()->addHours(1));

    return redirect($temporaryUrl)->header('Cache-Control', 'public, max-age=86400');
})->name('certificates.file');

Route::get('/announcements/{announcement}/image', function (Announcement $announcement) {
    abort_unless($announcement->hasImage(), 404);
    $temporaryUrl = Storage::disk('supabase')->temporaryUrl($announcement->image_path, now()->addHour());

    return redirect($temporaryUrl)->header('Cache-Control', 'public, max-age=86400');
})->name('announcements.image');

Route::middleware(['auth', 'client.approved', 'client.survey'])->group(function () {
    Route::get('/dashboard', DashboardController::class)->name('dashboard');
});

Route::middleware(['auth', 'client.survey'])->group(function () {
    Route::get('/security', [SecurityController::class, 'index'])->name('security.index');
    Route::post('/security/pin', [SecurityController::class, 'setPin'])->name('security.pin');

    Route::get('/webauthn/register/options', [WebauthnController::class, 'options'])->name('webauthn.register.options');
    Route::post('/webauthn/register/verify', [WebauthnController::class, 'verify'])->name('webauthn.register.verify');
    Route::post('/webauthn/test/options', [WebauthnController::class, 'testOptions'])->name('webauthn.test.options');
    Route::post('/webauthn/test/verify', [WebauthnController::class, 'testVerify'])->name('webauthn.test.verify');
    Route::delete('/webauthn/credentials/{credential}', [WebauthnController::class, 'destroy'])->name('webauthn.credentials.destroy');

    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    Route::get('/notifications', [NotificationController::class, 'index'])->name('notifications.index');
    Route::get('/notifications/{notification}/open', [NotificationController::class, 'open'])->name('notifications.open');
    Route::post('/notifications/{notification}/read', [NotificationController::class, 'markRead'])->name('notifications.read');
    Route::post('/notifications/read-all', [NotificationController::class, 'readAll'])->name('notifications.read-all');

    Route::post('/push/subscribe', [PushSubscriptionController::class, 'subscribe'])->name('push.subscribe');
    Route::post('/push/unsubscribe', [PushSubscriptionController::class, 'unsubscribe'])->name('push.unsubscribe');
    Route::get('/push/vapid-key', [PushSubscriptionController::class, 'vapidKey'])->name('push.vapid-key');
    Route::post('/push/test', [PushSubscriptionController::class, 'test'])->name('push.test');

    Route::get('/payment-image/{type}/{index?}', [BillingController::class, 'paymentImage'])->name('payment.image');

    Route::get('/documents/{document}/view', function (Document $document, Request $request) {
        $user = $request->user();
        abort_unless($document->client_id === $user->id || $user->isStaffOrAdmin(), 403);
        abort_unless(Storage::disk('supabase')->exists($document->path), 404);

        CorViewLog::create([
            'document_id' => $document->id,
            'viewed_by' => $user->id,
            'viewed_at' => now(),
        ]);

        return view('document-viewer', [
            'document' => $document,
            'viewerName' => $user->name,
            'viewedAt' => now(),
        ]);
    })->name('documents.view');

    Route::get('/documents/{document}/file', function (Document $document, Request $request) {
        $user = $request->user();
        abort_unless($document->client_id === $user->id || $user->isStaffOrAdmin(), 403);
        abort_unless(Storage::disk('supabase')->exists($document->path), 404);

        CorViewLog::create([
            'document_id' => $document->id,
            'viewed_by' => $user->id,
            'viewed_at' => now(),
        ]);

        $temporaryUrl = Storage::disk('supabase')->temporaryUrl($document->path, now()->addMinutes(30));

        return redirect($temporaryUrl)->withHeaders([
            'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0',
            'Pragma' => 'no-cache',
        ]);
    })->name('documents.file');
});

Route::middleware(['auth', 'role:admin,staff,supervisor', 'admin.confidentiality'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/users/{user}/photo', [UserController::class, 'photo'])
        ->name('users.photo');
});

// Each authenticated user may review only their own signature image.
Route::middleware('auth')->get('/confidentiality/signatures/{signature}/image', [AdminConfidentialityController::class, 'signatureImage'])
    ->name('confidentiality.signature.image');

Route::middleware(['auth', 'role:admin,staff,supervisor', 'admin.confidentiality'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/dashboard', AdminDashboardController::class)->name('dashboard');

    Route::get('/surveys', [AdminSurveyController::class, 'index'])->name('surveys.index');

    Route::get('/profile', [AdminProfileController::class, 'index'])->name('profile.index');
    Route::patch('/profile', [AdminProfileController::class, 'update'])->name('profile.update');

    Route::get('/announcements', [AnnouncementController::class, 'index'])->name('announcements.index');
    Route::post('/announcements', [AnnouncementController::class, 'store'])->name('announcements.store');
    Route::delete('/announcements/{announcement}', [AnnouncementController::class, 'destroy'])->name('announcements.destroy');

    Route::get('/chatbot', [AdminChatbotController::class, 'edit'])->name('chatbot');
    Route::post('/chatbot', [AdminChatbotController::class, 'update'])->name('chatbot.update');

    Route::get('/about', [AboutController::class, 'edit'])->name('about');
    Route::post('/about', [AboutController::class, 'update'])->name('about.update');
    Route::post('/about/certificate', [AboutController::class, 'uploadCertificate'])->name('about.certificate.upload');
    Route::delete('/about/certificate/{certificate}', [AboutController::class, 'destroyCertificate'])->name('about.certificate.destroy');

    Route::middleware('role:admin,supervisor')->group(function () {
        Route::get('/users', [UserController::class, 'index'])->name('users.index');
        Route::get('/users/{user}/edit', [UserController::class, 'edit'])->name('users.edit');
        Route::put('/users/{user}', [UserController::class, 'update'])->name('users.update');
        Route::post('/users', [UserController::class, 'store'])->name('users.store');
        Route::delete('/users/{user}', [UserController::class, 'destroy'])->name('users.destroy');
    });
    Route::middleware('role:admin')->group(function () {
        Route::get('/activity-logs', [ActivityLogController::class, 'index'])->name('activity-logs');

        Route::get('/confidentiality/policy', [AdminConfidentialityController::class, 'policy'])->name('confidentiality.policy');

        Route::get('/clients/pending', [AdminClientController::class, 'pending'])->name('clients.pending');
        Route::post('/clients/{client}/approve', [AdminClientController::class, 'approve'])->name('clients.approve');
        Route::post('/clients/{client}/reject', [AdminClientController::class, 'reject'])->name('clients.reject');
    });

    Route::get('/billings', [AdminBillingController::class, 'index'])->name('billing.index');
    Route::get('/billings/print-batch', [AdminBillingController::class, 'printBatch'])->name('billing.printBatch');
    Route::get('/billings/applicable-forms', [AdminBillingController::class, 'applicableForms'])->name('billing.applicableForms');
    Route::get('/billings/last-billing', [AdminBillingController::class, 'lastBilling'])->name('billing.lastBilling');
    Route::get('/billings/settings', [AdminBillingController::class, 'settings'])->name('billing.settings');
    Route::post('/billings/settings', [AdminBillingController::class, 'updateSettings'])->name('billing.settings.update');
    Route::get('/billings/payment-settings', [AdminBillingController::class, 'paymentSettings'])->name('billing.paymentSettings');
    Route::post('/billings/payment-settings', [AdminBillingController::class, 'updatePaymentSettings'])->name('billing.paymentSettings.update');
    Route::post('/billings/fee-rates', [AdminBillingController::class, 'storeFeeRate'])->name('billing.feeRates.store');
    Route::delete('/billings/fee-rates/{feeRate}', [AdminBillingController::class, 'destroyFeeRate'])->name('billing.feeRates.destroy');
    Route::get('/billings/create', [AdminBillingController::class, 'create'])->name('billing.create');
    Route::post('/billings', [AdminBillingController::class, 'store'])->name('billing.store');
    Route::get('/billings/{billing}/edit', [AdminBillingController::class, 'edit'])->name('billing.edit');
    Route::put('/billings/{billing}', [AdminBillingController::class, 'update'])->name('billing.update');
    Route::post('/billings/{billing}/pay', [AdminBillingController::class, 'pay'])->name('billing.pay');
    Route::post('/billings/{billing}/finalize', [AdminBillingController::class, 'finalize'])->name('billing.finalize');
    Route::post('/billings/{billing}/send-email', [AdminBillingController::class, 'sendEmail'])->name('billing.sendEmail');
    Route::get('/billings/{billing}/receipt', [AdminBillingController::class, 'receipt'])->name('billing.receipt');
    Route::get('/billings/{billing}/csv', [AdminBillingController::class, 'csv'])->name('billing.csv');
    Route::get('/billings/{client}/export', [AdminBillingController::class, 'clientCsv'])->name('billing.clientCsv');
    Route::get('/billings/export/xlsx', [AdminBillingController::class, 'exportSummaryXlsx'])->name('billing.exportSummaryXlsx');
    Route::get('/billings/export/pdf', [AdminBillingController::class, 'exportSummaryPdf'])->name('billing.exportSummaryPdf');
    Route::get('/billings/years', [AdminBillingController::class, 'availableYears'])->name('billing.years');
    Route::get('/billings/{client}', [AdminBillingController::class, 'show'])->name('billing.show');
    Route::delete('/billings/{billing}', [AdminBillingController::class, 'destroy'])->name('billing.destroy');

    Route::get('/clients', [AdminClientController::class, 'index'])->name('clients.index');
    Route::get('/clients/export/xlsx', [AdminClientController::class, 'exportXlsx'])->name('clients.exportXlsx');
    Route::get('/clients/export/pdf', [AdminClientController::class, 'exportPdf'])->name('clients.exportPdf');
    Route::get('/clients/{client}/requirements/{document}', [AdminClientController::class, 'requirementDocument'])
        ->middleware('signed')
        ->name('clients.requirements.show');

    Route::middleware('role:admin,staff')->group(function () {
        Route::get('/clients/create', [AdminClientController::class, 'create'])->name('clients.create');
        Route::post('/clients', [AdminClientController::class, 'store'])->name('clients.store');
        Route::get('/clients/{client}/edit', [AdminClientController::class, 'edit'])->name('clients.edit');
        Route::put('/clients/{client}', [AdminClientController::class, 'update'])->name('clients.update');
        Route::post('/clients/{client}/companies', [AdminClientController::class, 'storeCompany'])->name('clients.companies.store');
        Route::put('/clients/{client}/companies/{company}', [AdminClientController::class, 'updateCompany'])->name('clients.companies.update');
        Route::delete('/clients/{client}', [AdminClientController::class, 'destroy'])->name('clients.destroy');
        Route::post('/clients/{client}/info-entries', [AdminClientController::class, 'storeInfoEntry'])->name('clients.storeInfoEntry');
        Route::put('/clients/{client}/info-entries/{entry}', [AdminClientController::class, 'updateInfoEntry'])->name('clients.updateInfoEntry');
        Route::delete('/clients/{client}/info-entries/{entry}', [AdminClientController::class, 'destroyInfoEntry'])->name('clients.destroyInfoEntry');
        Route::post('/clients/{client}/impersonate', [ImpersonateController::class, 'start'])->name('clients.impersonate');
    });

    Route::get('/clients/{client}', [AdminClientController::class, 'show'])->name('clients.show');

    Route::get('/collections', [AdminCollectionController::class, 'index'])->name('collections.index');
    Route::post('/collections/{billing}/remind', [AdminCollectionController::class, 'remind'])->name('collections.remind');

    Route::get('/other-services', [AdminOtherServiceController::class, 'billing'])->name('other-services.billing');
    Route::get('/other-services/fill-up', [AdminOtherServiceController::class, 'fillUp'])->name('other-services.fill-up');
    Route::post('/other-services', [AdminOtherServiceController::class, 'store'])->name('other-services.store');
    Route::get('/other-services/collections', [AdminOtherServiceController::class, 'collections'])->name('other-services.collections');
    Route::post('/other-services/{otherService}/pay', [AdminOtherServiceController::class, 'pay'])->name('other-services.pay');
    Route::get('/other-services/{otherService}/receipt', [AdminOtherServiceController::class, 'receipt'])->name('other-services.receipt');
    Route::delete('/other-services/{otherService}', [AdminOtherServiceController::class, 'destroy'])->name('other-services.destroy');
    Route::get('/other-services/settings', [AdminOtherServiceController::class, 'settings'])->name('other-services.settings');
    Route::post('/other-services/service-types', [AdminOtherServiceController::class, 'storeServiceType'])->name('other-services.service-types.store');
    Route::delete('/other-services/service-types/{serviceType}', [AdminOtherServiceController::class, 'destroyServiceType'])->name('other-services.service-types.destroy');
    Route::get('/other-services/clients-json', [AdminOtherServiceController::class, 'clientsJson'])->name('other-services.clientsJson');

    Route::get('/service-tracker', [AdminServiceTrackerController::class, 'index'])->name('service-tracker.index');
    Route::get('/service-tracker/create', [AdminServiceTrackerController::class, 'create'])->name('service-tracker.create');
    Route::post('/service-tracker', [AdminServiceTrackerController::class, 'store'])->name('service-tracker.store');
    Route::post('/service-tracker/assignment/{assignment}/toggle', [AdminServiceTrackerController::class, 'toggleAssignment'])->name('service-tracker.toggle-assignment');
    Route::put('/service-tracker/{instance}/assignment', [AdminServiceTrackerController::class, 'updateAssignment'])->name('service-tracker.update-assignment');
    Route::post('/service-tracker/{instance}/start', [AdminServiceTrackerController::class, 'start'])->name('service-tracker.start');
    Route::post('/service-tracker/{instance}/hold', [AdminServiceTrackerController::class, 'hold'])->name('service-tracker.hold');
    Route::post('/service-tracker/{instance}/resume', [AdminServiceTrackerController::class, 'resume'])->name('service-tracker.resume');
    Route::post('/service-tracker/{instance}/complete', [AdminServiceTrackerController::class, 'complete'])->name('service-tracker.complete');
    Route::get('/service-tracker/{instance}/history', [AdminServiceTrackerController::class, 'show'])->name('service-tracker.show');
    Route::get('/service-tracker/summary', [AdminServiceTrackerController::class, 'summary'])->name('service-tracker.summary');
    Route::get('/service-tracker/concerns', [AdminServiceTrackerController::class, 'concerns'])->name('service-tracker.concerns');
    Route::post('/service-tracker/concerns', [AdminServiceTrackerController::class, 'storeConcern'])->name('service-tracker.concerns.store');
    Route::delete('/service-tracker/concerns/{concern}', [AdminServiceTrackerController::class, 'destroyConcern'])->name('service-tracker.concerns.destroy');
    Route::put('/service-tracker/concerns/{concern}', [AdminServiceTrackerController::class, 'updateConcern'])->name('service-tracker.concerns.update');
    Route::post('/service-tracker/concerns/{concern}/review', [AdminServiceTrackerController::class, 'markReviewed'])->name('service-tracker.concerns.review');
    Route::get('/service-tracker/clients-json', [AdminServiceTrackerController::class, 'clientsJson'])->name('service-tracker.clientsJson');

    Route::get('/weekly-bookkeeping', [AdminWeeklyBookkeepingController::class, 'index'])->name('weekly-bookkeeping.index');
    Route::get('/weekly-bookkeeping/create', [AdminWeeklyBookkeepingController::class, 'create'])->name('weekly-bookkeeping.create');
    Route::get('/weekly-bookkeeping-report', [AdminWeeklyBookkeepingController::class, 'report'])->name('weekly-bookkeeping.report');
    Route::post('/weekly-bookkeeping', [AdminWeeklyBookkeepingController::class, 'store'])->name('weekly-bookkeeping.store');
    Route::get('/weekly-bookkeeping/{bookkeeping}', [AdminWeeklyBookkeepingController::class, 'show'])->name('weekly-bookkeeping.show');
    Route::get('/weekly-bookkeeping/{bookkeeping}/history', [AdminWeeklyBookkeepingController::class, 'history'])->name('weekly-bookkeeping.history');
    Route::post('/weekly-bookkeeping/{bookkeeping}/owner', [AdminWeeklyBookkeepingController::class, 'updateOwner'])->name('weekly-bookkeeping.update-owner');
    Route::post('/weekly-bookkeeping/{bookkeeping}/target/{target}/start', [AdminWeeklyBookkeepingController::class, 'startTarget'])->name('weekly-bookkeeping.start-target');
    Route::post('/weekly-bookkeeping/{bookkeeping}/target/{target}/complete', [AdminWeeklyBookkeepingController::class, 'completeTarget'])->name('weekly-bookkeeping.complete-target');
    Route::post('/weekly-bookkeeping/{bookkeeping}/target/{target}/reassign', [AdminWeeklyBookkeepingController::class, 'reassignTarget'])->name('weekly-bookkeeping.reassign-target');
    Route::post('/weekly-bookkeeping/{bookkeeping}/target/{target}/upload', [AdminWeeklyBookkeepingController::class, 'uploadAttachment'])->name('weekly-bookkeeping.upload-attachment');
    Route::post('/weekly-bookkeeping/{bookkeeping}/target/{target}/replace-attachment', [AdminWeeklyBookkeepingController::class, 'replaceAttachment'])->name('weekly-bookkeeping.replace-attachment');
    Route::get('/weekly-bookkeeping/{bookkeeping}/target/{target}/view', [AdminWeeklyBookkeepingController::class, 'viewAttachment'])->name('weekly-bookkeeping.view-attachment');
    Route::get('/weekly-bookkeeping/{bookkeeping}/target/{target}/download', [AdminWeeklyBookkeepingController::class, 'downloadAttachment'])->name('weekly-bookkeeping.download-attachment');
    Route::patch('/weekly-bookkeeping/{bookkeeping}/target/{target}', [AdminWeeklyBookkeepingController::class, 'updateTarget'])->name('weekly-bookkeeping.update-target');
    Route::delete('/weekly-bookkeeping/{bookkeeping}/target/{target}', [AdminWeeklyBookkeepingController::class, 'destroyTarget'])->name('weekly-bookkeeping.destroy-target');
    Route::delete('/weekly-bookkeeping/{bookkeeping}', [AdminWeeklyBookkeepingController::class, 'destroy'])->name('weekly-bookkeeping.destroy');

    Route::get('/bir-forms', [AdminBirFormsController::class, 'index'])->name('bir-forms.index');

    Route::post('/bir-forms/{client}/toggle', [AdminBirFormsController::class, 'toggleApplicable'])->name('bir-forms.toggle');

    Route::get('/bir-forms/export/xlsx', [AdminBirFormsController::class, 'exportXlsx'])->name('bir-forms.exportXlsx');

    Route::get('/bir-forms/export/pdf', [AdminBirFormsController::class, 'exportPdf'])->name('bir-forms.exportPdf');

    // BIR Form Types (master list management)
    Route::post('/bir-form-types', [BirFormTypeController::class, 'store'])->name('bir-form-types.store');

    Route::get('/distribution', [AdminDistributionController::class, 'index'])->name('distribution.index');
    Route::get('/distribution/{client}', [AdminDistributionController::class, 'show'])->name('distribution.show');
    Route::post('/distribution/{client}/bir-status', [AdminDistributionController::class, 'updateBirStatus'])->name('distribution.bir-status');
    Route::post('/distribution/{client}/deliveries', [AdminDistributionController::class, 'storeDelivery'])->name('distribution.store-delivery');
    Route::delete('/distribution/{client}/deliveries/{delivery}', [AdminDistributionController::class, 'destroyDelivery'])->name('distribution.destroy-delivery');
    Route::post('/distribution/{client}/softcopy', [AdminDistributionController::class, 'storeSoftcopy'])->name('distribution.store-softcopy');
    Route::get('/distribution/{document}/download', [AdminDistributionController::class, 'download'])->name('distribution.download');
    Route::get('/distribution/{document}/view', [AdminDistributionController::class, 'view'])->name('distribution.view');
    Route::get('/distribution/{document}/file', function (Document $document) {
        abort_unless($document->client_id, 404);
        abort_unless(Storage::disk('supabase')->exists($document->path), 404);
        $temporaryUrl = Storage::disk('supabase')->temporaryUrl($document->path, now()->addMinutes(30));

        return redirect($temporaryUrl)->withHeaders([
            'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0',
            'Pragma' => 'no-cache',
        ]);
    })->name('distribution.file');
    Route::delete('/distribution/{client}/softcopy/{document}', [AdminDistributionController::class, 'destroySoftcopy'])->name('distribution.destroy-softcopy');
    Route::post('/distribution/{client}/location', [AdminDistributionController::class, 'updateLocation'])->name('distribution.update-location');
    Route::post('/distribution/geocode', [AdminDistributionController::class, 'geocode'])->name('distribution.geocode');
});

// Kaizen Concerns and the Priority List are also used by supervisors, so they
// live in their own group instead of widening the admin role list for every
// other module. The fine-grained checks (admin-only create/edit/delete, and
// admin + supervisor for checklist and evidence) stay in the controllers.
Route::middleware(['auth', 'role:admin,staff,supervisor', 'admin.confidentiality'])->prefix('admin')->name('admin.')->group(function () {
    // Kaizen Concerns / Admin Concerns
    Route::get('/kaizen-concerns', [KaizenConcernController::class, 'index'])->name('kaizen-concerns.index');
    Route::get('/kaizen-concerns/create', [KaizenConcernController::class, 'create'])->name('kaizen-concerns.create');
    Route::post('/kaizen-concerns', [KaizenConcernController::class, 'store'])->name('kaizen-concerns.store');
    Route::get('/kaizen-concerns/{concern}', [KaizenConcernController::class, 'show'])->name('kaizen-concerns.show');
    Route::get('/kaizen-concerns/{concern}/edit', [KaizenConcernController::class, 'edit'])->name('kaizen-concerns.edit');
    Route::put('/kaizen-concerns/{concern}', [KaizenConcernController::class, 'update'])->name('kaizen-concerns.update');
    Route::delete('/kaizen-concerns/{concern}', [KaizenConcernController::class, 'destroy'])->name('kaizen-concerns.destroy');
    Route::post('/kaizen-concerns/{concern}/checklist/toggle', [KaizenConcernController::class, 'toggleChecklistItem'])->name('kaizen-concerns.checklist.toggle');
    Route::post('/kaizen-concerns/{concern}/checklist/add', [KaizenConcernController::class, 'addChecklistItem'])->name('kaizen-concerns.checklist.add');
    Route::post('/kaizen-concerns/{concern}/checklist/update', [KaizenConcernController::class, 'updateChecklistItem'])->name('kaizen-concerns.checklist.update');
    Route::post('/kaizen-concerns/{concern}/checklist/destroy', [KaizenConcernController::class, 'destroyChecklistItem'])->name('kaizen-concerns.checklist.destroy');
    Route::post('/kaizen-concerns/{concern}/evidence', [KaizenConcernController::class, 'addEvidence'])->name('kaizen-concerns.evidence.add');
    Route::get('/kaizen-concerns/{concern}/evidence/{evidence}', [KaizenConcernController::class, 'viewEvidence'])->name('kaizen-concerns.evidence.view')->middleware('signed');
    Route::get('/kaizen-concerns/{concern}/evidence/{evidence}/download', [KaizenConcernController::class, 'downloadEvidence'])->name('kaizen-concerns.evidence.download')->middleware('signed');
    Route::delete('/kaizen-concerns/{concern}/evidence/{evidence}', [KaizenConcernController::class, 'deleteEvidence'])->name('kaizen-concerns.evidence.delete');

    // Priority List / To-Do List
    Route::get('/priority-items', [PriorityItemController::class, 'index'])->name('priority-items.index');
    Route::get('/priority-items/create', [PriorityItemController::class, 'create'])->name('priority-items.create');
    Route::post('/priority-items', [PriorityItemController::class, 'store'])->name('priority-items.store');
    Route::get('/priority-items/{item}', [PriorityItemController::class, 'show'])->name('priority-items.show');
    Route::get('/priority-items/{item}/edit', [PriorityItemController::class, 'edit'])->name('priority-items.edit');
    Route::put('/priority-items/{item}', [PriorityItemController::class, 'update'])->name('priority-items.update');
    Route::delete('/priority-items/{item}', [PriorityItemController::class, 'destroy'])->name('priority-items.destroy');
    Route::post('/priority-items/{item}/checklist/toggle', [PriorityItemController::class, 'toggleChecklistItem'])->name('priority-items.checklist.toggle');
    Route::post('/priority-items/{item}/checklist/add', [PriorityItemController::class, 'addChecklistItem'])->name('priority-items.checklist.add');
    Route::post('/priority-items/{item}/checklist/update', [PriorityItemController::class, 'updateChecklistItem'])->name('priority-items.checklist.update');
    Route::post('/priority-items/{item}/checklist/destroy', [PriorityItemController::class, 'destroyChecklistItem'])->name('priority-items.checklist.destroy');
    Route::post('/priority-items/{item}/evidence', [PriorityItemController::class, 'addEvidence'])->name('priority-items.evidence.add');
    Route::get('/priority-items/{item}/evidence/{evidence}', [PriorityItemController::class, 'viewEvidence'])->name('priority-items.evidence.view')->middleware('signed');
    Route::get('/priority-items/{item}/evidence/{evidence}/download', [PriorityItemController::class, 'downloadEvidence'])->name('priority-items.evidence.download')->middleware('signed');
    Route::delete('/priority-items/{item}/evidence/{evidence}', [PriorityItemController::class, 'deleteEvidence'])->name('priority-items.evidence.delete');
});

// Daily Accomplishment Journal routes - Employee facing (no admin prefix)
Route::middleware(['auth', 'role:admin,staff,supervisor'])->group(function () {
    Route::get('/daily-journal', [DailyJournalController::class, 'create'])->name('daily-journal.create');
    Route::post('/daily-journal', [DailyJournalController::class, 'store'])->name('daily-journal.store');
    Route::get('/daily-journal/{dailyJournal}', [DailyJournalController::class, 'show'])->name('daily-journal.show');
});

// Admin monitoring routes
Route::middleware(['auth', 'role:admin,staff,supervisor', 'admin.confidentiality'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/daily-journal-monitor', [DailyJournalController::class, 'adminMonitor'])->name('daily-journal.admin-monitor');
    Route::delete('/daily-journal/{dailyJournal}', [DailyJournalController::class, 'destroy'])->name('daily-journal.destroy');
});

// Supervisor monitoring routes
Route::middleware(['auth', 'role:supervisor', 'admin.confidentiality'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/daily-journal-supervisor', [DailyJournalController::class, 'supervisorMonitor'])->name('daily-journal.supervisor-monitor');
});

// Impersonation exit must stay reachable while the admin is logged in as a
// client (role:client) — otherwise the "Exit" button would 403 and lock the
// admin in the impersonation session. The controller itself validates the
// original admin session before switching back.
Route::middleware('auth')->post('/admin/impersonate/stop', [ImpersonateController::class, 'stop'])->name('admin.impersonate.stop');

Route::middleware(['auth', 'role:client'])->prefix('client')->name('client.')->group(function () {
    Route::get('/pending-approval', [ClientApprovalController::class, 'status'])->name('pending-approval');
});

Route::middleware(['auth', 'role:client', 'client.approved'])->prefix('client')->name('client.')->group(function () {
    Route::get('/survey', [ClientSurveyController::class, 'show'])->name('survey.show');
    Route::post('/survey', [ClientSurveyController::class, 'store'])->name('survey.store');
});

Route::middleware(['auth', 'role:client', 'client.approved', 'client.survey', 'client.confidentiality'])->prefix('client')->name('client.')->group(function () {
    Route::get('/dashboard', ClientDashboardController::class)->name('dashboard');

    Route::get('/profile', [ClientProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ClientProfileController::class, 'update'])->name('profile.update');
    Route::post('/geocode', [ClientGeocodeController::class, 'search'])->name('geocode')->middleware('throttle:60,1');

    Route::get('/billing', [ClientBillingController::class, 'index'])->name('billing.index');
    Route::get('/billing/{billing}', [ClientBillingController::class, 'show'])->name('billing.show');

    Route::get('/collections', [ClientCollectionController::class, 'index'])->name('collections.index');

    Route::get('/other-services', [ClientOtherServiceController::class, 'billing'])->name('other-services.billing');
    Route::get('/other-services/collections', [ClientOtherServiceController::class, 'collections'])->name('other-services.collections');
    Route::get('/other-services/{otherService}/receipt', [ClientOtherServiceController::class, 'receipt'])->name('other-services.receipt');

    Route::get('/service-tracker', [ClientServiceTrackerController::class, 'index'])->name('service-tracker.index');
    Route::get('/service-tracker/concerns', [ClientServiceTrackerController::class, 'concerns'])->name('service-tracker.concerns');
    Route::post('/service-tracker/concerns', [ClientServiceTrackerController::class, 'storeConcern'])->name('service-tracker.concerns.store');

    Route::get('/documents', [ClientDistributionController::class, 'index'])->name('documents.index');
    Route::get('/documents/{document}/download', [ClientDistributionController::class, 'download'])->name('documents.download');
});

Route::get('/system/run-scheduler', function () {
    $token = request()->query('token');
    $expected = config('app.scheduler_secret');

    if (! $expected || ! hash_equals($expected, (string) $token)) {
        abort(403, 'Invalid token.');
    }

    Artisan::call('schedule:run');

    return response('ok', 200)->header('Content-Type', 'text/plain');
})->name('system.run-scheduler');
