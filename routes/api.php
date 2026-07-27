<?php

use App\Http\Controllers\Api\AuditLogController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\BreachController;
use App\Http\Controllers\Api\CertificationController;
use App\Http\Controllers\Api\ContractorController;
use App\Http\Controllers\Api\ContractorEmployeeController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\DepartmentController;
use App\Http\Controllers\Api\EmployeeController;
use App\Http\Controllers\Api\EnvironmentController;
use App\Http\Controllers\Api\EquipmentController;
use App\Http\Controllers\Api\ExportController;
use App\Http\Controllers\Api\FormationController;
use App\Http\Controllers\Api\GembaWalkController;
use App\Http\Controllers\Api\ImportController;
use App\Http\Controllers\Api\IncidentController;
use App\Http\Controllers\Api\MediaController;
use App\Http\Controllers\Api\MedicalVisitController;
use App\Http\Controllers\Api\NearMissController;
use App\Http\Controllers\Api\NotificationController;
use App\Http\Controllers\Api\PermitToWorkController;
use App\Http\Controllers\Api\ReportController;
use App\Http\Controllers\Api\SafetyTrackerController;
use App\Http\Controllers\Api\SettingsController;
use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\Api\SearchController;
use App\Http\Controllers\Api\VisitorController;
use Illuminate\Support\Facades\Route;

// ── Health ────────────────────────────────────────────────────────────────────
Route::get('/health', fn () => response()->json([
    'status'  => 'ok',
    'version' => '2.0',
    'app'     => 'Operix HSSE — TCN',
    'time'    => now()->toIso8601String(),
]));

