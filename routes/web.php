<?php

use App\Http\Controllers\Admin\AboutController;
use App\Http\Controllers\Admin\ActivityLogController;
use App\Http\Controllers\Admin\AnnouncementController;
use App\Http\Controllers\Admin\BirFormsController as AdminBirFormsController;
use App\Http\Controllers\Admin\BillingController as AdminBillingController;
use App\Http\Controllers\Admin\ClientController as AdminClientController;
use App\Http\Controllers\Admin\OtherServiceController as AdminOtherServiceController;
use App\Http\Controllers\Admin\ServiceTrackerController as AdminServiceTrackerController;
use App\Http\Controllers\Admin\CollectionController as AdminCollectionController;
use App\Http\Controllers\Admin\DashboardController as AdminDashboardController;
use App\Http\Controllers\Admin\DistributionController as AdminDistributionController;
use App\Http\Controllers\Admin\ProfileController as AdminProfileController;
use App\Http\Controllers\Admin\ChatbotController as AdminChatbotController;
use App\Http\Controllers\Auth\SecurityController;
use App\Http\Controllers\Auth\WebauthnController;
use App\Http\Controllers\ChatbotController;
use App\Http\Controllers\Client\BillingController as ClientBillingController;
use App\Http\Controllers\Client\CollectionController as ClientCollectionController;
use App\Http\Controllers\Client\DashboardController as ClientDashboardController;
use App\Http\Controllers\Client\DistributionController as ClientDistributionController;
use App\Http\Controllers\Client\GeocodeController as ClientGeocodeController;
use App\Http\Controllers\Client\OtherServiceController as ClientOtherServiceController;
use App\Http\Controllers\Client\ServiceTrackerController as ClientServiceTrackerController;
use App\Http\Controllers\Client\ProfileController as ClientProfileController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\PushSubscriptionController;
use Illuminate\Support\Facades\Route;

Route::get('/', HomeController::class)->name('home');

Route::get('/chatbot/config', [ChatbotController::class, 'config'])->name('chatbot.config');

require __DIR__.'/auth.php';

Route::view('/terms', 'terms')->name('terms');

Route::get('/about', function () {
    $about = \App\Models\AboutContent::instance();
    $coreValues = \App\Models\CoreValue::ordered()->get();
    $certificates = \App\Models\CompanyCertificate::ordered()->get();
    $teamMembers = \App\Models\TeamMember::ordered()->get();
    return view('about', compact('about', 'coreValues', 'certificates', 'teamMembers'));
})->name('about.public');

Route::get('/certificates/{certificate}/file', function (\App\Models\CompanyCertificate $certificate) {
    abort_unless(\Illuminate\Support\Facades\Storage::disk('local')->exists($certificate->file_path), 404);
    $mime = $certificate->mime_type ?: mime_content_type(\Illuminate\Support\Facades\Storage::disk('local')->path($certificate->file_path)) ?: 'application/octet-stream';
    return response()->file(\Illuminate\Support\Facades\Storage::disk('local')->path($certificate->file_path), [
        'Content-Type' => $mime,
        'Cache-Control' => 'public, max-age=86400',
    ]);
})->name('certificates.file');

