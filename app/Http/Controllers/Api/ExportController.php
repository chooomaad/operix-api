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
use App\Traits\HandlesApiResources;
use Illuminate\Http\Request;
use Rap2hpoutre\FastExcel\FastExcel;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ExportController extends Controller
{
    use HandlesApiResources;

    public function employees(Request $request): StreamedResponse
    {
        $query = Employee::query()
            ->with('department:id,name')
            ->whereNull('deleted_at')
            ->orderBy('nom');

        if ($request->filled('department_id')) $query->where('department_id', $request->department_id);
        if ($request->filled('is_active'))     $query->where('is_active', filter_var($request->is_active, FILTER_VALIDATE_BOOLEAN));
        if ($request->filled('type_contrat'))  $query->where('type_contrat', $request->type_contrat);

        $data = $query->get()->map(fn ($e) => [
            'Matricule'      => $e->matricule,
            'NNI'            => $e->nni ?? '',
            'Nom'            => $e->nom,
            'Prénom'         => $e->prenom,
            'Poste'          => $e->poste,
            'Département'    => $e->department?->name ?? '',
            'Type contrat'   => $e->type_contrat,
            'Date embauche'  => $e->date_embauche?->format('d/m/Y') ?? '',
            'Statut'         => $e->is_active ? 'Actif' : 'Inactif',
            'Email'          => $e->email ?? '',
            'Téléphone'      => $e->phone ?? '',
            'Nationalité'    => $e->nationalite ?? '',
            'Genre'          => $e->gender ?? '',
            'Date naissance' => $e->date_naissance?->format('d/m/Y') ?? '',
            'Section'        => $e->section ?? '',
        ]);

        $this->auditLog($request, 'export_excel', 'employees', 0);
        return (new FastExcel($data))->download('employes-' . now()->format('Y-m-d') . '.xlsx');
    }

    public function incidents(Request $request): StreamedResponse
    {
        $query = SafetyIncident::query()->with('reporter:id,name')->orderByDesc('date');

        if ($request->filled('from'))     $query->whereDate('date', '>=', $request->from);
        if ($request->filled('to'))       $query->whereDate('date', '<=', $request->to);
        if ($request->filled('severity')) $query->where('severity', $request->severity);
        if ($request->filled('status'))   $query->where('status',   $request->status);
        if ($request->filled('type'))     $query->where('type',     $request->type);

        $data = $query->get()->map(fn ($i) => [
            'Référence'         => $i->reference,
            'Date'              => $i->date?->format('d/m/Y'),
            'Heure'             => $i->time ?? '',
            'Lieu'              => $i->location,
            'Type'              => $i->type,
            'Gravité'           => $i->severity,
            'Statut'            => $i->status,
            'Description'       => $i->description,
            'Cause immédiate'   => $i->immediate_cause ?? '',
            'Cause racine'      => $i->root_cause ?? '',
            'Action corrective' => $i->corrective_action ?? '',
            'Échéance CA'       => $i->corrective_action_due?->format('d/m/Y') ?? '',
            'Rapporté par'      => $i->reporter?->name ?? '',
        ]);

        $this->auditLog($request, 'export_excel', 'incidents', 0);
        return (new FastExcel($data))->download('incidents-' . now()->format('Y-m-d') . '.xlsx');
    }

    public function nearMiss(Request $request): StreamedResponse
    {
        $query = SafetyNearMiss::query()->with('reporter:id,name')->orderByDesc('date');

        if ($request->filled('from'))   $query->whereDate('date', '>=', $request->from);
        if ($request->filled('to'))     $query->whereDate('date', '<=', $request->to);
        if ($request->filled('status')) $query->where('status', $request->status);

        $data = $query->get()->map(fn ($n) => [
            'Référence'               => $n->reference,
            'Date'                    => $n->date?->format('d/m/Y'),
            'Lieu'                    => $n->location,
            'Gravité potentielle'     => $n->severity,
            'Description'             => $n->description,
            'Conséquence potentielle' => $n->potential_consequence ?? '',
            'Action corrective'       => $n->corrective_action ?? '',
            'Statut'                  => $n->status,
            'Rapporté par'            => $n->reporter?->name ?? '',
        ]);

        $this->auditLog($request, 'export_excel', 'near_miss', 0);
        return (new FastExcel($data))->download('presqu-accidents-' . now()->format('Y-m-d') . '.xlsx');
    }

    public function breaches(Request $request): StreamedResponse
    {
        $query = Breach::query()->with('employee:id,nom,prenom,matricule')->orderByDesc('date');

        if ($request->filled('from'))   $query->whereDate('date', '>=', $request->from);
        if ($request->filled('to'))     $query->whereDate('date', '<=', $request->to);
        if ($request->filled('status')) $query->where('status', $request->status);

        $data = $query->get()->map(fn ($b) => [
            'Référence'         => $b->reference,
            'Date'              => $b->date?->format('d/m/Y'),
            'Employé'           => $b->employee ? "{$b->employee->prenom} {$b->employee->nom}" : '',
            'Matricule'         => $b->employee?->matricule ?? '',
            'Type'              => $b->type ?? '',
            'Lieu'              => $b->location ?? '',
            'Gravité'           => $b->severity ?? '',
            'Description'       => $b->description,
            'Action corrective' => $b->corrective_action ?? '',
            'Statut'            => $b->status,
        ]);

        $this->auditLog($request, 'export_excel', 'breaches', 0);
        return (new FastExcel($data))->download('infractions-' . now()->format('Y-m-d') . '.xlsx');
    }

    public function environment(Request $request): StreamedResponse
    {
        $query = EnvironmentReport::query()->with('reporter:id,name')->orderByDesc('date');

        if ($request->filled('from'))   $query->whereDate('date', '>=', $request->from);
        if ($request->filled('to'))     $query->whereDate('date', '<=', $request->to);
        if ($request->filled('status')) $query->where('status', $request->status);
        if ($request->filled('type'))   $query->where('type',   $request->type);

        $data = $query->get()->map(fn ($r) => [
            'Référence'         => $r->reference,
            'Date'              => $r->date?->format('d/m/Y'),
            'Type'              => $r->type ?? '',
            'Description'       => $r->description,
            'Lieu'              => $r->location ?? '',
            'Gravité'           => $r->severity ?? '',
            'Impact'            => $r->impact ?? '',
            'Action corrective' => $r->corrective_action ?? '',
            'Statut'            => $r->status,
            'Rapporté par'      => $r->reporter?->name ?? '',
        ]);

        $this->auditLog($request, 'export_excel', 'environment', 0);
        return (new FastExcel($data))->download('environnement-' . now()->format('Y-m-d') . '.xlsx');
    }

    public function certifications(Request $request): StreamedResponse
    {
        $query = Certification::query()
            ->with('employee:id,nom,prenom,matricule')
            ->orderBy('date_expiration');

        $data = $query->get()->map(fn ($c) => [
            'Matricule'       => $c->employee?->matricule ?? '',
            'Employé'         => $c->employee ? "{$c->employee->prenom} {$c->employee->nom}" : '',
            'Type'            => $c->type,
            'N° Certificat'   => $c->numero ?? '',
            'Organisme'       => $c->organisme ?? '',
            'Date obtention'  => $c->date_obtention?->format('d/m/Y') ?? '',
            'Date expiration' => $c->date_expiration?->format('d/m/Y') ?? '',
            'Statut'          => $c->statut,
        ]);

        $this->auditLog($request, 'export_excel', 'certifications', 0);
        return (new FastExcel($data))->download('certifications-' . now()->format('Y-m-d') . '.xlsx');
    }

    public function medicalVisits(Request $request): StreamedResponse
    {
        $data = MedicalVisit::query()
            ->with('employee:id,nom,prenom,matricule')
            ->orderByDesc('date')
            ->get()
            ->map(fn ($v) => [
                'Matricule'        => $v->employee?->matricule ?? '',
                'Employé'          => $v->employee ? "{$v->employee->prenom} {$v->employee->nom}" : '',
                'Date visite'      => $v->date?->format('d/m/Y'),
                'Type'             => $v->type,
                'Médecin'          => $v->medecin ?? '',
                'Résultat'         => $v->resultat ?? '',
                'Restrictions'     => $v->restrictions ?? '',
                'Prochaine visite' => $v->prochaine_visite?->format('d/m/Y') ?? '',
            ]);

        $this->auditLog($request, 'export_excel', 'medical_visits', 0);
        return (new FastExcel($data))->download('visites-medicales-' . now()->format('Y-m-d') . '.xlsx');
    }

    public function permits(Request $request): StreamedResponse
    {
        $query = PermitToWork::query()
            ->with(['contractor:id,company_name', 'requestedBy:id,name'])
            ->orderByDesc('valid_from');

        if ($request->filled('status')) $query->where('status', $request->status);
        if ($request->filled('type'))   $query->where('type',   $request->type);

        $data = $query->get()->map(fn ($p) => [
            'Référence'   => $p->reference,
            'Type'        => $p->type,
            'Titre'       => $p->title,
            'Lieu'        => $p->location,
            'Prestataire' => $p->contractor?->company_name ?? $p->contractor_name ?? '',
            'Valide du'   => $p->valid_from?->format('d/m/Y'),
            'Valide au'   => $p->valid_to?->format('d/m/Y'),
            'Statut'      => $p->status,
            'Demandé par' => $p->requestedBy?->name ?? '',
        ]);

        $this->auditLog($request, 'export_excel', 'permits', 0);
        return (new FastExcel($data))->download('permis-travail-' . now()->format('Y-m-d') . '.xlsx');
    }

    public function visitors(Request $request): StreamedResponse
    {
        $query = Visitor::query()->orderByDesc('checked_in_at');

        if ($request->filled('from')) $query->whereDate('checked_in_at', '>=', $request->from);
        if ($request->filled('to'))   $query->whereDate('checked_in_at', '<=', $request->to);

        $data = $query->get()->map(fn ($v) => [
            'NNI'         => $v->nni ?? '',
            'Nom'         => $v->nom,
            'Prénom'      => $v->prenom,
            'Entreprise'  => $v->entreprise ?? '',
            'Motif'       => $v->motif ?? '',
            'Badge'       => $v->badge_number ?? '',
            'Arrivée'     => $v->checked_in_at?->format('d/m/Y H:i'),
            'Départ'      => $v->checked_out_at?->format('d/m/Y H:i') ?? '',
            'Statut'      => $v->status,
        ]);

        $this->auditLog($request, 'export_excel', 'visitors', 0);
        return (new FastExcel($data))->download('visiteurs-' . now()->format('Y-m-d') . '.xlsx');
    }

    public function contractors(Request $request): StreamedResponse
    {
        $query = Contractor::query()->whereNull('deleted_at')->orderBy('company_name');

        if ($request->filled('status')) $query->where('status', $request->status);

        $data = $query->get()->map(fn ($c) => [
            'Société'       => $c->company_name,
            'Activité'      => $c->activite ?? '',
            'Contact'       => $c->contact_nom ?? '',
            'Email'         => $c->contact_email ?? '',
            'Téléphone'     => $c->contact_phone ?? '',
            'N° Registre'   => $c->num_registre ?? '',
            'NNI'           => $c->nni ?? '',
            'Début contrat' => $c->contract_start?->format('d/m/Y') ?? '',
            'Fin contrat'   => $c->contract_end?->format('d/m/Y') ?? '',
            'Statut'        => $c->status,
        ]);

        $this->auditLog($request, 'export_excel', 'contractors', 0);
        return (new FastExcel($data))->download('prestataires-' . now()->format('Y-m-d') . '.xlsx');
    }

    public function employeeImportTemplate(): StreamedResponse
    {
        $template = collect([[
            'matricule'     => 'TCN-2024-001',
            'nni'           => '1234567890123',
            'nom'           => 'OULD AHMED',
            'prenom'        => 'Mohamed',
            'poste'         => 'Technicien HSE',
            'section'       => 'Operations',
            'email'         => 'mohamed.ahmed@tcn.mr',
            'phone'         => '+222 22 00 00 00',
            'type_contrat'  => 'CDI',
            'date_embauche' => '2024-01-15',
            'gender'        => 'M',
            'nationalite'   => 'Mauritanienne',
        ]]);

        return (new FastExcel($template))->download('modele-import-employes.xlsx');
    }
}
