<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Risque HSSE (registre + évaluation par matrice 5×5).
 *
 * Le score (probabilité × gravité) et le niveau sont TOUJOURS recalculés ici, à
 * l'enregistrement : la règle métier vit dans le modèle, jamais dans le client.
 */
class Risk extends Model
{
    use Auditable, BelongsToTenant, SoftDeletes;

    /** Catégories de risque adaptées à l'environnement portuaire. */
    public const CATEGORIES = [
        'hse', 'industrial_safety', 'fire', 'work_at_height', 'traffic', 'handling',
        'equipment', 'chemicals', 'environment', 'ergonomics', 'electricity',
        'confined_space', 'hot_work', 'ship', 'security', 'unauthorized_access',
        'intrusion', 'theft', 'cctv', 'visitor_management', 'contractor',
    ];

    /** Modes d'évaluation : analyse de tâche, HIRA, évaluation générale. */
    public const ASSESSMENT_TYPES = ['jsa', 'hira', 'risk_assessment'];

    /** Hiérarchie des mesures de contrôle (de la plus efficace à la moins). */
    public const CONTROL_HIERARCHY = ['elimination', 'substitution', 'engineering', 'administrative', 'ppe'];

    public const STATUSES = ['open', 'monitoring', 'closed'];

    protected $attributes = [
        'status' => 'open',
        'assessment_type' => 'risk_assessment',
    ];

    protected $fillable = [
        'reference', 'date_identification', 'location', 'department_id', 'activity',
        'category', 'assessment_type', 'danger', 'risk_description', 'causes',
        'consequences', 'exposed_persons', 'existing_controls', 'owner_id',
        'probability', 'severity', 'controls',
        'residual_probability', 'residual_severity',
        'status', 'review_date', 'created_by',
    ];

    protected $casts = [
        'date_identification'  => 'date',
        'review_date'          => 'date',
        'controls'             => 'array',
        'probability'          => 'integer',
        'severity'             => 'integer',
        'score'                => 'integer',
        'residual_probability' => 'integer',
        'residual_severity'    => 'integer',
        'residual_score'       => 'integer',
    ];

    protected static function booted(): void
    {
        static::saving(function (self $risk): void {
            // Score initial = probabilité × gravité ; niveau dérivé du barème.
            $risk->score = (int) $risk->probability * (int) $risk->severity;
            $risk->level = self::levelForScore($risk->score);

            // Score résiduel : uniquement si les deux composantes sont renseignées.
            if ($risk->residual_probability && $risk->residual_severity) {
                $risk->residual_score = (int) $risk->residual_probability * (int) $risk->residual_severity;
                $risk->residual_level = self::levelForScore($risk->residual_score);
            } else {
                $risk->residual_score = null;
                $risk->residual_level = null;
            }
        });
    }

    /** Barème officiel : 1–4 faible, 5–9 moyen, 10–16 élevé, 17–25 critique. */
    public static function levelForScore(int $score): string
    {
        return match (true) {
            $score >= 17 => 'critical',
            $score >= 10 => 'high',
            $score >= 5  => 'medium',
            default      => 'low',
        };
    }

    public function department()
    {
        return $this->belongsTo(Department::class);
    }

    public function owner()
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function actions()
    {
        return $this->hasMany(RiskAction::class);
    }
}
