<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\SafetyIncident;
use App\Models\SafetyNearMiss;
use App\Traits\HasTenantScope;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Rap2hpoutre\FastExcel\FastExcel;

class ImportController extends Controller
{
    use HasTenantScope;

    public function previewEmployees(Request $request): JsonResponse
    {
        $request->validate(['file' => 'required|file|mimes:xlsx,xls,csv|max:10240']);

        return $this->runPreview(
            file: $request->file('file'),
            validator: fn ($row) => $this->validateEmployeeRow($row),
        );
    }

    public function importEmployees(Request $request): JsonResponse
    {
        $request->validate(['file' => 'required|file|mimes:xlsx,xls,csv|max:10240']);

        return $this->runImport(
            file: $request->file('file'),
            validator: fn ($row) => $this->validateEmployeeRow($row),
            inserter: function ($row) use ($request) {
                Employee::create([
                    'matricule'        => trim($this->pick($row, ['matricule','Matricule','MATRICULE','id','ID'])),
                    'nni'              => $this->pick($row, ['nni','NNI','Nni']) ?: null,
                    'nom'              => trim($this->pick($row, ['nom','Nom','NOM','last_name','lastName','name'])),
                    'prenom'           => trim($this->pick($row, ['prenom','Prénom','PRENOM','first_name','firstName','prenom'])),
                    'poste'            => trim($this->pick($row, ['poste','Poste','POSTE','position','job_title','fonction'])) ?: 'Non défini',
                    'section'          => $this->pick($row, ['section','Section','département','Département','department']) ?: null,
                    'email'            => $this->pick($row, ['email','Email','EMAIL','mail']) ?: null,
                    'phone'            => $this->pick($row, ['phone','Phone','Téléphone','telephone','tel','Tel']) ?: null,
                    'type_contrat'     => $this->normalizeContrat($this->pick($row, ['type_contrat','Type contrat','contrat','Contrat','contract_type']) ?: 'CDI'),
                    'date_embauche'    => $this->parseDate($this->pick($row, ['date_embauche','Date embauche','date_d\'embauche','date_d\'embauche_société','hire_date','hireDate','date_entree']) ?: null),
                    'gender'           => in_array($this->pick($row, ['gender','Genre','genre','sexe','Sexe']) ?? '', ['M','F','m','f'])
                                           ? strtoupper($this->pick($row, ['gender','Genre','genre','sexe','Sexe'])) : null,
                    'nationalite'      => $this->pick($row, ['nationalite','Nationalité','nationality','nationalité']) ?: null,
                    'is_active'        => true,
                    'last_modified_by' => $request->user()->id,
                    'last_modified_at' => now(),
                ]);
            },
        );
    }

    public function previewIncidents(Request $request): JsonResponse
    {
        $request->validate(['file' => 'required|file|mimes:xlsx,xls,csv|max:10240']);
        return $this->runPreview(
            file: $request->file('file'),
            validator: fn ($row) => $this->validateIncidentRow($row),
        );
    }

    public function importIncidents(Request $request): JsonResponse
    {
        $request->validate(['file' => 'required|file|mimes:xlsx,xls,csv|max:10240']);

        return $this->runImport(
            file: $request->file('file'),
            validator: fn ($row) => $this->validateIncidentRow($row),
            inserter: function ($row) use ($request) {
                $ref = $this->generateReference('INC', SafetyIncident::class);
                SafetyIncident::create([
                    'reference'   => $ref,
                    'date'        => $this->parseDate($row['date']      ?? $row['Date']        ?? null),
                    'time'        => $row['time']                        ?? $row['Heure']       ?? null,
                    'location'    => trim($row['location']              ?? $row['Lieu']         ?? 'Non défini'),
                    'type'        => $this->normalizeIncidentType($row['type'] ?? $row['Type'] ?? 'autre'),
                    'severity'    => $this->normalizeSeverity($row['severity'] ?? $row['Gravité'] ?? 'medium'),
                    'description' => trim($row['description']           ?? $row['Description']  ?? ''),
                    'status'      => 'open',
                    'reported_by' => $request->user()->id,
                ]);
            },
        );
    }

    public function importNearMiss(Request $request): JsonResponse
    {
        $request->validate(['file' => 'required|file|mimes:xlsx,xls,csv|max:10240']);

        return $this->runImport(
            file: $request->file('file'),
            validator: fn ($row) => $this->validateNearMissRow($row),
            inserter: function ($row) use ($request) {
                $ref = $this->generateReference('NM', SafetyNearMiss::class);
                SafetyNearMiss::create([
                    'reference'            => $ref,
                    'date'                 => $this->parseDate($row['date'] ?? $row['Date'] ?? null),
                    'location'             => trim($row['location']  ?? $row['Lieu']        ?? 'Non défini'),
                    'severity'             => $this->normalizeSeverity($row['severity'] ?? $row['Gravité potentielle'] ?? 'medium'),
                    'description'          => trim($row['description'] ?? $row['Description'] ?? ''),
                    'potential_consequence'=> $row['potential_consequence'] ?? $row['Conséquence potentielle'] ?? null,
                    'status'               => 'open',
                    'reported_by'          => $request->user()->id,
                ]);
            },
        );
    }

    // ── Infrastructure privée ─────────────────────────────────────────────────