// ══════════════════════════════════════════════════════════════════════════════
// API v1
// ══════════════════════════════════════════════════════════════════════════════
Route::prefix('v1')->group(function () {

    // ── Organisation info (public — login page) ───────────────────────────────
    Route::get('/organisation', function () {
        $org = \App\Models\Organisation::first();
        return response()->json([
            'name'          => $org?->name          ?? 'Terminal à Conteneurs de Nouakchott',
            'short_name'    => $org?->short_name     ?? 'TCN',
            'logo_url'      => $org?->logo ? asset('storage/' . $org->logo) : null,
            'primary_color' => $org?->primary_color  ?? '#0f2847',
            'country'       => $org?->country         ?? 'MR',
        ]);
    });

    // ── Auth (public) ─────────────────────────────────────────────────────────
    Route::prefix('auth')->group(function () {
        Route::post('/request-otp', [AuthController::class, 'requestOtp']);
        Route::post('/verify-otp',  [AuthController::class, 'verifyOtp']);
        Route::post('/login',       [AuthController::class, 'loginWithMatricule']);
        Route::post('/register',    [AuthController::class, 'register']);
        Route::post('/forgot-pin',  [AuthController::class, 'forgotPin']);
        Route::post('/reset-pin',   [AuthController::class, 'resetPin']);
    });

    // ── Routes protégées ─────────────────────────────────────────────────────
    Route::middleware('auth:sanctum')->group(function () {

        // Auth — tous rôles
        Route::get('/auth/me',      [AuthController::class, 'me']);
        Route::post('/auth/logout', [AuthController::class, 'logout']);

        // ── Médias (upload générique) ─────────────────────────────────────────
        Route::prefix('media')->group(function () {
            Route::post('/',       [MediaController::class, 'store']);
            Route::get('/{id}',    [MediaController::class, 'show']);
            Route::delete('/{id}', [MediaController::class, 'destroy']);
        });

        // ── Notifications (lecture — tous rôles) ──────────────────────────────
        Route::get('/notifications',              [NotificationController::class, 'index']);
        Route::get('/notifications/unread-count', [NotificationController::class, 'unreadCount']);
        Route::put('/notifications/{id}/read',    [NotificationController::class, 'markRead']);
        Route::post('/notifications/read-all',    [NotificationController::class, 'markAllRead']);

        // ── Recherche globale (tous rôles) ───────────────────────────────────
        Route::get('/search', [SearchController::class, 'search']);

        // ── Employés (lecture — tous rôles) ──────────────────────────────────
        Route::get('/employees',      [EmployeeController::class, 'index']);
        Route::get('/employees/{id}', [EmployeeController::class, 'show']);

        // ── Dashboard ─────────────────────────────────────────────────────────
        Route::prefix('dashboard')->group(function () {
            Route::get('/',                   [DashboardController::class, 'index']);
            Route::get('/safety-timeline',    [DashboardController::class, 'safetyTimeline']);
            Route::get('/employee-breakdown', [DashboardController::class, 'employeeBreakdown']);
            Route::get('/incident-stats',     [DashboardController::class, 'incidentStats']);
            Route::get('/recent-activity',    [DashboardController::class, 'recentActivity']);
            Route::get('/top-zones',          [DashboardController::class, 'topZones']);
        });

        // ── Admin seulement ───────────────────────────────────────────────────
        Route::middleware('role:admin')->group(function () {

            // ── Paramètres / Branding ─────────────────────────────────────────
            Route::prefix('settings')->group(function () {
                Route::get('/',        [SettingsController::class, 'index']);
                Route::put('/',        [SettingsController::class, 'update']);
                Route::post('/logo',   [SettingsController::class, 'uploadLogo']);
                Route::delete('/logo', [SettingsController::class, 'deleteLogo']);
            });

            // ── Utilisateurs ──────────────────────────────────────────────────
            Route::prefix('users')->group(function () {
                Route::get('/',        [UserController::class, 'index']);
                Route::post('/',       [UserController::class, 'store']);
                Route::get('/{id}',    [UserController::class, 'show']);
                Route::put('/{id}',    [UserController::class, 'update']);
                Route::delete('/{id}', [UserController::class, 'destroy']);
            });

            // ── Départements ──────────────────────────────────────────────────
            Route::prefix('departments')->group(function () {
                Route::get('/',        [DepartmentController::class, 'index']);
                Route::post('/',       [DepartmentController::class, 'store']);
                Route::get('/{id}',    [DepartmentController::class, 'show']);
                Route::put('/{id}',    [DepartmentController::class, 'update']);
                Route::delete('/{id}', [DepartmentController::class, 'destroy']);
            });

            // ── Notifications (envoi + suppression) ───────────────────────────
            Route::post('/notifications',        [NotificationController::class, 'store']);
            Route::delete('/notifications/{id}', [NotificationController::class, 'destroy']);

            // ── Safety Tracker ────────────────────────────────────────────────
            Route::prefix('safety-tracker')->group(function () {
                Route::get('/',        [SafetyTrackerController::class, 'index']);
                Route::get('/history', [SafetyTrackerController::class, 'history']);
            });

            // ── Rapports PDF ──────────────────────────────────────────────────
            Route::prefix('reports')->group(function () {
                Route::get('/dashboard',              [ReportController::class, 'dashboardPdf']);
                Route::get('/incidents',              [ReportController::class, 'incidentsPdf']);
                Route::get('/incidents/{id}',         [ReportController::class, 'incidentDetailPdf']);
                Route::get('/near-miss',              [ReportController::class, 'nearMissPdf']);
                Route::get('/breaches',               [ReportController::class, 'breachesPdf']);
                Route::get('/environment',            [ReportController::class, 'environmentPdf']);
                Route::get('/employees',              [ReportController::class, 'employeesPdf']);
                Route::get('/employees/{id}/profile', [ReportController::class, 'employeeProfilePdf']);
                Route::get('/permits',                [ReportController::class, 'permitsPdf']);
            });

            // ── Exports Excel/CSV ─────────────────────────────────────────────
            Route::prefix('exports')->group(function () {
                Route::get('/employees',           [ExportController::class, 'employees']);
                Route::get('/incidents',           [ExportController::class, 'incidents']);
                Route::get('/near-miss',           [ExportController::class, 'nearMiss']);
                Route::get('/breaches',            [ExportController::class, 'breaches']);
                Route::get('/environment',         [ExportController::class, 'environment']);
                Route::get('/certifications',      [ExportController::class, 'certifications']);
                Route::get('/medical-visits',      [ExportController::class, 'medicalVisits']);
                Route::get('/permits',             [ExportController::class, 'permits']);
                Route::get('/visitors',            [ExportController::class, 'visitors']);
                Route::get('/contractors',         [ExportController::class, 'contractors']);
                Route::get('/templates/employees', [ExportController::class, 'employeeImportTemplate']);
            });

            // ── Imports ───────────────────────────────────────────────────────
            Route::prefix('imports')->group(function () {
                Route::post('/employees/preview', [ImportController::class, 'previewEmployees']);
                Route::post('/employees',         [ImportController::class, 'importEmployees']);
                Route::post('/incidents/preview', [ImportController::class, 'previewIncidents']);
                Route::post('/incidents',         [ImportController::class, 'importIncidents']);
                Route::post('/near-miss',         [ImportController::class, 'importNearMiss']);
            });

            // ── Employés (écriture) ───────────────────────────────────────────
            Route::post('/employees',             [EmployeeController::class, 'store']);
            Route::put('/employees/{id}',         [EmployeeController::class, 'update']);
            Route::delete('/employees/{id}',      [EmployeeController::class, 'destroy']);
            Route::get('/employees/{id}/history', [EmployeeController::class, 'history']);

            // ── Formations ────────────────────────────────────────────────────
            Route::prefix('employees/{employeeId}/formations')->group(function () {
                Route::get('/',        [FormationController::class, 'index']);
                Route::post('/',       [FormationController::class, 'store']);
                Route::put('/{id}',    [FormationController::class, 'update']);
                Route::delete('/{id}', [FormationController::class, 'destroy']);
            });

            // ── Certifications ────────────────────────────────────────────────
            Route::prefix('employees/{employeeId}/certifications')->group(function () {
                Route::get('/',        [CertificationController::class, 'index']);
                Route::post('/',       [CertificationController::class, 'store']);
                Route::put('/{id}',    [CertificationController::class, 'update']);
                Route::delete('/{id}', [CertificationController::class, 'destroy']);
            });

            // ── Visites médicales ─────────────────────────────────────────────
            Route::prefix('employees/{employeeId}/medical-visits')->group(function () {
                Route::get('/',        [MedicalVisitController::class, 'index']);
                Route::post('/',       [MedicalVisitController::class, 'store']);
                Route::put('/{id}',    [MedicalVisitController::class, 'update']);
                Route::delete('/{id}', [MedicalVisitController::class, 'destroy']);
            });

            // ── Incidents ─────────────────────────────────────────────────────
            Route::prefix('incidents')->group(function () {
                Route::get('/',            [IncidentController::class, 'index']);
                Route::post('/',           [IncidentController::class, 'store']);
                Route::get('/stats',       [IncidentController::class, 'stats']);
                Route::get('/{id}',        [IncidentController::class, 'show']);
                Route::put('/{id}',        [IncidentController::class, 'update']);
                Route::delete('/{id}',     [IncidentController::class, 'destroy']);
                Route::post('/{id}/close', [IncidentController::class, 'close']);
            });

            // ── Near Miss ─────────────────────────────────────────────────────
            Route::prefix('near-miss')->group(function () {
                Route::get('/',            [NearMissController::class, 'index']);
                Route::post('/',           [NearMissController::class, 'store']);
                Route::get('/{id}',        [NearMissController::class, 'show']);
                Route::put('/{id}',        [NearMissController::class, 'update']);
                Route::delete('/{id}',     [NearMissController::class, 'destroy']);
                Route::post('/{id}/close', [NearMissController::class, 'close']);
            });

            // ── Environnement ─────────────────────────────────────────────────
            Route::prefix('environment')->group(function () {
                Route::get('/',        [EnvironmentController::class, 'index']);
                Route::post('/',       [EnvironmentController::class, 'store']);
                Route::get('/stats',   [EnvironmentController::class, 'stats']);
                Route::get('/{id}',    [EnvironmentController::class, 'show']);
                Route::put('/{id}',    [EnvironmentController::class, 'update']);
                Route::delete('/{id}', [EnvironmentController::class, 'destroy']);
                Route::post('/{id}/close', [EnvironmentController::class, 'close']);
            });

            // ── Gemba Walks ───────────────────────────────────────────────────
            Route::prefix('gemba-walks')->group(function () {
                Route::get('/',              [GembaWalkController::class, 'index']);
                Route::post('/',             [GembaWalkController::class, 'store']);
                Route::get('/stats',         [GembaWalkController::class, 'stats']);
                Route::get('/{id}',          [GembaWalkController::class, 'show']);
                Route::put('/{id}',          [GembaWalkController::class, 'update']);
                Route::delete('/{id}',       [GembaWalkController::class, 'destroy']);
                Route::post('/{id}/resolve', [GembaWalkController::class, 'resolve']);
            });

            // ── Infractions / Breaches ────────────────────────────────────────
            Route::prefix('breaches')->group(function () {
                Route::get('/',            [BreachController::class, 'index']);
                Route::post('/',           [BreachController::class, 'store']);
                Route::get('/{id}',        [BreachController::class, 'show']);
                Route::put('/{id}',        [BreachController::class, 'update']);
                Route::delete('/{id}',     [BreachController::class, 'destroy']);
                Route::post('/{id}/close', [BreachController::class, 'close']);
            });

            // ── Visiteurs ─────────────────────────────────────────────────────
            Route::prefix('visitors')->group(function () {
                Route::get('/',               [VisitorController::class, 'index']);
                Route::post('/',              [VisitorController::class, 'store']);
                Route::get('/on-site',        [VisitorController::class, 'onSite']);
                Route::get('/{id}',           [VisitorController::class, 'show']);
                Route::delete('/{id}',        [VisitorController::class, 'destroy']);
                Route::post('/{id}/checkout', [VisitorController::class, 'checkout']);
            });

            // ── Prestataires ──────────────────────────────────────────────────
            Route::prefix('contractors')->group(function () {
                Route::get('/',        [ContractorController::class, 'index']);
                Route::post('/',       [ContractorController::class, 'store']);
                Route::get('/{id}',    [ContractorController::class, 'show']);
                Route::put('/{id}',    [ContractorController::class, 'update']);
                Route::delete('/{id}', [ContractorController::class, 'destroy']);
                // Personnel prestataire
                Route::get('/{id}/employees',         [ContractorEmployeeController::class, 'index']);
                Route::post('/{id}/employees',        [ContractorEmployeeController::class, 'store']);
                Route::put('/{id}/employees/{empId}', [ContractorEmployeeController::class, 'update']);
                Route::delete('/{id}/employees/{empId}', [ContractorEmployeeController::class, 'destroy']);
            });

            // ── Équipements ───────────────────────────────────────────────────
            Route::prefix('equipment')->group(function () {
                Route::get('/',              [EquipmentController::class, 'index']);
                Route::post('/',             [EquipmentController::class, 'store']);
                Route::get('/{id}',          [EquipmentController::class, 'show']);
                Route::put('/{id}',          [EquipmentController::class, 'update']);
                Route::delete('/{id}',       [EquipmentController::class, 'destroy']);
                Route::post('/{id}/inspect', [EquipmentController::class, 'inspect']);
            });

            // ── Permis de travail ─────────────────────────────────────────────
            Route::prefix('permits')->group(function () {
                Route::get('/',              [PermitToWorkController::class, 'index']);
                Route::post('/',             [PermitToWorkController::class, 'store']);
                Route::get('/stats',         [PermitToWorkController::class, 'stats']);
                Route::get('/{id}',          [PermitToWorkController::class, 'show']);
                Route::put('/{id}',          [PermitToWorkController::class, 'update']);
                Route::delete('/{id}',       [PermitToWorkController::class, 'destroy']);
                Route::post('/{id}/approve', [PermitToWorkController::class, 'approve']);
                Route::post('/{id}/close',   [PermitToWorkController::class, 'close']);
            });

            // ── Audit Log ─────────────────────────────────────────────────────
            Route::prefix('audit')->group(function () {
                Route::get('/',     [AuditLogController::class, 'index']);
                Route::get('/{id}', [AuditLogController::class, 'show']);
            });
        });
    });
});
