@extends('pdf.layout')

@section('content')

{{-- ── Sécurité ─────────────────────────────────────────────────────────── --}}
<table width="100%" cellpadding="0" cellspacing="0" style="margin:12px 0 6px 0;">
  <tr><td style="background-color:{{ $brandColor ?? '#0f2847' }};color:#ffffff;font-weight:bold;font-size:10px;padding:5px 10px;">Sécurité — {{ $year }}</td></tr>
</table>
<table class="kpi-table">
  <tr>
    <td class="kpi-td">
      <div class="kpi-inner c-blue">
        <div class="kpi-val" style="color:#2563eb;">{{ $safety['incidents_ytd'] ?? 0 }}</div>
        <div class="kpi-lbl">Incidents YTD</div>
      </div>
    </td>
    <td class="kpi-td">
      <div class="kpi-inner c-red">
        <div class="kpi-val" style="color:#dc2626;">{{ $safety['incidents_ouverts'] ?? 0 }}</div>
        <div class="kpi-lbl">En cours</div>
      </div>
    </td>
    <td class="kpi-td">
      <div class="kpi-inner c-amber">
        <div class="kpi-val" style="color:#d97706;">{{ $safety['near_miss_ytd'] ?? 0 }}</div>
        <div class="kpi-lbl">Presqu'accidents YTD</div>
      </div>
    </td>
    <td class="kpi-td">
      <div class="kpi-inner c-red">
        <div class="kpi-val" style="color:#dc2626;">{{ $safety['lti_ytd'] ?? 0 }}</div>
        <div class="kpi-lbl">LTI</div>
      </div>
    </td>
    <td class="kpi-td">
      <div class="kpi-inner c-brand">
        <div class="kpi-val" style="color:{{ $brandColor ?? '#0f2847' }};">{{ $safety['taux_frequence'] ?? 0 }}</div>
        <div class="kpi-lbl">Taux de fréquence</div>
      </div>
    </td>
    <td class="kpi-td">
      <div class="kpi-inner c-amber">
        <div class="kpi-val" style="color:#d97706;">{{ $safety['infractions_mois'] ?? 0 }}</div>
        <div class="kpi-lbl">Infractions (mois)</div>
      </div>
    </td>
  </tr>
</table>

{{-- ── Safety Tracker ─────────────────────────────────────────────────── --}}
@if(isset($safetyTracker))
<div class="tracker-box">
  <table style="width:100%;border-collapse:collapse;">
    <tr>
      <td style="width:180px;vertical-align:middle;">
        <span style="font-size:32px;font-weight:bold;color:#15803d;">{{ $safetyTracker['days_without_accident'] ?? 0 }}</span>
        <span style="font-size:12px;color:#15803d;font-weight:bold;"> jours</span>
        <div style="font-size:8.5px;color:#166534;margin-top:2px;">sans accident avec arrêt de travail</div>
      </td>
      <td style="vertical-align:middle;padding-left:16px;border-left:2px solid #86efac;">
        <div style="font-size:10px;color:#166534;font-weight:bold;">Safety Tracker</div>
        <div style="font-size:9px;color:#4b7c59;margin-top:3px;">
          Dernier incident : <strong>{{ $safetyTracker['last_incident_date'] ?? 'Aucun enregistré' }}</strong>
        </div>
        @if(!empty($safetyTracker['best_streak']))
        <div style="font-size:9px;color:#4b7c59;margin-top:2px;">
          Meilleur record : <strong>{{ $safetyTracker['best_streak'] }} jours</strong>
        </div>
        @endif
      </td>
    </tr>
  </table>
</div>
@endif

{{-- ── Effectifs ────────────────────────────────────────────────────────── --}}
<table width="100%" cellpadding="0" cellspacing="0" style="margin:14px 0 6px 0;">
  <tr><td style="background-color:{{ $brandColor ?? '#0f2847' }};color:#ffffff;font-weight:bold;font-size:10px;padding:5px 10px;">Effectifs</td></tr>
</table>
<table class="kpi-table">
  <tr>
    <td class="kpi-td">
      <div class="kpi-inner c-green">
        <div class="kpi-val" style="color:#16a34a;">{{ $employees['total_actifs'] ?? 0 }}</div>
        <div class="kpi-lbl">Employés actifs</div>
      </div>
    </td>
    <td class="kpi-td">
      <div class="kpi-inner">
        <div class="kpi-val" style="color:#64748b;">{{ $employees['total_inactifs'] ?? 0 }}</div>
        <div class="kpi-lbl">Inactifs</div>
      </div>
    </td>
    <td class="kpi-td">
      <div class="kpi-inner c-blue">
        <div class="kpi-val" style="color:#2563eb;">{{ $employees['entrees_ytd'] ?? 0 }}</div>
        <div class="kpi-lbl">Entrées YTD</div>
      </div>
    </td>
    <td class="kpi-td">
      <div class="kpi-inner">
        <div class="kpi-val" style="color:#1a1a2e;">{{ $employees['nouvelles_entrees'] ?? 0 }}</div>
        <div class="kpi-lbl">Nouvelles entrées (mois)</div>
      </div>
    </td>
    <td class="kpi-td">
      <div class="kpi-inner c-amber">
        <div class="kpi-val" style="color:#d97706;">{{ $contractors['total_actifs'] ?? 0 }}</div>
        <div class="kpi-lbl">Prestataires actifs</div>
      </div>
    </td>
    <td class="kpi-td">
      <div class="kpi-inner">
        <div class="kpi-val" style="color:#1a1a2e;">{{ $visitors['entrees_mois'] ?? 0 }}</div>
        <div class="kpi-lbl">Visiteurs (mois)</div>
      </div>
    </td>
  </tr>
