<?php

use App\Http\Controllers\Admin\AboutController;
use App\Http\Controllers\Admin\ActivityLogController;
use App\Http\Controllers\Admin\AnnouncementController;
use App\Http\Controllers\Admin\BillingController as AdminBillingController;
use App\Http\Controllers\Admin\ClientController as AdminClientController;
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
use App\Http\Controllers\Client\ProfileController as ClientProfileController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\ProfileController;
use Illuminate\Support\Facades\Route;

Route::get('/', HomeController::class)->name('home');

Route::get('/chatbot/config', [ChatbotController::class, 'config'])->name('chatbot.config');

require __DIR__.'/auth.php';

Route::view('/terms', 'terms')->name('terms');

Route::get('/about', function () {
    $about = \App\Models\AboutContent::instance();
    $coreValues = \App\Models\CoreValue::ordered()->get();
    $certificates = \App\Models\CompanyCertificate::ordered()->get();
    return view('about', compact('about', 'coreValues', 'certificates'));
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
    Route::get('/billings/settings', [AdminBillingController::class, 'settings'])->name('billing.settings');
    Route::post('/billings/settings', [AdminBillingController::class, 'updateSettings'])->name('billing.settings.update');
    Route::post('/billings/fee-rates', [AdminBillingController::class, 'storeFeeRate'])->name('billing.feeRates.store');
    Route::delete('/billings/fee-rates/{feeRate}', [AdminBillingController::class, 'destroyFeeRate'])->name('billing.feeRates.destroy');
    Route::get('/billings/create', [AdminBillingController::class, 'create'])->name('billing.create');
    Route::post('/billings', [AdminBillingController::class, 'store'])->name('billing.store');
    Route::get('/billings/{billing}/edit', [AdminBillingController::class, 'edit'])->name('billing.edit');
    Route::put('/billings/{billing}', [AdminBillingController::class, 'update'])->name('billing.update');
    Route::post('/billings/{billing}/pay', [AdminBillingController::class, 'pay'])->name('billing.pay');
    Route::get('/billings/{billing}/receipt', [AdminBillingController::class, 'receipt'])->name('billing.receipt');
    Route::get('/billings/{billing}/csv', [AdminBillingController::class, 'csv'])->name('billing.csv');
    Route::get('/billings/{client}/export', [AdminBillingController::class, 'clientCsv'])->name('billing.clientCsv');
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
    Route::post('/billing', [ClientBillingController::class, 'submitSales'])->name('billing.submit');
    Route::get('/billing/period-data', [ClientBillingController::class, 'periodData'])->name('billing.period-data');
    Route::get('/billing/{billing}', [ClientBillingController::class, 'show'])->name('billing.show');

    Route::get('/collections', [ClientCollectionController::class, 'index'])->name('collections.index');

    Route::get('/documents', [ClientDistributionController::class, 'index'])->name('documents.index');
    Route::get('/documents/{document}/download', [ClientDistributionController::class, 'download'])->name('documents.download');
});
