<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\SafetyIncident;
use App\Models\SafetyNearMiss;
use App\Traits\HandlesApiResources;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Rap2hpoutre\FastExcel\FastExcel;

class ImportController extends Controller
{
    use HandlesApiResources;

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
                $deptName = $this->pick($row, ['Intitulé département','Intitule departement','Département','Departement','department','Intitulé_département']);
                Employee::create([
                    'matricule'        => trim($this->pick($row, ['matricule','Matricule','MATRICULE','id','ID'])),
                    'nni'              => $this->pick($row, ['nni','NNI','Nni']) ?: null,
                    'nom'              => trim($this->pick($row, ['nom','Nom','NOM','last_name','lastName','name'])),
                    'prenom'           => trim($this->pick($row, ['prenom','Prénom','Prenom','PRENOM','first_name','firstName'])),
                    'poste'            => trim($this->pick($row, ['Emploi occupé','Emploi occupe','poste','Poste','POSTE','position','job_title','fonction'])) ?: 'Non défini',
                    'section'          => $deptName ?: null,
                    'department_id'    => $this->resolveDepartmentId($deptName),
                    'category_code'    => $this->pick($row, ['Code catégorie','Code categorie','category_code','code_categorie','Catégorie','Categorie']) ?: null,
                    'nombre_enfants'   => is_numeric($this->pick($row, ['Nombre d\'enfants','Nombre enfants','nombre_enfants','enfants']))
                                           ? (int) $this->pick($row, ['Nombre d\'enfants','Nombre enfants','nombre_enfants','enfants']) : null,
                    'email'            => $this->pick($row, ['email','Email','EMAIL','mail']) ?: null,
                    'phone'            => $this->pick($row, ['phone','Phone','Téléphone','telephone','tel','Tel']) ?: null,
                    'type_contrat'     => $this->normalizeContrat($this->pick($row, ['type_contrat','Type contrat','contrat','Contrat','contract_type']) ?: 'CDI'),
                    'date_embauche'    => $this->parseDate($this->pickRaw($row, ["Date d'embauche société", "Date d\u{2019}embauche société", "Date d'embauche", 'Date embauche', 'date_embauche', 'hire_date', 'date_entree'])),
                    'date_naissance'   => $this->parseDate($this->pickRaw($row, ['Date de naissance', 'date_naissance', 'birth_date', 'naissance'])),
                    'gender'           => $this->resolveGender($row),
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
            if (isset($row[$key]) && is_scalar($row[$key]) && trim((string)$row[$key]) !== '') {
                return trim((string)$row[$key]);
            }
        }
        return null;
    }

    /**
     * Résout le département par son intitulé (insensible casse/espaces) et le CRÉE
     * s'il n'existe pas encore (tenant-scopé par le global scope). Retourne l'id.
     */
    private function resolveDepartmentId(?string $name): ?int
    {
        $name = trim((string) $name);
        if ($name === '') {
            return null;
        }
        $dept = \App\Models\Department::whereRaw('LOWER(TRIM(name)) = ?', [mb_strtolower($name)])->first();
        if (! $dept) {
            $dept = \App\Models\Department::create(['name' => $name]);
        }
        return $dept->id;
    }

    /** Civilité (Monsieur/Madame) ou Genre/Sexe → 'M' / 'F'. */
    private function resolveGender(array $row): ?string
    {
        $raw = mb_strtolower($this->pick($row, ['Civilité','Civilite','gender','Genre','genre','sexe','Sexe']) ?? '');
        if ($raw === '') {
            return null;
        }
        if (in_array($raw, ['m', 'h', 'monsieur', 'mr', 'mr.', 'm.'], true))       return 'M';
        if (in_array($raw, ['f', 'madame', 'mme', 'mademoiselle', 'mlle'], true))  return 'F';
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
        // Délègue au vocabulaire canonique du modèle. L'ancienne table locale
        // ('FAT','MTI','FA','PP','autre') ne produisait QUE des valeurs refusées par
        // la contrainte CHECK : tout import d'incidents échouait en 500.
        return SafetyIncident::normalizeType($v);
    }

    private function normalizeSeverity(string $v): string
    {
        return in_array(strtolower($v), ['low','medium','high','critical'])
            ? strtolower($v)
            : 'medium';
    }

    private function parseDate($v): ?string
    {
        if (empty($v)) return null;
        if ($v instanceof \DateTimeInterface) return $v->format('Y-m-d');   // FastExcel renvoie des DateTime
        $v = trim((string) $v);
        if ($v === '') return null;
        foreach (['Y-m-d','d/m/Y','d-m-Y','m/d/Y','Y-m-d H:i:s','d/m/Y H:i:s'] as $fmt) {
            $d = \DateTime::createFromFormat($fmt, $v);
            if ($d) return $d->format('Y-m-d');
        }
        try { return \Carbon\Carbon::parse($v)->format('Y-m-d'); }
        catch (\Throwable $e) { return null; }
    }

    /** Comme pick() mais renvoie la valeur BRUTE (objet DateTime inclus, sans cast texte). */
    private function pickRaw(array $row, array $keys)
    {
        foreach ($keys as $key) {
            if (array_key_exists($key, $row) && $row[$key] !== null && $row[$key] !== '') {
                return $row[$key];
            }
        }
        return null;
    }
}
