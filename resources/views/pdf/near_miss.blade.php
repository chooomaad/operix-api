@extends('pdf.layout')

@section('content')

<table class="kpi-table" style="margin-top:12px;">
  <tr>
    <td class="kpi-td">
      <div class="kpi-inner c-blue">
        <div class="kpi-val" style="color:#2563eb;">{{ $stats['total'] }}</div>
        <div class="kpi-lbl">Total presqu'accidents</div>
      </div>
    </td>
    <td class="kpi-td">
      <div class="kpi-inner c-amber">
        <div class="kpi-val" style="color:#d97706;">{{ $stats['open'] }}</div>
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
      <div class="kpi-inner">
        <div class="kpi-val" style="color:#1a1a2e;">{{ $stats['total'] > 0 ? round($stats['closed']/$stats['total']*100) : 0 }}%</div>
        <div class="kpi-lbl">Taux de clôture</div>
      </div>
    </td>
  </tr>
</table>

<table width="100%" cellpadding="0" cellspacing="0" style="margin:14px 0 6px 0;">
  <tr><td style="background-color:{{ $brandColor ?? '#0f2847' }};color:#ffffff;font-weight:bold;font-size:10px;padding:5px 10px;">Liste des presqu'accidents ({{ count($records) }})</td></tr>
</table>
<table class="data-table">
  <thead>
    <tr>
      <th style="width:85px;">Référence</th>
      <th style="width:60px;">Date</th>
      <th>Description</th>
      <th style="width:85px;">Lieu</th>
      <th style="width:70px;">Gravité potent.</th>
      <th style="width:52px;">Statut</th>
      <th style="width:80px;">Rapporté par</th>
    </tr>
  </thead>
  <tbody>
    @forelse($records as $nm)
    <tr class="{{ $loop->even ? 'even' : '' }}">
      <td class="bold">{{ $nm->reference }}</td>
      <td>{{ $nm->date?->format('d/m/Y') }}</td>
      <td>{{ \Illuminate\Support\Str::limit($nm->description ?? '', 85) }}</td>
      <td>{{ $nm->location }}</td>
      <td><span class="badge b-{{ $nm->severity ?? 'medium' }}">{{ ucfirst($nm->severity ?? 'N/A') }}</span></td>
      <td><span class="badge b-{{ $nm->status }}">{{ $nm->status === 'open' ? 'Ouvert' : 'Clôturé' }}</span></td>
      <td class="muted">{{ $nm->reporter?->name ?? '—' }}</td>
    </tr>
    @empty
    <tr><td colspan="7" class="center muted" style="padding:16px">Aucun presqu'accident pour cette période</td></tr>
    @endforelse
  </tbody>
</table>

@endsection
