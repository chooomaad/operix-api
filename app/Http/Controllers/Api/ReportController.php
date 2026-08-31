<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Breach;
use App\Models\Certification;
use App\Models\Contractor;
use App\Models\Employee;
use App\Models\EnvironmentReport;
use App\Models\Formation;
use App\Models\MedicalVisit;
use App\Models\PermitToWork;
use App\Models\SafetyIncident;
use App\Models\SafetyNearMiss;
use App\Models\Visitor;
use App\Models\Tenant;
use App\Traits\HandlesApiResources;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class ReportController extends Controller
{
    use HandlesApiResources;

    private function orgBranding(Request $request): array
    {
        $tenant = $request->user()?->tenant ?? Tenant::where('slug', 'tcn')->first();

        $logoB64 = null;
        $logoPaths = array_filter([
            $tenant?->logo ? storage_path('app/public/' . $tenant->logo) : null,
            storage_path('app/public/logos/logo-tcn.png'),
            storage_path('app/public/logos/logo-operix.png'),
            public_path('storage/logos/logo-tcn.png'),
        ]);
        foreach ($logoPaths as $path) {
            if (file_exists($path) && extension_loaded('gd')) {
                // Redimensionner à 120x120 max pour alléger DomPDF
                $src = @imagecreatefrompng($path) ?: @imagecreatefromjpeg($path);
                if ($src) {
                    $w = min(imagesx($src), 120);
                    $h = min(imagesy($src), 120);
                    $dst = imagecreatetruecolor($w, $h);
                    imagealphablending($dst, false);
                    imagesavealpha($dst, true);
                    imagefill($dst, 0, 0, imagecolorallocatealpha($dst, 0, 0, 0, 127));
                    imagecopyresampled($dst, $src, 0, 0, 0, 0, $w, $h, imagesx($src), imagesy($src));
                    ob_start();
                    imagepng($dst, null, 6);
                    $logoB64 = 'data:image/png;base64,' . base64_encode(ob_get_clean());
                    imagedestroy($src);
                    imagedestroy($dst);
                } else {
                    $logoB64 = 'data:image/png;base64,' . base64_encode(file_get_contents($path));
                }
                break;
            }
        }

        $rawColor = trim($tenant?->primary_color ?? '');
        // Reject white, near-white, or empty — always use a dark readable color for PDFs
        $invalidColors = ['#ffffff', '#fff', 'ffffff', 'fff', '#fefefe', '#f0f0f0', ''];
        $brandColor = (!in_array(strtolower($rawColor), $invalidColors)) ? $rawColor : '#0f2847';

        return [
            'name'   => $tenant?->name       ?? 'Terminal à Conteneurs de Nouakchott',
            'short'  => $tenant?->short_name ?? 'TCN',
            'color'  => $brandColor,
            'logo'   => $logoB64,
        ];
    }

    // ── Dashboard PDF ─────────────────────────────────────────────────────────
    public function dashboardPdf(Request $request): Response
    {
        ini_set('memory_limit', '512M');
        $request->validate(['year' => 'nullable|integer|min:2000|max:2099']);

        $year  = $request->integer('year', (int) now()->year);
        $month = now()->month;
        $org   = $this->orgBranding($request);

        $employees   = $this->employeeKpis($month, $year);
        $safety      = $this->safetyKpis($month, $year);
        $environment = $this->environmentKpis($month, $year);
        $tracker     = $this->safetyTrackerData();
        $contractors = $this->contractorKpis();
        $visitors    = $this->visitorKpis($month, $year);

        $incidentsByMonth = SafetyIncident::query()
            ->whereYear('date', $year)
            ->selectRaw("TO_CHAR(date,'MM') as month, COUNT(*) as total")
            ->groupBy('month')->orderBy('month')
            ->pluck('total', 'month')->toArray();

        $recentIncidents = SafetyIncident::query()
            ->with('reporter:id,name')
            ->orderByDesc('date')
            ->limit(5)
            ->get();

        $pdf = Pdf::loadView('pdf.dashboard_report', [
            'title'            => "Rapport de tableau de bord — {$year}",
            'orgName'          => $org['name'],
            'orgShort'         => $org['short'],
            'orgLogo'          => $org['logo'],
            'brandColor'       => $org['color'],
            'year'             => $year,
            'period'           => "Année {$year}",
            'employees'        => $employees,
            'safety'           => $safety,
            'environment'      => $environment,
            'safetyTracker'    => $tracker,
            'contractors'      => $contractors,
            'visitors'         => $visitors,
            'incidentsByMonth' => $incidentsByMonth,
            'recentIncidents'  => $recentIncidents,
        ])->setPaper('a4', 'portrait');

        $this->auditLog($request, 'export_pdf', 'dashboard_report', 0);

        return $pdf->download("operix-dashboard-{$year}.pdf");
    }

    // ── Incidents PDF ─────────────────────────────────────────────────────────
    public function incidentsPdf(Request $request): Response
    {
        $validated = $request->validate([
            'from'     => 'nullable|date',
            'to'       => 'nullable|date',
            'severity' => 'nullable|string',
            'status'   => 'nullable|string',
            'type'     => 'nullable|string',
        ]);

        $org   = $this->orgBranding($request);
        $query = SafetyIncident::query()->with('reporter:id,name')->orderByDesc('date');

        $this->applyDateFilters($query, $validated, 'date');
        if (!empty($validated['severity'])) $query->where('severity', $validated['severity']);
        if (!empty($validated['status']))   $query->where('status',   $validated['status']);
        if (!empty($validated['type']))     $query->where('type',     $validated['type']);

        $incidents  = $query->get();
        $empCount   = Employee::where('is_active', true)->count();
        $ltiCount   = $incidents->where('type', 'LTI')->count();
        $tf         = $empCount > 0 ? round(($ltiCount * 1_000_000) / ($empCount * 200 * 8), 2) : 0;

        $pdf = Pdf::loadView('pdf.incidents', [
            'title'      => 'Rapport des incidents',
            'orgName'    => $org['name'],
            'orgShort'   => $org['short'],
            'orgLogo'    => $org['logo'],
            'brandColor' => $org['color'],
            'period'     => $this->periodLabel($validated),
            'incidents'  => $incidents,
            'stats'      => [
                'total'    => $incidents->count(),
                'open'     => $incidents->where('status', 'open')->count(),
                'closed'   => $incidents->where('status', 'closed')->count(),
                'critical' => $incidents->where('severity', 'critical')->count(),
                'lti'      => $ltiCount,
                'tf'       => $tf,
            ],
            'byType'     => $incidents->groupBy('type')->map->count(),
            'bySeverity' => $incidents->groupBy('severity')->map->count(),
        ])->setPaper('a4', 'landscape');

        $this->auditLog($request, 'export_pdf', 'incidents', 0);

        return $pdf->download('operix-incidents.pdf');
    }

    // ── Incident detail PDF ───────────────────────────────────────────────────
    public function incidentDetailPdf(Request $request, int $id): Response
    {
        $org      = $this->orgBranding($request);
        $incident = SafetyIncident::with('reporter:id,name')->findOrFail($id);

        $pdf = Pdf::loadView('pdf.incident_detail', [
            'title'      => "Incident {$incident->reference}",
            'orgName'    => $org['name'],
            'orgShort'   => $org['short'],
            'orgLogo'    => $org['logo'],
            'brandColor' => $org['color'],
            'incident'   => $incident,
        ])->setPaper('a4', 'portrait');

        $this->auditLog($request, 'export_pdf', 'incident', $id);

        return $pdf->download("incident-{$incident->reference}.pdf");
    }

    // ── Near miss PDF ─────────────────────────────────────────────────────────
    public function nearMissPdf(Request $request): Response
    {
        $validated = $request->validate(['from' => 'nullable|date', 'to' => 'nullable|date', 'status' => 'nullable|string']);
        $org       = $this->orgBranding($request);

        $query = SafetyNearMiss::query()->with('reporter:id,name')->orderByDesc('date');
        $this->applyDateFilters($query, $validated, 'date');
        if (!empty($validated['status'])) $query->where('status', $validated['status']);

        $records = $query->get();

        $pdf = Pdf::loadView('pdf.near_miss', [
            'title'      => "Rapport des presqu'accidents",
            'orgName'    => $org['name'],
            'orgShort'   => $org['short'],
            'orgLogo'    => $org['logo'],
            'brandColor' => $org['color'],
            'period'     => $this->periodLabel($validated),
            'records'    => $records,
            'stats'      => [
                'total'  => $records->count(),
                'open'   => $records->where('status', 'open')->count(),
                'closed' => $records->where('status', 'closed')->count(),
            ],
        ])->setPaper('a4', 'landscape');

        $this->auditLog($request, 'export_pdf', 'near_miss', 0);

        return $pdf->download('operix-near-miss.pdf');
    }

    // ── Breaches PDF ──────────────────────────────────────────────────────────
    public function breachesPdf(Request $request): Response
    {
        $validated = $request->validate(['from' => 'nullable|date', 'to' => 'nullable|date', 'status' => 'nullable|string']);
        $org       = $this->orgBranding($request);

        $query = Breach::query()->with('employee:id,nom,prenom,matricule')->orderByDesc('date');
        $this->applyDateFilters($query, $validated, 'date');
        if (!empty($validated['status'])) $query->where('status', $validated['status']);

        $records = $query->get();

        $pdf = Pdf::loadView('pdf.breaches', [
            'title'      => 'Rapport des infractions',
            'orgName'    => $org['name'],
            'orgShort'   => $org['short'],
            'orgLogo'    => $org['logo'],
            'brandColor' => $org['color'],
            'period'     => $this->periodLabel($validated),
            'records'    => $records,
            'stats'      => [
                'total'  => $records->count(),
                'open'   => $records->where('status', 'open')->count(),
                'closed' => $records->where('status', 'closed')->count(),
            ],
        ])->setPaper('a4', 'landscape');

        $this->auditLog($request, 'export_pdf', 'breaches', 0);

        return $pdf->download('operix-infractions.pdf');
    }

    // ── Environment PDF ───────────────────────────────────────────────────────
    public function environmentPdf(Request $request): Response
    {
        $validated = $request->validate(['from' => 'nullable|date', 'to' => 'nullable|date', 'status' => 'nullable|string', 'type' => 'nullable|string']);
        $org       = $this->orgBranding($request);

        $query = EnvironmentReport::query()->with('reporter:id,name')->orderByDesc('date');
        $this->applyDateFilters($query, $validated, 'date');
        if (!empty($validated['status'])) $query->where('status', $validated['status']);
        if (!empty($validated['type']))   $query->where('type',   $validated['type']);

        $records = $query->get();

        $pdf = Pdf::loadView('pdf.environment', [
            'title'      => 'Rapport environnement',
            'orgName'    => $org['name'],
            'orgShort'   => $org['short'],
            'orgLogo'    => $org['logo'],
            'brandColor' => $org['color'],
            'period'     => $this->periodLabel($validated),
            'records'    => $records,
            'stats'      => [
                'total'  => $records->count(),
                'open'   => $records->where('status', 'open')->count(),
                'closed' => $records->where('status', 'closed')->count(),
            ],
        ])->setPaper('a4', 'landscape');

        $this->auditLog($request, 'export_pdf', 'environment', 0);

        return $pdf->download('operix-environnement.pdf');
    }

    // ── Employees PDF ─────────────────────────────────────────────────────────
    public function employeesPdf(Request $request): Response
    {
        ini_set('memory_limit', '512M');
        $validated = $request->validate(['department_id' => 'nullable|integer', 'is_active' => 'nullable|boolean', 'type_contrat' => 'nullable|string']);
        $org       = $this->orgBranding();

        $query = Employee::query()->with('department:id,name')->whereNull('deleted_at')->orderBy('nom');
        if (isset($validated['department_id'])) $query->where('department_id', $validated['department_id']);
        if (isset($validated['is_active']))     $query->where('is_active',     $validated['is_active']);
        if (!empty($validated['type_contrat'])) $query->where('type_contrat',  $validated['type_contrat']);

        $employees = $query->get();

        $pdf = Pdf::loadView('pdf.employees', [
            'title'      => 'Rapport des employés',
            'orgName'    => $org['name'],
            'orgShort'   => $org['short'],
            'orgLogo'    => $org['logo'],
            'brandColor' => $org['color'],
            'employees'  => $employees,
            'stats'      => [
                'total'    => $employees->count(),
                'actifs'   => $employees->where('is_active', true)->count(),
                'inactifs' => $employees->where('is_active', false)->count(),
                'cdi'      => $employees->where('type_contrat', 'CDI')->count(),
                'cdd'      => $employees->where('type_contrat', 'CDD')->count(),
            ],
        ])->setPaper('a4', 'landscape');

        $this->auditLog($request, 'export_pdf', 'employees', 0);

        return $pdf->download('operix-employes.pdf');
    }

    // ── Employee profile PDF ──────────────────────────────────────────────────
    public function employeeProfilePdf(Request $request, int $id): Response
    {
        $org      = $this->orgBranding($request);
        $employee = Employee::with('department:id,name')->findOrFail($id);

        // Historique HSSE : événements où l'employé est impliqué (colonne jsonb
        // `employees`), même logique que l'écran profil (EmployeeController::history).
        $empJson  = json_encode([$id]);
        $incidents = SafetyIncident::whereRaw('employees @> ?::jsonb', [$empJson])
            ->orderByDesc('date')->get();
        $nearMiss  = SafetyNearMiss::whereRaw('employees @> ?::jsonb', [$empJson])
            ->orderByDesc('date')->get();
        $breaches  = Breach::where(function ($q) use ($id, $empJson) {
                $q->where('employee_id', $id)->orWhereRaw('employees @> ?::jsonb', [$empJson]);
            })->orderByDesc('date')->get();
        $environment = EnvironmentReport::whereRaw('employees @> ?::jsonb', [$empJson])
            ->orderByDesc('date')->get();

        $formations     = Formation::where('employee_id', $id)->orderByDesc('date_debut')->get();
        $certifications = Certification::where('employee_id', $id)->orderByDesc('date_obtention')->get();
        $medicalVisits  = MedicalVisit::where('employee_id', $id)->orderByDesc('date')->get();

        // Justificatifs image intégrés en base64 (dompdf ne charge pas d'URL distante).
        $formations->each(fn ($f)     => $f->img_data = $this->imgDataUri($f->certificat));
        $certifications->each(fn ($c)  => $c->img_data = $this->imgDataUri($c->document));
        $medicalVisits->each(fn ($v)   => $v->img_data = $this->imgDataUri($v->document));

        $pdf = Pdf::loadView('pdf.employee_profile', [
            'title'          => "Profil : {$employee->full_name}",
            'orgName'        => $org['name'],
            'orgShort'       => $org['short'],
            'orgLogo'        => $org['logo'],
            'brandColor'     => $org['color'],
            'employee'       => $employee,
            'incidents'      => $incidents,
            'nearMiss'       => $nearMiss,
            'breaches'       => $breaches,
            'environment'    => $environment,
            'formations'     => $formations,
            'certifications' => $certifications,
            'medicalVisits'  => $medicalVisits,
        ])->setPaper('a4', 'portrait');

        $this->auditLog($request, 'export_pdf', 'employee_profile', $id);

        return $pdf->download("profil-{$employee->matricule}.pdf");
    }

    // ── Permits PDF ───────────────────────────────────────────────────────────
    public function permitsPdf(Request $request): Response
    {
        $validated = $request->validate(['from' => 'nullable|date', 'to' => 'nullable|date', 'status' => 'nullable|string', 'type' => 'nullable|string']);
        $org       = $this->orgBranding($request);

        $query = PermitToWork::query()
            ->with(['contractor:id,company_name', 'requestedBy:id,name'])
            ->orderByDesc('valid_from');

        $this->applyDateFilters($query, $validated, 'valid_from');
        if (!empty($validated['status'])) $query->where('status', $validated['status']);
        if (!empty($validated['type']))   $query->where('type',   $validated['type']);

        $records = $query->get();

        $pdf = Pdf::loadView('pdf.permits', [
            'title'      => 'Rapport permis de travail',
            'orgName'    => $org['name'],
            'orgShort'   => $org['short'],
            'orgLogo'    => $org['logo'],
            'brandColor' => $org['color'],
            'period'     => $this->periodLabel($validated),
            'records'    => $records,
            'stats'      => [
                'total'    => $records->count(),
                'pending'  => $records->where('status', 'pending')->count(),
                'approved' => $records->where('status', 'approved')->count(),
                'closed'   => $records->where('status', 'closed')->count(),
            ],
        ])->setPaper('a4', 'landscape');

        $this->auditLog($request, 'export_pdf', 'permits', 0);

        return $pdf->download('operix-permis.pdf');
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    /**
     * Lit un fichier stocké (disque tenant-media ou public) et le renvoie en
     * data URI base64, uniquement s'il s'agit d'une image — pour l'intégrer
     * directement dans le PDF (dompdf ne récupère pas les URL distantes/signées).
     */
    private function imgDataUri(?string $path): ?string
    {
        if (! $path) {
            return null;
        }
        foreach ([config('operix.media_disk', 'tenant-media'), 'public'] as $disk) {
            try {
                $storage = \Illuminate\Support\Facades\Storage::disk($disk);
                if (! $storage->exists($path)) {
                    continue;
                }
                $mime = $storage->mimeType($path) ?: 'image/png';
                if (! str_starts_with($mime, 'image/')) {
                    return null; // PDF ou autre justificatif non-image : pas d'aperçu
                }
                return 'data:' . $mime . ';base64,' . base64_encode($storage->get($path));
            } catch (\Throwable $e) {
                // disque indisponible / fichier illisible : on ignore l'aperçu
            }
        }
        return null;
    }

    private function applyDateFilters($query, array $filters, string $field): void
    {
        if (!empty($filters['from'])) $query->whereDate($field, '>=', $filters['from']);
        if (!empty($filters['to']))   $query->whereDate($field, '<=', $filters['to']);
    }

    private function periodLabel(array $filters): string
    {
        if (!empty($filters['from']) && !empty($filters['to'])) {
            return "Du {$filters['from']} au {$filters['to']}";
        }
        if (!empty($filters['from'])) return "À partir du {$filters['from']}";
        if (!empty($filters['to']))   return "Jusqu'au {$filters['to']}";
        return 'Toutes périodes';
    }

    private function employeeKpis(int $month, int $year): array
    {
        $base = Employee::query()->whereNull('deleted_at');
        return [
            'total_actifs'      => (clone $base)->where('is_active', true)->count(),
            'total_inactifs'    => (clone $base)->where('is_active', false)->count(),
            'nouvelles_entrees' => (clone $base)->whereYear('date_embauche', $year)->whereMonth('date_embauche', $month)->count(),
            'entrees_ytd'       => (clone $base)->whereYear('date_embauche', $year)->count(),
        ];
    }

    private function safetyKpis(int $month, int $year): array
    {
        $incBase  = SafetyIncident::query();
        $nmBase   = SafetyNearMiss::query();
        $incYtd   = (clone $incBase)->whereYear('date', $year);
        $empCount = Employee::where('is_active', true)->count();
        $ltiYtd   = (clone $incYtd)->where('type', 'LTI')->count();
        $tf       = $empCount > 0 ? round(($ltiYtd * 1_000_000) / ($empCount * 200 * 8), 2) : 0;

        return [
            'incidents_mois'     => (clone $incBase)->whereYear('date',$year)->whereMonth('date',$month)->count(),
            'incidents_ytd'      => (clone $incYtd)->count(),
            'incidents_ouverts'  => (clone $incBase)->where('status','open')->count(),
            'near_miss_mois'     => (clone $nmBase)->whereYear('date',$year)->whereMonth('date',$month)->count(),
            'near_miss_ytd'      => (clone $nmBase)->whereYear('date',$year)->count(),
            'near_miss_ouverts'  => (clone $nmBase)->where('status','open')->count(),
            'lti_ytd'            => $ltiYtd,
            'taux_frequence'     => $tf,
            'infractions_mois'   => Breach::query()->whereYear('date',$year)->whereMonth('date',$month)->count(),
        ];
    }

    private function environmentKpis(int $month, int $year): array
    {
        $base = EnvironmentReport::query();
        return [
            'rapports_mois'    => (clone $base)->whereYear('date',$year)->whereMonth('date',$month)->count(),
            'rapports_ytd'     => (clone $base)->whereYear('date',$year)->count(),
            'rapports_ouverts' => (clone $base)->where('status','open')->count(),
        ];
    }


    private function contractorKpis(): array
    {
        $base = Contractor::query();
        return [
            'total_actifs'    => (clone $base)->where('status', 'active')->count(),
            'total_suspendus' => (clone $base)->where('status', 'suspended')->count(),
            'expires_30j'     => (clone $base)->where('status', 'active')
                                    ->whereNotNull('contract_end')
                                    ->whereDate('contract_end', '>=', now())
                                    ->whereDate('contract_end', '<=', now()->addDays(30))->count(),
        ];
    }

    private function visitorKpis(int $month, int $year): array
    {
        $base = Visitor::query();
        return [
            'presents'    => (clone $base)->whereNull('checked_out_at')->count(),
            'entrees_mois'=> (clone $base)->whereYear('created_at', $year)->whereMonth('created_at', $month)->count(),
            'entrees_ytd' => (clone $base)->whereYear('created_at', $year)->count(),
        ];
    }

    private function safetyTrackerData(): array
    {
        $lastDate = SafetyIncident::query()
            ->whereIn('type', ['LTI', 'FA', 'FAT'])
            ->whereNull('deleted_at')
            ->orderByDesc('date')
            ->value('date');

        $daysWithout = $lastDate
            ? (int) Carbon::parse($lastDate)->diffInDays(now())
            : (int) now()->startOfYear()->diffInDays(now());

        return [
            'days_without_accident' => $daysWithout,
            'last_incident_date'    => $lastDate ? Carbon::parse($lastDate)->format('d/m/Y') : 'Aucun',
        ];
    }
}
