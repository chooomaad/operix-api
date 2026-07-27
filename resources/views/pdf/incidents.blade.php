@extends('pdf.layout')

@section('content')

{{-- KPI --}}
<table class="kpi-table" style="margin-top:12px;">
  <tr>
    <td class="kpi-td">
      <div class="kpi-inner c-blue">
        <div class="kpi-val" style="color:#2563eb;">{{ $stats['total'] }}</div>
        <div class="kpi-lbl">Total incidents</div>
      </div>
    </td>
    <td class="kpi-td">
      <div class="kpi-inner c-red">
        <div class="kpi-val" style="color:#dc2626;">{{ $stats['open'] }}</div>
        <div class="kpi-lbl">En cours</div>
      </div>
    </td>
    <td class="kpi-td">
      <div class="kpi-inner c-green">
        <div class="kpi-val" style="color:#16a34a;">{{ $stats['closed'] }}</div>
        <div class="kpi-lbl">Clôturés</div>
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
        <div class="kpi-val" style="color:#d97706;">{{ $stats['lti'] }}</div>
        <div class="kpi-lbl">LTI</div>
      </div>
    </td>
    <td class="kpi-td">
      <div class="kpi-inner c-brand">
        <div class="kpi-val" style="color:{{ $brandColor ?? '#0f2847' }};">{{ $stats['tf'] }}</div>
        <div class="kpi-lbl">Taux de fréquence</div>
      </div>
    </td>
  </tr>
</table>

{{-- Répartition --}}
@if(!empty($byType) || !empty($bySeverity))
<div class="clearfix" style="margin-bottom:12px">
  <div class="col-left">
    <table width="100%" cellpadding="0" cellspacing="0" style="margin:14px 0 6px 0;">
      <tr><td style="background-color:{{ $brandColor ?? '#0f2847' }};color:#ffffff;font-weight:bold;font-size:10px;padding:5px 10px;">Par type</td></tr>
    </table>
    <table class="data-table">
      <thead>
        <tr>
          <th>Type</th>
          <th class="right">Nb</th>
          <th class="right">%</th>
        </tr>
      </thead>
      <tbody>
        @foreach($byType as $type => $count)
        <tr class="{{ $loop->even ? 'even' : '' }}">
          <td class="bold">{{ strtoupper($type) }}</td>
          <td class="right">{{ $count }}</td>
          <td class="right muted">{{ $stats['total'] > 0 ? round($count/$stats['total']*100) : 0 }}%</td>
        </tr>
        @endforeach
      </tbody>
    </table>
  </div>
  <div class="col-right">
    <table width="100%" cellpadding="0" cellspacing="0" style="margin:14px 0 6px 0;">
      <tr><td style="background-color:{{ $brandColor ?? '#0f2847' }};color:#ffffff;font-weight:bold;font-size:10px;padding:5px 10px;">Par gravité</td></tr>
    </table>
    <table class="data-table">
      <thead>
        <tr>
          <th>Gravité</th>
          <th class="right">Nb</th>
          <th class="right">%</th>
        </tr>
      </thead>
      <tbody>
        @foreach($bySeverity as $sev => $count)
        <tr class="{{ $loop->even ? 'even' : '' }}">
          <td><span class="badge b-{{ $sev }}">{{ ucfirst($sev) }}</span></td>
          <td class="right">{{ $count }}</td>
          <td class="right muted">{{ $stats['total'] > 0 ? round($count/$stats['total']*100) : 0 }}%</td>
        </tr>
        @endforeach
      </tbody>
    </table>
  </div>
</div>
<div class="clearfix"></div>
@endif

{{-- Liste des incidents --}}
<table width="100%" cellpadding="0" cellspacing="0" style="margin:14px 0 6px 0;">
  <tr><td style="background-color:{{ $brandColor ?? '#0f2847' }};color:#ffffff;font-weight:bold;font-size:10px;padding:5px 10px;">Liste des incidents ({{ count($incidents) }})</td></tr>
</table>
<table class="data-table">
  <thead>
    <tr>
      <th style="width:85px;">Référence</th>
      <th style="width:60px;">Date</th>
      <th>Description</th>
      <th style="width:75px;">Lieu</th>
      <th style="width:45px;">Type</th>
      <th style="width:58px;">Gravité</th>
      <th style="width:52px;">Statut</th>
      <th style="width:75px;">Rapporté par</th>
    </tr>
  </thead>
  <tbody>
    @forelse($incidents as $inc)
    <tr class="{{ $loop->even ? 'even' : '' }}">
      <td class="bold">{{ $inc->reference }}</td>
      <td>{{ $inc->date?->format('d/m/Y') }}</td>
      <td>{{ \Illuminate\Support\Str::limit($inc->description ?? '', 75) }}</td>
      <td>{{ $inc->location }}</td>
      <td class="center" style="font-size:8px;">{{ strtoupper($inc->type ?? '') }}</td>
      <td><span class="badge b-{{ $inc->severity }}">{{ ucfirst($inc->severity ?? '') }}</span></td>
      <td><span class="badge b-{{ $inc->status }}">{{ $inc->status === 'open' ? 'Ouvert' : 'Clôturé' }}</span></td>
      <td class="muted">{{ $inc->reporter?->name ?? '—' }}</td>
    </tr>
    @empty
    <tr><td colspan="8" class="center muted" style="padding:16px">Aucun incident pour cette période</td></tr>
    @endforelse
  </tbody>
</table>

@endsection