    private function runPreview($file, callable $validator): JsonResponse
    {
        try {
            $rows    = $this->readFile($file);
            $preview = [];
            $errors  = [];
            $rowNum  = 1;

            foreach ($rows as $row) {
                $rowNum++;
                $errs      = $validator($row);
                $preview[] = ['row' => $rowNum, 'data' => $row, 'errors' => $errs, 'valid' => empty($errs)];
                foreach ($errs as $e) {
                    $errors[] = "Ligne {$rowNum}: {$e}";
                }
            }

            return response()->json([
                'total'   => count($preview),
                'valid'   => count(array_filter($preview, fn ($r) => $r['valid'])),
                'invalid' => count(array_filter($preview, fn ($r) => !$r['valid'])),
                'preview' => array_slice($preview, 0, 50),
                'errors'  => $errors,
            ]);
        } catch (\Throwable $e) {
            return response()->json(['message' => 'Erreur lecture fichier: ' . $e->getMessage()], 422);
        }
    }

    private function runImport($file, callable $validator, callable $inserter): JsonResponse
    {
        $rows    = $this->readFile($file);
        $created = 0;
        $errors  = [];
        $rowNum  = 1;

        DB::beginTransaction();
        try {
            foreach ($rows as $row) {
                $rowNum++;
                $errs = $validator($row);
                if (!empty($errs)) {
                    $errors[] = "Ligne {$rowNum}: " . implode('; ', $errs);
                    continue;
                }
                try {
                    $inserter($row);
                    $created++;
                } catch (\Throwable $e) {
                    $errors[] = "Ligne {$rowNum}: " . $e->getMessage();
                }
            }
            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            return response()->json(['message' => 'Import échoué : ' . $e->getMessage()], 500);
        }

        return response()->json([
            'message' => "{$created} enregistrement(s) importé(s).",
            'created' => $created,
            'errors'  => $errors,
            'total'   => $rowNum - 1,
        ]);
    }

    private function readFile($file): array
    {
        $ext = strtolower($file->getClientOriginalExtension());
        if ($ext === 'csv') {
            $handle  = fopen($file->getRealPath(), 'r');
            $headers = array_map('trim', fgetcsv($handle));
            $rows    = [];
            while (($line = fgetcsv($handle)) !== false) {
                if (count($line) === count($headers)) {
                    $rows[] = array_combine($headers, array_map('trim', $line));
                }
            }
            fclose($handle);
            return $rows;
        }
        return (new FastExcel)->import($file->getRealPath())->toArray();
    }

    // ── Validators ────────────────────────────────────────────────────────────

    private function validateEmployeeRow(array $row): array
    {
        $errors    = [];
        $matricule = $this->pick($row, ['matricule','Matricule','MATRICULE','id','ID']) ?? '';
        $nom       = $this->pick($row, ['nom','Nom','NOM','last_name','lastName','name']) ?? '';
        $prenom    = $this->pick($row, ['prenom','Prénom','PRENOM','first_name','firstName']) ?? '';

        if (empty($matricule)) $errors[] = 'Matricule obligatoire';
        if (empty($nom))       $errors[] = 'Nom obligatoire';
        if (empty($prenom))    $errors[] = 'Prénom obligatoire';

        if ($matricule && Employee::whereRaw('LOWER(matricule) = ?', [strtolower($matricule)])->exists()) {
            $errors[] = "Matricule '{$matricule}' déjà existant";
        }

        return $errors;
    }

    private function validateIncidentRow(array $row): array
    {
        $errors = [];
        if (empty($row['date'] ?? $row['Date'] ?? ''))               $errors[] = 'Date obligatoire';
        if (empty(trim($row['description'] ?? $row['Description'] ?? ''))) $errors[] = 'Description obligatoire';
        return $errors;
    }

    private function validateNearMissRow(array $row): array
    {
        $errors = [];
        if (empty($row['date'] ?? $row['Date'] ?? ''))               $errors[] = 'Date obligatoire';
        if (empty(trim($row['description'] ?? $row['Description'] ?? ''))) $errors[] = 'Description obligatoire';
        return $errors;
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    private function pick(array $row, array $keys): ?string
    {
        foreach ($keys as $key) {
            if (isset($row[$key]) && trim((string)$row[$key]) !== '') {
                return trim((string)$row[$key]);
            }
        }
        return null;
    }

    // ── Normaliseurs ──────────────────────────────────────────────────────────

    private function normalizeContrat(string $v): string
    {
        return in_array(strtoupper($v), ['CDI','CDD','STAGE','PRESTATAIRE','AUTRE'])
            ? strtoupper($v)
            : 'CDI';
    }

    private function normalizeIncidentType(string $v): string
    {
        $map = ['LTI'=>'LTI','FAT'=>'FAT','MTI'=>'MTI','FA'=>'FA','PP'=>'PP'];
        return $map[strtoupper($v)] ?? 'autre';
    }

    private function normalizeSeverity(string $v): string
    {
        return in_array(strtolower($v), ['low','medium','high','critical'])
            ? strtolower($v)
            : 'medium';
    }

    private function parseDate(?string $v): ?string
    {
        if (!$v) return null;
        foreach (['Y-m-d','d/m/Y','d-m-Y','m/d/Y'] as $fmt) {
            $d = \DateTime::createFromFormat($fmt, trim($v));
            if ($d) return $d->format('Y-m-d');
        }
        return null;
    }
}
