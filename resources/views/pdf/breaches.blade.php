@extends('pdf.layout')

@section('content')

<table class="kpi-table" style="margin-top:12px;">
  <tr>
    <td class="kpi-td">
      <div class="kpi-inner c-blue">
        <div class="kpi-val" style="color:#2563eb;">{{ $stats['total'] }}</div>
        <div class="kpi-lbl">Total infractions</div>
      </div>
    </td>
    <td class="kpi-td">
      <div class="kpi-inner c-red">
        <div class="kpi-val" style="color:#dc2626;">{{ $stats['open'] }}</div>
        <div class="kpi-lbl">Ouvertes</div>
      </div>
    </td>
    <td class="kpi-td">
      <div class="kpi-inner c-green">
        <div class="kpi-val" style="color:#16a34a;">{{ $stats['closed'] }}</div>
        <div class="kpi-lbl">Clôturées</div>
      </div>
    </td>
    <td class="kpi-td">
      <div class="kpi-inner">
        <div class="kpi-val" style="color:#1a1a2e;">{{ $stats['total'] > 0 ? round($stats['closed']/$stats['total']*100) : 0 }}%</div>
        <div class="kpi-lbl">Taux de clôture</div>
      </div>
    </td>
  </tr>
</table>

<table width="100%" cellpadding="0" cellspacing="0" style="margin:14px 0 6px 0;">
  <tr><td style="background-color:{{ $brandColor ?? '#0f2847' }};color:#ffffff;font-weight:bold;font-size:10px;padding:5px 10px;">Infractions / Manquements ({{ count($records) }})</td></tr>
</table>
<table class="data-table">
  <thead>
    <tr>
      <th style="width:80px;">Référence</th>
      <th style="width:60px;">Date</th>
      <th style="width:95px;">Employé</th>
      <th style="width:65px;">Type</th>
      <th>Description</th>
      <th style="width:58px;">Gravité</th>
      <th style="width:75px;">Sanction</th>
      <th style="width:52px;">Statut</th>
    </tr>
  </thead>
  <tbody>
    @forelse($records as $b)
    <tr class="{{ $loop->even ? 'even' : '' }}">
      <td class="bold">{{ $b->reference }}</td>
      <td>{{ $b->date?->format('d/m/Y') }}</td>
      <td>{{ $b->employee?->full_name ?? '—' }}</td>
      <td class="muted" style="font-size:8.5px;">{{ $b->type ?? '—' }}</td>
      <td>{{ \Illuminate\Support\Str::limit($b->description ?? '', 70) }}</td>
      <td><span class="badge b-{{ $b->severity ?? 'medium' }}">{{ ucfirst($b->severity ?? '—') }}</span></td>
      <td class="muted" style="font-size:8.5px;">{{ $b->sanction ?? '—' }}</td>
      <td><span class="badge b-{{ $b->status }}">{{ $b->status === 'open' ? 'Ouvert' : 'Clôturé' }}</span></td>
    </tr>
    @empty
    <tr><td colspan="8" class="center muted" style="padding:16px">Aucune infraction pour cette période</td></tr>
    @endforelse
  </tbody>
</table>

@endsection
