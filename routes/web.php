<?php

use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\LogoutController;
use App\Http\Controllers\Auth\OrganizationLookupController;
use App\Http\Controllers\Auth\OtpController;
use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\ApplicationController;
use App\Http\Controllers\Admin\AdminDashboardController;
use App\Http\Controllers\Admin\ApplicationManagementController;
use App\Http\Controllers\Admin\ContactCenterController as AdminContactCenterController;
use App\Http\Controllers\Admin\EntityManagementController;
use App\Http\Controllers\Admin\GroupManagementController;
use App\Http\Controllers\Admin\IntegrationDiagnosticsController;
use App\Http\Controllers\Admin\PermitRegistryController;
use App\Http\Controllers\Admin\ProducerDirectoryController;
use App\Http\Controllers\Admin\ScoutingRequestManagementController;
use App\Http\Controllers\Admin\UserManagementController;
use App\Http\Controllers\Authority\ApplicationInboxController;
use App\Http\Controllers\ContactCenterController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\NotificationRedirectController;
use App\Http\Controllers\PermitVerificationController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\RegistrationCompletionController;
use App\Http\Controllers\ScoutingRequestController;
use Illuminate\Support\Facades\Route;
use Mcamara\LaravelLocalization\Facades\LaravelLocalization;

Route::group([
    'prefix' => LaravelLocalization::setLocale(),
    'middleware' => ['localize', 'localeSessionRedirect', 'localizationRedirect'],
], function (): void {
    Route::get('/', function () {
        return auth()->check()
            ? redirect()->route('dashboard')
            : redirect()->route('login');
    })->name('home');

    Route::middleware('guest')->group(function (): void {
        Route::get('/login', [LoginController::class, 'create'])->name('login');
        Route::post('/login', [LoginController::class, 'store'])->name('login.store');

        Route::get('/verify-otp', [OtpController::class, 'create'])->name('otp.create');
        Route::post('/verify-otp', [OtpController::class, 'store'])->name('otp.store');
        Route::post('/verify-otp/resend', [OtpController::class, 'resend'])->name('otp.resend');

        Route::get('/register', [RegisterController::class, 'index'])->name('register');
        Route::post('/register', [RegisterController::class, 'store'])->name('register.store');
        Route::get('/register/individual', fn () => redirect()->route('register'))->name('register.individual.create');
        Route::get('/register/organization', fn () => redirect()->route('register'))->name('register.organization.create');
        Route::post('/register/organization/lookup', OrganizationLookupController::class)->name('register.organization.lookup');
        Route::post('/register/organization', [RegisterController::class, 'store'])->name('register.organization.store');
        Route::post('/register/individual', [RegisterController::class, 'store'])->name('register.individual.store');

        Route::get('/registration/link/{entity}/complete', [RegistrationCompletionController::class, 'editViaSignedLink'])
            ->middleware('signed')
            ->name('registration.completion.link.edit');
        Route::post('/registration/link/{entity}/complete', [RegistrationCompletionController::class, 'updateViaSignedLink'])
            ->middleware('signed')
            ->name('registration.completion.link.update');
    });

    Route::get('/permit-verification', [PermitVerificationController::class, 'index'])->name('permits.verify');
    Route::get('/permit-verification/{permit}', [PermitVerificationController::class, 'showSigned'])
        ->middleware('signed')
        ->name('permits.verify.signed');

    Route::middleware('auth')->group(function (): void {
        Route::get('/dashboard', DashboardController::class)->name('dashboard');
        Route::get('/registration/complete', [RegistrationCompletionController::class, 'edit'])->name('registration.completion.edit');
        Route::post('/registration/complete', [RegistrationCompletionController::class, 'update'])->name('registration.completion.update');
        Route::get('/profile', ProfileController::class)->name('profile.show');
        Route::post('/logout', [LogoutController::class, 'store'])->name('logout');
        Route::get('/notifications/{notification}', NotificationRedirectController::class)->name('notifications.redirect');
        Route::get('/contact-center', [ContactCenterController::class, 'index'])->name('contact-center.index');
        Route::get('/contact-center/messages/{message}/download', [ContactCenterController::class, 'download'])->name('contact-center.messages.download');

        Route::get('/applications', [ApplicationController::class, 'index'])->name('applications.index');
        Route::get('/applications/create', [ApplicationController::class, 'create'])->name('applications.create');
        Route::post('/applications', [ApplicationController::class, 'store'])->name('applications.store');
        Route::get('/applications/{application}', [ApplicationController::class, 'show'])->name('applications.show');
        Route::get('/applications/{application}/edit', [ApplicationController::class, 'edit'])->name('applications.edit');
        Route::post('/applications/{application}/update', [ApplicationController::class, 'update'])->name('applications.update');
        Route::post('/applications/{application}/submit', [ApplicationController::class, 'submit'])->name('applications.submit');
        Route::post('/applications/{application}/documents', [ApplicationController::class, 'storeDocument'])->name('applications.documents.store');
        Route::get('/applications/{application}/documents/{document}/download', [ApplicationController::class, 'downloadDocument'])->name('applications.documents.download');
        Route::post('/applications/{application}/correspondence', [ApplicationController::class, 'storeCorrespondence'])->name('applications.correspondence.store');
        Route::get('/applications/{application}/correspondence/{correspondence}/download', [ApplicationController::class, 'downloadCorrespondenceAttachment'])->name('applications.correspondence.download');
        Route::get('/applications/{application}/final-letter/download', [ApplicationController::class, 'downloadFinalLetter'])->name('applications.final-letter.download');
        Route::get('/applications/{application}/final-letter/print', [ApplicationController::class, 'printFinalLetter'])->name('applications.final-letter.print');

        Route::get('/scouting-requests', [ScoutingRequestController::class, 'index'])->name('scouting-requests.index');
        Route::get('/scouting-requests/create', [ScoutingRequestController::class, 'create'])->name('scouting-requests.create');
        Route::post('/scouting-requests', [ScoutingRequestController::class, 'store'])->name('scouting-requests.store');
        Route::get('/scouting-requests/{scoutingRequest}', [ScoutingRequestController::class, 'show'])->name('scouting-requests.show');
        Route::get('/scouting-requests/{scoutingRequest}/edit', [ScoutingRequestController::class, 'edit'])->name('scouting-requests.edit');
        Route::post('/scouting-requests/{scoutingRequest}/update', [ScoutingRequestController::class, 'update'])->name('scouting-requests.update');
        Route::post('/scouting-requests/{scoutingRequest}/submit', [ScoutingRequestController::class, 'submit'])->name('scouting-requests.submit');
        Route::get('/scouting-requests/{scoutingRequest}/story-file/download', [ScoutingRequestController::class, 'downloadStory'])->name('scouting-requests.story-file.download');
        Route::post('/scouting-requests/{scoutingRequest}/correspondence', [ScoutingRequestController::class, 'storeCorrespondence'])->name('scouting-requests.correspondence.store');
        Route::get('/scouting-requests/{scoutingRequest}/correspondence/{correspondence}/download', [ScoutingRequestController::class, 'downloadCorrespondenceAttachment'])->name('scouting-requests.correspondence.download');

        Route::prefix('/authority')
            ->name('authority.')
            ->group(function (): void {
                Route::get('/applications', [ApplicationInboxController::class, 'index'])
                    ->middleware('permission:applications.view.entity')
                    ->name('applications.index');
                Route::get('/applications/export', [ApplicationInboxController::class, 'export'])
                    ->middleware('permission:applications.view.entity')
                    ->name('applications.export');
                Route::get('/applications/{application}', [ApplicationInboxController::class, 'show'])
                    ->middleware('permission:applications.view.entity')
                    ->name('applications.show');
                Route::post('/applications/{application}/approval', [ApplicationInboxController::class, 'updateApproval'])
                    ->middleware('permission:applications.review')
                    ->name('applications.approval.update');
                Route::post('/applications/{application}/correspondence', [ApplicationInboxController::class, 'storeCorrespondence'])
                    ->middleware('permission:applications.review')
                    ->name('applications.correspondence.store');
                Route::get('/applications/{application}/documents/{document}/download', [ApplicationInboxController::class, 'downloadDocument'])
                    ->middleware('permission:documents.view.entity')
                    ->name('applications.documents.download');
                Route::get('/applications/{application}/correspondence/{correspondence}/download', [ApplicationInboxController::class, 'downloadCorrespondenceAttachment'])
                    ->middleware('permission:applications.view.entity')
                    ->name('applications.correspondence.download');
            });

        Route::prefix('/admin')
            ->name('admin.')
            ->middleware('permission:access.admin-panel')
            ->group(function (): void {
                Route::get('/', AdminDashboardController::class)->name('dashboard');
                Route::get('/reports/export', [AdminDashboardController::class, 'export'])
                    ->middleware('permission:access.admin-panel')
                    ->name('reports.export');

                Route::get('/producers', ProducerDirectoryController::class)
                    ->middleware('permission:applications.view.all')
                    ->name('producers.index');

                Route::get('/applications', [ApplicationManagementController::class, 'index'])
                    ->middleware('permission:applications.view.all')
                    ->name('applications.index');
                Route::get('/applications/export', [ApplicationManagementController::class, 'export'])
                    ->middleware('permission:applications.view.all')
                    ->name('applications.export');
                Route::get('/applications/{application}', [ApplicationManagementController::class, 'show'])
                    ->middleware('permission:applications.view.all')
                    ->name('applications.show');
                Route::post('/applications/{application}/review', [ApplicationManagementController::class, 'review'])
                    ->middleware('permission:applications.review')
                    ->name('applications.review');
                Route::post('/applications/{application}/finalize', [ApplicationManagementController::class, 'finalize'])
                    ->middleware('permission:applications.approve')
                    ->name('applications.finalize');
                Route::post('/applications/{application}/assign', [ApplicationManagementController::class, 'assign'])
                    ->middleware('permission:applications.assign')
                    ->name('applications.assign');
                Route::post('/applications/{application}/approvals/{approval}/update', [ApplicationManagementController::class, 'updateApproval'])
                    ->middleware('permission:applications.review')
                    ->name('applications.approvals.update');
                Route::post('/applications/{application}/documents/{document}/review', [ApplicationManagementController::class, 'reviewDocument'])
                    ->middleware('permission:applications.review')
                    ->name('applications.documents.review');
                Route::get('/applications/{application}/documents/{document}/download', [ApplicationManagementController::class, 'downloadDocument'])
                    ->middleware('permission:applications.view.all')
                    ->name('applications.documents.download');
                Route::post('/applications/{application}/correspondence', [ApplicationManagementController::class, 'storeCorrespondence'])
                    ->middleware('permission:applications.review')
                    ->name('applications.correspondence.store');
                Route::get('/applications/{application}/correspondence/{correspondence}/download', [ApplicationManagementController::class, 'downloadCorrespondenceAttachment'])
                    ->middleware('permission:applications.view.all')
                    ->name('applications.correspondence.download');
                Route::get('/applications/{application}/final-letter/download', [ApplicationManagementController::class, 'downloadFinalLetter'])
                    ->middleware('permission:applications.view.all')
                    ->name('applications.final-letter.download');
                Route::get('/applications/{application}/final-letter/print', [ApplicationManagementController::class, 'printFinalLetter'])
                    ->middleware('permission:applications.view.all')
                    ->name('applications.final-letter.print');

                Route::get('/scouting-requests', [ScoutingRequestManagementController::class, 'index'])
                    ->middleware('permission:applications.view.all')
                    ->name('scouting-requests.index');
                Route::get('/scouting-requests/export', [ScoutingRequestManagementController::class, 'export'])
                    ->middleware('permission:applications.view.all')
                    ->name('scouting-requests.export');
                Route::get('/scouting-requests/{scoutingRequest}', [ScoutingRequestManagementController::class, 'show'])
                    ->middleware('permission:applications.view.all')
                    ->name('scouting-requests.show');
                Route::post('/scouting-requests/{scoutingRequest}/review', [ScoutingRequestManagementController::class, 'review'])
                    ->middleware('permission:applications.review')
                    ->name('scouting-requests.review');
                Route::post('/scouting-requests/{scoutingRequest}/correspondence', [ScoutingRequestManagementController::class, 'storeCorrespondence'])
                    ->middleware('permission:applications.review')
                    ->name('scouting-requests.correspondence.store');
                Route::get('/scouting-requests/{scoutingRequest}/correspondence/{correspondence}/download', [ScoutingRequestManagementController::class, 'downloadCorrespondenceAttachment'])
                    ->middleware('permission:applications.view.all')
                    ->name('scouting-requests.correspondence.download');
                Route::get('/scouting-requests/{scoutingRequest}/story-file/download', [ScoutingRequestManagementController::class, 'downloadStory'])
                    ->middleware('permission:applications.view.all')
                    ->name('scouting-requests.story-file.download');

                Route::get('/contact-center', [AdminContactCenterController::class, 'index'])
                    ->middleware('permission:applications.view.all')
                    ->name('contact-center.index');
                Route::post('/contact-center/messages', [AdminContactCenterController::class, 'store'])
                    ->middleware('permission:applications.review')
                    ->name('contact-center.messages.store');
                Route::get('/contact-center/messages/{message}/download', [AdminContactCenterController::class, 'download'])
                    ->middleware('permission:applications.view.all')
                    ->name('contact-center.messages.download');

                Route::get('/permits', [PermitRegistryController::class, 'index'])
                    ->middleware('permission:permits.view.all')
                    ->name('permits.index');
                Route::get('/permits/export', [PermitRegistryController::class, 'export'])
                    ->middleware('permission:permits.view.all')
                    ->name('permits.export');
                Route::get('/permits/{permit}', [PermitRegistryController::class, 'show'])
                    ->middleware('permission:permits.view.all')
                    ->name('permits.show');

                Route::get('/groups', [GroupManagementController::class, 'index'])
                    ->middleware('permission:groups.view')
                    ->name('groups.index');

                Route::get('/integrations', [IntegrationDiagnosticsController::class, 'index'])
                    ->middleware('permission:settings.manage')
                    ->name('integrations.index');
                Route::post('/integrations/sms-test', [IntegrationDiagnosticsController::class, 'sendSmsTest'])
                    ->middleware('permission:settings.manage')
                    ->name('integrations.sms-test');
                Route::post('/integrations/company-registry-test', [IntegrationDiagnosticsController::class, 'lookupCompanyRegistry'])
                    ->middleware('permission:settings.manage')
                    ->name('integrations.company-registry-test');

                Route::get('/entities', [EntityManagementController::class, 'index'])
                    ->middleware('permission:entities.view')
                    ->name('entities.index');
                Route::get('/entities/create', [EntityManagementController::class, 'create'])
                    ->middleware('permission:entities.manage')
                    ->name('entities.create');
                Route::post('/entities', [EntityManagementController::class, 'store'])
                    ->middleware('permission:entities.manage')
                    ->name('entities.store');
                Route::get('/entities/{entity}', [EntityManagementController::class, 'show'])
                    ->middleware('permission:entities.view')
                    ->name('entities.show');
                Route::post('/entities/{entity}/update', [EntityManagementController::class, 'update'])
                    ->middleware('permission:entities.manage')
                    ->name('entities.update');
                Route::post('/entities/{entity}/status', [EntityManagementController::class, 'updateStatus'])
                    ->middleware('permission:entities.manage')
                    ->name('entities.status');
                Route::post('/entities/{entity}/delete', [EntityManagementController::class, 'destroy'])
                    ->middleware('permission:entities.manage')
                    ->name('entities.delete');
                Route::post('/entities/{entity}/restore', [EntityManagementController::class, 'restore'])
                    ->middleware('permission:entities.manage')
                    ->name('entities.restore');
                Route::get('/entities/{entity}/registration-document', [EntityManagementController::class, 'downloadRegistrationDocument'])
                    ->middleware('permission:entities.view')
                    ->name('entities.registration-document');
                Route::post('/entities/{entity}/review', [EntityManagementController::class, 'review'])
                    ->middleware('permission:entities.manage')
                    ->name('entities.review');
                Route::post('/entities/{entity}/members', [EntityManagementController::class, 'storeMember'])
                    ->middleware('permission:entities.manage')
                    ->name('entities.members.store');

                Route::get('/users', [UserManagementController::class, 'index'])
                    ->middleware('permission:users.view')
                    ->name('users.index');
                Route::get('/users/create', [UserManagementController::class, 'create'])
                    ->middleware('permission:users.manage')
                    ->name('users.create');
                Route::post('/users', [UserManagementController::class, 'store'])
                    ->middleware('permission:users.manage')
                    ->name('users.store');
                Route::get('/users/{user}', [UserManagementController::class, 'show'])
                    ->middleware('permission:users.view')
                    ->name('users.show');
                Route::post('/users/{user}/update', [UserManagementController::class, 'update'])
                    ->middleware('permission:users.manage')
                    ->name('users.update');
                Route::post('/users/{user}/status', [UserManagementController::class, 'updateStatus'])
                    ->middleware('permission:users.manage')
                    ->name('users.status');
                Route::post('/users/{user}/delete', [UserManagementController::class, 'destroy'])
                    ->middleware('permission:users.manage')
                    ->name('users.delete');
                Route::post('/users/{user}/restore', [UserManagementController::class, 'restore'])
                    ->middleware('permission:users.manage')
                    ->name('users.restore');
                Route::post('/users/{user}/memberships', [UserManagementController::class, 'storeMembership'])
                    ->middleware('permission:users.manage')
                    ->name('users.memberships.store');
            });
    });
});
