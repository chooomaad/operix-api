<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Contractor;
use App\Models\ContractorEmployee;
use App\Models\Employee;
use App\Models\Intern;
use App\Models\SafetyIncident;
use App\Models\SafetyNearMiss;
use App\Models\Visitor;
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

    // ══ Import STAGIAIRES / VISITEURS / SOUS-TRAITANTS ══════════════════════════

    public function previewInterns(Request $request): JsonResponse
    {
        $request->validate(['file' => 'required|file|mimes:xlsx,xls,csv|max:10240']);
        return $this->smartPreview($request->file('file'), ['nom et prénom', 'nom et prenom', 'stagiaire', 'nom'], fn ($r) => $this->pick($r, ['Nom et Prénom', 'Nom et Prenom', 'Nom', 'nom']) ? [] : ['Nom obligatoire']);
    }

    public function importInterns(Request $request): JsonResponse
    {
        $request->validate(['file' => 'required|file|mimes:xlsx,xls,csv|max:10240']);
        $rows = $this->readSmart($request->file('file'), ['nom et prénom', 'nom et prenom', 'stagiaire', 'nom']);
        $created = 0; $errors = [];
        DB::transaction(function () use ($rows, &$created, &$errors) {
            foreach ($rows as $i => $r) {
                // Ligne de données seulement : un N° NUMÉRIQUE (skip en-tête secondaire « N° Ordre » et ligne TOTAL).
                $no = $this->pick($r, ['N°', 'N° Ordre', 'No', 'N', '#']);
                if ($no !== null && ! is_numeric($no)) { continue; }
                $nom = $this->pick($r, ['Nom et Prénom', 'Nom et Prenom', 'Nom', 'nom']);
                if (! $nom || in_array(mb_strtolower($nom), ['nom', 'nom et prénom', 'nom et prenom'], true)) { continue; }
                $org = $this->pick($r, ['Organisme', 'organisme', 'Établissement', 'etablissement']);
                $this->createWithReference('INT', Intern::class, [
                    'nom'             => $nom,
                    'prenom'          => '',
                    'etablissement'   => ($org && strtoupper($org) !== 'N/A') ? $org : null,
                    'departement'     => $this->pick($r, ['Département', 'Departement', 'departement']) ?: null,
                    'duree'           => $this->pick($r, ['Durée du Stage', 'Duree du Stage', 'duree']) ?: null,
                    'numero_identite' => $this->pick($r, ["Numéro d'identité", 'Numéro d’identité', "Numero d'identite", 'numero_identite', 'CNI', 'NNI']) ?: null,
                    'date_debut'      => $this->parseDate($this->pickRaw($r, ['Date de Début', 'Date de debut', 'Date debut', 'date_debut'])),
                    'date_fin'        => $this->parseDate($this->pickRaw($r, ['Date de Fin', 'Date fin', 'date_fin'])),
                    'status'          => 'active',
                    'is_active'       => true,
                ]);
                $created++;
            }
        });
        return response()->json(['message' => "{$created} stagiaire(s) importé(s).", 'created' => $created, 'errors' => $errors]);
    }

    public function previewVisitors(Request $request): JsonResponse
    {
        $request->validate(['file' => 'required|file|mimes:xlsx,xls,csv|max:10240']);
        return $this->smartPreview($request->file('file'), ['nom', 'visiteur', 'visitor'], fn ($r) => $this->pick($r, ['Nom', 'nom', 'Nom et Prénom']) ? [] : ['Nom obligatoire']);
    }

    public function importVisitors(Request $request): JsonResponse
    {
        $request->validate(['file' => 'required|file|mimes:xlsx,xls,csv|max:10240']);
        $rows = $this->readSmart($request->file('file'), ['nom', 'visiteur', 'visitor']);
        $created = 0;
        DB::transaction(function () use ($rows, &$created) {
            foreach ($rows as $r) {
                $nom = $this->pick($r, ['Nom', 'nom', 'Nom et Prénom']);
                if (! $nom) { continue; }
                Visitor::create([
                    'nom'              => $nom,
                    'prenom'           => $this->pick($r, ['Prénom', 'Prenom', 'prenom']) ?: '',
                    'entreprise'       => $this->pick($r, ['Entreprise', 'Société', 'Societe', 'entreprise', 'Organisme']) ?: null,
                    'phone'            => $this->pick($r, ['Téléphone', 'Telephone', 'phone', 'Tel']) ?: null,
                    'cin'              => $this->pick($r, ['CIN', "Numéro d'identité", 'cin', 'NNI']) ?: null,
                    'badge_number'     => $this->pick($r, ['Badge', 'badge_number', 'N° Badge']) ?: null,
                    'motif'            => $this->pick($r, ['Motif', 'Objet', 'motif']) ?: 'Visite',
                    'personne_visitee' => $this->pick($r, ['Personne visitée', 'Personne visitee', 'Hôte', 'personne_visitee']) ?: 'N/A',
                    'status'           => 'out',
                ]);
                $created++;
            }
        });
        return response()->json(['message' => "{$created} visiteur(s) importé(s).", 'created' => $created]);
    }

    public function previewContractors(Request $request): JsonResponse
    {
        $request->validate(['file' => 'required|file|mimes:xlsx,xls,csv|max:10240']);
        return $this->smartPreview($request->file('file'), ['société', 'societe', 'entreprise', 'company', 'raison sociale'], fn ($r) => $this->pick($r, ['Société', 'Societe', 'Entreprise', 'company_name', 'Raison sociale', 'Nom']) ? [] : ['Nom société obligatoire']);
    }

    public function importContractors(Request $request): JsonResponse
    {
        $request->validate(['file' => 'required|file|mimes:xlsx,xls,csv|max:10240']);
        $rows = $this->readSmart($request->file('file'), ['société', 'societe', 'entreprise', 'company', 'raison sociale']);
        $created = 0;
        DB::transaction(function () use ($rows, &$created) {
            foreach ($rows as $r) {
                $name = $this->pick($r, ['Société', 'Societe', 'Entreprise', 'company_name', 'Raison sociale', 'Nom']);
                if (! $name) { continue; }
                Contractor::create([
                    'company_name'  => $name,
                    'activite'      => $this->pick($r, ['Activité', 'Activite', 'activite', 'Secteur']) ?: 'Non défini',
                    'contact_nom'   => $this->pick($r, ['Contact', 'Responsable', 'contact_nom']) ?: null,
                    'contact_phone' => $this->pick($r, ['Téléphone', 'Telephone', 'contact_phone', 'Tel']) ?: null,
                    'contact_email' => $this->pick($r, ['Email', 'email', 'contact_email']) ?: null,
                    'num_registre'  => $this->pick($r, ['Registre', 'RC', 'num_registre']) ?: null,
                    'status'        => 'active',
                ]);
                $created++;
            }
        });
        return response()->json(['message' => "{$created} sous-traitant(s) importé(s).", 'created' => $created]);
    }

    /** Aperçu générique pour fichiers à en-tête décoré (détection intelligente). */
    private function smartPreview($file, array $keywords, callable $validator): JsonResponse
    {
        $rows = $this->readSmart($file, $keywords);
        $preview = []; $n = 0;
        foreach ($rows as $r) {
            $errs = $validator($r);
            if (! empty(array_filter($r, fn ($v) => is_scalar($v) && trim((string) $v) !== ''))) {
                $preview[] = ['data' => array_map(fn ($v) => $v instanceof \DateTimeInterface ? $v->format('Y-m-d') : $v, $r), 'errors' => $errs, 'valid' => empty($errs)];
                $n++;
            }
        }
        return response()->json([
            'total'   => count($preview),
            'valid'   => count(array_filter($preview, fn ($p) => $p['valid'])),
            'invalid' => count(array_filter($preview, fn ($p) => ! $p['valid'])),
            'preview' => array_slice($preview, 0, 50),
        ]);
    }

    /**
     * Lecture « intelligente » : détecte la ligne d'en-tête (contenant un mot-clé)
     * même si le fichier a des lignes de titre décoratives, puis lit à partir de là.
     * Les clés d'en-tête sont nettoyées (trim). Renvoie les lignes de données.
     */
    private function readSmart($file, array $keywords): array
    {
        $path = $file->getRealPath();
        $headerRow = $this->detectHeaderRow($path, $keywords);
        $rows = (new FastExcel)->startRow($headerRow)->import($path)->toArray();
        // Normalise les clés (certains en-têtes ont des espaces/retours à la ligne).
        return array_map(function ($r) {
            $clean = [];
            foreach ($r as $k => $v) { $clean[trim((string) $k)] = $v; }
            return $clean;
        }, $rows);
    }

    /** Numéro (1-based) de la ligne d'en-tête, repérée par un mot-clé. Défaut 1. */
    private function detectHeaderRow(string $path, array $keywords): int
    {
        try {
            $reader = new \OpenSpout\Reader\XLSX\Reader();
            $reader->open($path);
            $i = 0;
            foreach ($reader->getSheetIterator() as $sheet) {
                foreach ($sheet->getRowIterator() as $row) {
                    $i++;
                    $cells = array_map(fn ($c) => trim((string) $c->getValue()), $row->getCells());
                    // Ignore les lignes de titre/date fusionnées (une seule cellule remplie).
                    $nonEmpty = count(array_filter($cells, fn ($v) => $v !== ''));
                    if ($nonEmpty >= 3) {
                        $joined = mb_strtolower(implode('|', $cells));
                        foreach ($keywords as $kw) {
                            if (str_contains($joined, mb_strtolower($kw))) { $reader->close(); return $i; }
                        }
                    }
                    if ($i >= 15) { break; }
                }
                break;
            }
            $reader->close();
        } catch (\Throwable $e) {
            // fichier illisible en peek : on retombe sur la 1ère ligne
        }
        return 1;
    }

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
