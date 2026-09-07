@extends('pdf.layout')

@section('content')

@php
  $typeLabels = [
    'vehicle' => 'Véhicule / engin', 'equipment' => 'Équipement', 'infrastructure' => 'Infrastructure',
    'cargo' => 'Marchandise', 'container' => 'Conteneur', 'other' => 'Autre',
  ];
@endphp

<table class="kpi-table" style="margin-top:12px;">
  <tr>
    <td class="kpi-td">
      <div class="kpi-inner c-blue">
        <div class="kpi-val" style="color:#2563eb;">{{ $stats['total'] }}</div>
        <div class="kpi-lbl">Total dommages</div>
      </div>
    </td>
    <td class="kpi-td">
      <div class="kpi-inner c-red">
        <div class="kpi-val" style="color:#dc2626;">{{ $stats['open'] }}</div>
        <div class="kpi-lbl">Ouverts</div>
      </div>
    </td>
    <td class="kpi-td">
      <div class="kpi-inner c-green">
        <div class="kpi-val" style="color:#16a34a;">{{ $stats['closed'] }}</div>
        <div class="kpi-lbl">Clôturés</div>
      </div>
    </td>
    <td class="kpi-td">
      <div class="kpi-inner c-amber">
        <div class="kpi-val" style="color:#d97706;">{{ number_format($stats['cost'], 0, ',', ' ') }}</div>
        <div class="kpi-lbl">Coût total estimé (MRU)</div>
      </div>
    </td>
  </tr>
</table>

<table width="100%" cellpadding="0" cellspacing="0" style="margin:14px 0 6px 0;">
  <tr><td style="background-color:{{ $brandColor ?? '#0f2847' }};color:#ffffff;font-weight:bold;font-size:10px;padding:5px 10px;">Dommages matériels ({{ count($records) }})</td></tr>
</table>
<table class="data-table">
  <thead>
    <tr>
      <th style="width:80px;">Référence</th>
      <th style="width:60px;">Date</th>
      <th style="width:90px;">Type</th>
      <th>Description</th>
      <th style="width:80px;">Lieu</th>
      <th style="width:78px;" class="right">Coût (MRU)</th>
      <th style="width:58px;">Gravité</th>
      <th style="width:52px;">Statut</th>
    </tr>
  </thead>
  <tbody>
    @forelse($records as $r)
    <tr class="{{ $loop->even ? 'even' : '' }}">
      <td class="bold">{{ $r->reference }}</td>
      <td>{{ $r->date?->format('d/m/Y') }}</td>
      <td class="muted" style="font-size:8.5px;">{{ $typeLabels[$r->type] ?? $r->type }}</td>
      <td>{{ \Illuminate\Support\Str::limit($r->description ?? '', 65) }}</td>
      <td>{{ $r->location ?? '—' }}</td>
      <td class="right">{{ $r->estimated_cost !== null ? number_format($r->estimated_cost, 0, ',', ' ') : '—' }}</td>
      <td><span class="badge b-{{ $r->severity ?? 'medium' }}">{{ ucfirst($r->severity ?? '—') }}</span></td>
      <td><span class="badge b-{{ $r->status }}">{{ $r->status === 'open' ? 'Ouvert' : 'Clôturé' }}</span></td>
    </tr>
    @empty
    <tr><td colspan="8" class="center muted" style="padding:16px">Aucun dommage matériel pour cette période</td></tr>
    @endforelse
  </tbody>
</table>

@endsection
