@extends('pdf.layout')

@section('content')
<div class="info-grid">
  <div class="info-row"><div class="info-label">Référence</div><div class="info-value"><strong>{{ $incident->reference }}</strong></div></div>
  <div class="info-row"><div class="info-label">Date</div><div class="info-value">{{ $incident->date?->format('d/m/Y') }}</div></div>
  <div class="info-row"><div class="info-label">Heure</div><div class="info-value">{{ $incident->time ?? '-' }}</div></div>
  <div class="info-row"><div class="info-label">Lieu</div><div class="info-value">{{ $incident->location }}</div></div>
  <div class="info-row"><div class="info-label">Type</div><div class="info-value">{{ strtoupper($incident->type) }}</div></div>
  <div class="info-row"><div class="info-label">Gravité</div><div class="info-value"><span class="badge badge-{{ $incident->severity }}">{{ ucfirst($incident->severity) }}</span></div></div>
  <div class="info-row"><div class="info-label">Statut</div><div class="info-value"><span class="badge badge-{{ $incident->status }}">{{ ucfirst($incident->status) }}</span></div></div>
  <div class="info-row"><div class="info-label">Rapporté par</div><div class="info-value">{{ $incident->reporter?->name ?? '-' }}</div></div>
</div>

<div class="section-title">Description</div>
<p style="margin-bottom:12px;line-height:1.6">{{ $incident->description }}</p>

@if($incident->immediate_cause)
<div class="section-title">Cause immédiate</div>
<p style="margin-bottom:12px;line-height:1.6">{{ $incident->immediate_cause }}</p>
@endif

@if($incident->root_cause)
<div class="section-title">Cause racine</div>
<p style="margin-bottom:12px;line-height:1.6">{{ $incident->root_cause }}</p>
@endif

@if($incident->corrective_action)
<div class="section-title">Action corrective</div>
<p style="margin-bottom:4px;line-height:1.6">{{ $incident->corrective_action }}</p>
@if($incident->corrective_action_due)
<p style="font-size:10px;color:#6b7280">Échéance : {{ $incident->corrective_action_due?->format('d/m/Y') }}</p>
@endif
@endif

@if(!empty($incident->employees))
<div class="section-title">Employés impliqués</div>
<p>{{ implode(', ', $incident->employees) }}</p>
@endif
@endsection