Route::middleware('auth')->group(function () {
    Route::get('/dashboard', DashboardController::class)->name('dashboard');

    Route::get('/security', [SecurityController::class, 'index'])->name('security.index');
    Route::post('/security/pin', [SecurityController::class, 'setPin'])->name('security.pin');

    Route::get('/webauthn/register/options', [WebauthnController::class, 'options'])->name('webauthn.register.options');
    Route::post('/webauthn/register/verify', [WebauthnController::class, 'verify'])->name('webauthn.register.verify');
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

    Route::get('/payment-image/{type}/{index?}', [\App\Http\Controllers\Admin\BillingController::class, 'paymentImage'])->name('payment.image');

    Route::get('/documents/{document}/view', function (\App\Models\Document $document, \Illuminate\Http\Request $request) {
        $user = $request->user();
        abort_unless($document->client_id === $user->id || $user->isAdmin(), 403);
        abort_unless(\Illuminate\Support\Facades\Storage::disk('local')->exists($document->path), 404);

        \App\Models\CorViewLog::create([
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

    Route::get('/documents/{document}/file', function (\App\Models\Document $document, \Illuminate\Http\Request $request) {
        $user = $request->user();
        abort_unless($document->client_id === $user->id || $user->isAdmin(), 403);
        abort_unless(\Illuminate\Support\Facades\Storage::disk('local')->exists($document->path), 404);

        $mime = $document->mime_type ?: mime_content_type(\Illuminate\Support\Facades\Storage::disk('local')->path($document->path)) ?: 'application/octet-stream';

        return response()->file(\Illuminate\Support\Facades\Storage::disk('local')->path($document->path), [
            'Content-Type' => $mime,
            'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0',
            'Pragma' => 'no-cache',
        ]);
    })->name('documents.file');
});

Route::middleware(['auth', 'role:admin'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/confidentiality/acknowledge', function () {
        return view('admin.confidentiality-ack');
    })->name('confidentiality.acknowledge');

    Route::post('/confidentiality/acknowledge', function (\Illuminate\Http\Request $request) {
        $request->validate(['agree' => 'accepted']);
        $user = $request->user();
        $user->update([
            'confidentiality_acknowledged_at' => now(),
            'confidentiality_ack_version' => \App\Http\Middleware\EnsureAdminConfidentialityAcknowledged::CURRENT_VERSION,
        ]);
        \App\Models\ActivityLog::record($user, 'admin.confidentiality_acknowledged', 'Acknowledged the confidentiality policy.');
        return redirect()->route('admin.dashboard');
    })->name('confidentiality.acknowledge.store');
});

Route::middleware(['auth', 'role:admin', 'admin.confidentiality'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/dashboard', AdminDashboardController::class)->name('dashboard');

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

    Route::get('/activity-logs', [ActivityLogController::class, 'index'])->name('activity-logs');

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
    Route::get('/clients/{client}/edit', [AdminClientController::class, 'edit'])->name('clients.edit');
    Route::put('/clients/{client}', [AdminClientController::class, 'update'])->name('clients.update');
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
    Route::get('/service-tracker/summary', [AdminServiceTrackerController::class, 'summary'])->name('service-tracker.summary');
    Route::get('/service-tracker/concerns', [AdminServiceTrackerController::class, 'concerns'])->name('service-tracker.concerns');
    Route::post('/service-tracker/concerns', [AdminServiceTrackerController::class, 'storeConcern'])->name('service-tracker.concerns.store');
    Route::delete('/service-tracker/concerns/{concern}', [AdminServiceTrackerController::class, 'destroyConcern'])->name('service-tracker.concerns.destroy');
    Route::put('/service-tracker/concerns/{concern}', [AdminServiceTrackerController::class, 'updateConcern'])->name('service-tracker.concerns.update');
    Route::post('/service-tracker/concerns/{concern}/review', [AdminServiceTrackerController::class, 'markReviewed'])->name('service-tracker.concerns.review');
    Route::get('/service-tracker/clients-json', [AdminServiceTrackerController::class, 'clientsJson'])->name('service-tracker.clientsJson');

    Route::get('/bir-forms', [AdminBirFormsController::class, 'index'])->name('bir-forms.index');

    Route::post('/bir-forms/{client}/toggle', [AdminBirFormsController::class, 'toggleApplicable'])->name('bir-forms.toggle');

    Route::get('/bir-forms/export/xlsx', [AdminBirFormsController::class, 'exportXlsx'])->name('bir-forms.exportXlsx');

    Route::get('/bir-forms/export/pdf', [AdminBirFormsController::class, 'exportPdf'])->name('bir-forms.exportPdf');

    Route::get('/distribution', [AdminDistributionController::class, 'index'])->name('distribution.index');
    Route::get('/distribution/{client}', [AdminDistributionController::class, 'show'])->name('distribution.show');
    Route::post('/distribution/{client}/bir-status', [AdminDistributionController::class, 'updateBirStatus'])->name('distribution.bir-status');
    Route::post('/distribution/{client}/deliveries', [AdminDistributionController::class, 'storeDelivery'])->name('distribution.store-delivery');
    Route::delete('/distribution/{client}/deliveries/{delivery}', [AdminDistributionController::class, 'destroyDelivery'])->name('distribution.destroy-delivery');
    Route::post('/distribution/{client}/softcopy', [AdminDistributionController::class, 'storeSoftcopy'])->name('distribution.store-softcopy');
    Route::get('/distribution/{document}/download', [AdminDistributionController::class, 'download'])->name('distribution.download');
    Route::get('/distribution/{document}/view', [AdminDistributionController::class, 'view'])->name('distribution.view');
    Route::get('/distribution/{document}/file', function (\App\Models\Document $document) {
        abort_unless($document->client_id, 404);
        abort_unless(\Illuminate\Support\Facades\Storage::disk('local')->exists($document->path), 404);
        $mime = $document->mime_type ?: mime_content_type(\Illuminate\Support\Facades\Storage::disk('local')->path($document->path)) ?: 'application/octet-stream';
        return response()->file(\Illuminate\Support\Facades\Storage::disk('local')->path($document->path), [
            'Content-Type' => $mime,
            'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0',
            'Pragma' => 'no-cache',
        ]);
    })->name('distribution.file');
    Route::delete('/distribution/{client}/softcopy/{document}', [AdminDistributionController::class, 'destroySoftcopy'])->name('distribution.destroy-softcopy');
    Route::post('/distribution/{client}/location', [AdminDistributionController::class, 'updateLocation'])->name('distribution.update-location');
    Route::post('/distribution/geocode', [AdminDistributionController::class, 'geocode'])->name('distribution.geocode');
});

Route::middleware(['auth', 'role:client'])->prefix('client')->name('client.')->group(function () {
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

