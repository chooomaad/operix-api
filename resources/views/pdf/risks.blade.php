@extends('pdf.layout')

@section('content')

@php
  $levelLabels  = ['low' => 'Faible', 'medium' => 'Moyen', 'high' => 'Élevé', 'critical' => 'Critique'];
  $statusLabels = ['open' => 'Ouvert', 'monitoring' => 'Sous surveillance', 'closed' => 'Clôturé'];
  $statusBadge  = ['open' => 'open', 'monitoring' => 'in_progress', 'closed' => 'closed'];
@endphp

<table class="kpi-table" style="margin-top:12px;">
  <tr>
    <td class="kpi-td">
      <div class="kpi-inner c-blue">
        <div class="kpi-val" style="color:#2563eb;">{{ $stats['total'] }}</div>
        <div class="kpi-lbl">Total risques</div>
      </div>
    </td>
    <td class="kpi-td">
      <div class="kpi-inner c-red">
        <div class="kpi-val" style="color:#dc2626;">{{ $stats['critical'] }}</div>
        <div class="kpi-lbl">Critiques</div>
      </div>
    </td>
    <td class="kpi-td">
      <div class="kpi-inner c-amber">
        <div class="kpi-val" style="color:#d97706;">{{ $stats['open'] }}</div>
        <div class="kpi-lbl">Ouverts</div>
      </div>
    </td>
    <td class="kpi-td">
      <div class="kpi-inner c-green">
        <div class="kpi-val" style="color:#16a34a;">{{ $stats['closed'] }}</div>
        <div class="kpi-lbl">Clôturés</div>
      </div>
    </td>
  </tr>
</table>

<table width="100%" cellpadding="0" cellspacing="0" style="margin:14px 0 6px 0;">
  <tr><td style="background-color:{{ $brandColor ?? '#0f2847' }};color:#ffffff;font-weight:bold;font-size:10px;padding:5px 10px;">Registre des risques ({{ count($records) }})</td></tr>
</table>
<table class="data-table">
  <thead>
    <tr>
      <th style="width:78px;">Référence</th>
      <th style="width:56px;">Date</th>
      <th style="width:95px;">Catégorie</th>
      <th style="width:78px;">Lieu</th>
      <th>Risque</th>
      <th style="width:88px;" class="center">Risque initial</th>
      <th style="width:78px;" class="center">Résiduel</th>
      <th style="width:60px;">Statut</th>
    </tr>
  </thead>
  <tbody>
    @forelse($records as $r)
    <tr class="{{ $loop->even ? 'even' : '' }}">
      <td class="bold">{{ $r->reference }}</td>
      <td>{{ $r->date_identification?->format('d/m/Y') }}</td>
      <td class="muted" style="font-size:8.5px;">{{ $catLabels[$r->category] ?? $r->category }}</td>
      <td>{{ $r->location ?? '—' }}</td>
      <td>{{ \Illuminate\Support\Str::limit($r->risk_description ?? '', 55) }}</td>
      <td class="center">
        <span class="badge b-{{ $r->level }}">{{ $r->score }} · {{ $levelLabels[$r->level] ?? $r->level }}</span>
      </td>
      <td class="center">
        @if($r->residual_score)
          <span class="badge b-{{ $r->residual_level }}">{{ $r->residual_score }} · {{ $levelLabels[$r->residual_level] ?? $r->residual_level }}</span>
        @else <span class="muted">—</span> @endif
      </td>
      <td><span class="badge b-{{ $statusBadge[$r->status] ?? 'open' }}">{{ $statusLabels[$r->status] ?? $r->status }}</span></td>
    </tr>
    @empty
    <tr><td colspan="8" class="center muted" style="padding:16px">Aucun risque enregistré</td></tr>
    @endforelse
  </tbody>
</table>

@endsection