</table>

{{-- ── Environnement & Opérations ──────────────────────────────────────── --}}
<table width="100%" cellpadding="0" cellspacing="0" style="margin:14px 0 6px 0;">
  <tr><td style="background-color:{{ $brandColor ?? '#0f2847' }};color:#ffffff;font-weight:bold;font-size:10px;padding:5px 10px;">Environnement &amp; Opérations</td></tr>
</table>
<table class="kpi-table">
  <tr>
    <td class="kpi-td">
      <div class="kpi-inner c-green">
        <div class="kpi-val" style="color:#16a34a;">{{ $environment['rapports_ytd'] ?? 0 }}</div>
        <div class="kpi-lbl">Rapports env. YTD</div>
      </div>
    </td>
    <td class="kpi-td">
      <div class="kpi-inner c-red">
        <div class="kpi-val" style="color:#dc2626;">{{ $environment['rapports_ouverts'] ?? 0 }}</div>
        <div class="kpi-lbl">Rapports ouverts</div>
      </div>
    </td>
    <td class="kpi-td">
      <div class="kpi-inner c-amber">
        <div class="kpi-val" style="color:#d97706;">{{ $gemba['total_ouverts'] ?? 0 }}</div>
        <div class="kpi-lbl">Gemba ouverts</div>
      </div>
    </td>
    <td class="kpi-td">
      <div class="kpi-inner c-red">
        <div class="kpi-val" style="color:#dc2626;">{{ $gemba['en_retard'] ?? 0 }}</div>
        <div class="kpi-lbl">Gemba en retard</div>
      </div>
    </td>
    <td class="kpi-td">
      <div class="kpi-inner">
        <div class="kpi-val" style="color:#1a1a2e;">{{ $safety['near_miss_ouverts'] ?? 0 }}</div>
        <div class="kpi-lbl">Near miss ouverts</div>
      </div>
    </td>
    <td class="kpi-td">
      <div class="kpi-inner c-brand">
        <div class="kpi-val" style="color:{{ $brandColor ?? '#0f2847' }};">{{ $safety['incidents_mois'] ?? 0 }}</div>
        <div class="kpi-lbl">Incidents (mois)</div>
      </div>
    </td>
  </tr>
</table>

{{-- ── Évolution incidents par mois ────────────────────────────────────── --}}
@if(!empty($incidentsByMonth))
<table width="100%" cellpadding="0" cellspacing="0" style="margin:14px 0 6px 0;">
  <tr><td style="background-color:{{ $brandColor ?? '#0f2847' }};color:#ffffff;font-weight:bold;font-size:10px;padding:5px 10px;">Évolution des incidents — {{ $year }}</td></tr>
</table>
<table class="data-table">
  <thead>
    <tr>
      @foreach(['Jan','Fév','Mar','Avr','Mai','Jun','Jul','Aoû','Sep','Oct','Nov','Déc'] as $m)
      <th class="center">{{ $m }}</th>
      @endforeach
      <th class="right">Total</th>
    </tr>
  </thead>
  <tbody>
    <tr>
      @php $total = 0; @endphp
      @foreach(range(1,12) as $m)
        @php $v = $incidentsByMonth[str_pad($m,2,'0',STR_PAD_LEFT)] ?? 0; $total += $v; @endphp
        <td class="center" style="{{ $v > 0 ? 'font-weight:bold;color:#dc2626;' : 'color:#94a3b8;' }}">{{ $v }}</td>
      @endforeach
      <td class="right bold">{{ $total }}</td>
    </tr>
  </tbody>
</table>
@endif

{{-- ── Derniers incidents ─────────────────────────────────────────────── --}}
@if(!empty($recentIncidents) && count($recentIncidents) > 0)
<table width="100%" cellpadding="0" cellspacing="0" style="margin:14px 0 6px 0;">
  <tr><td style="background-color:{{ $brandColor ?? '#0f2847' }};color:#ffffff;font-weight:bold;font-size:10px;padding:5px 10px;">Derniers incidents enregistrés</td></tr>
</table>
<table class="data-table">
  <thead>
    <tr>
      <th style="width:85px;">Référence</th>
      <th style="width:62px;">Date</th>
      <th>Description</th>
      <th style="width:78px;">Lieu</th>
      <th style="width:52px;">Type</th>
      <th style="width:58px;">Gravité</th>
      <th style="width:52px;">Statut</th>
    </tr>
  </thead>
  <tbody>
    @foreach($recentIncidents as $inc)
    <tr class="{{ $loop->even ? 'even' : '' }}">
      <td class="bold">{{ $inc->reference }}</td>
      <td>{{ $inc->date?->format('d/m/Y') }}</td>
      <td>{{ \Illuminate\Support\Str::limit($inc->description ?? '', 70) }}</td>
      <td>{{ $inc->location }}</td>
      <td class="center" style="font-size:8px;">{{ strtoupper($inc->type ?? '') }}</td>
      <td><span class="badge b-{{ $inc->severity }}">{{ ucfirst($inc->severity ?? '') }}</span></td>
      <td><span class="badge b-{{ $inc->status }}">{{ $inc->status === 'open' ? 'Ouvert' : 'Clôturé' }}</span></td>
    </tr>
    @endforeach
  </tbody>
</table>
@endif

@endsection
