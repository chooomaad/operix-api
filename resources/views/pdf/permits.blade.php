@extends('pdf.layout')

@section('content')
<div class="kpi-grid">
  <div class="kpi-cell"><div class="kpi-card"><div class="kpi-value">{{ $stats['total'] }}</div><div class="kpi-label">Total permis</div></div></div>
  <div class="kpi-cell"><div class="kpi-card"><div class="kpi-value" style="color:#2563eb">{{ $stats['pending'] }}</div><div class="kpi-label">En attente</div></div></div>
  <div class="kpi-cell"><div class="kpi-card"><div class="kpi-value" style="color:#16a34a">{{ $stats['approved'] }}</div><div class="kpi-label">Approuvés</div></div></div>
  <div class="kpi-cell"><div class="kpi-card"><div class="kpi-value" style="color:#dc2626">{{ $stats['closed'] }}</div><div class="kpi-label">Clôturés</div></div></div>
</div>

<div class="section-title">Permis de travail ({{ count($records) }})</div>
<table>
  <thead>
    <tr>
      <th>Référence</th>
      <th>Titre</th>
      <th>Type</th>
      <th>Lieu</th>
      <th>Prestataire</th>
      <th>Du</th>
      <th>Au</th>
      <th>Statut</th>
    </tr>
  </thead>
  <tbody>
    @forelse($records as $p)
    <tr>
      <td><strong>{{ $p->reference }}</strong></td>
      <td>{{ \Illuminate\Support\Str::limit($p->title, 50) }}</td>
      <td>{{ str_replace('_', ' ', strtoupper($p->type)) }}</td>
      <td>{{ $p->location }}</td>
      <td>{{ $p->contractor?->name ?? $p->contractor_name ?? '-' }}</td>
      <td>{{ $p->valid_from?->format('d/m/Y') }}</td>
      <td>{{ $p->valid_to?->format('d/m/Y') }}</td>
      <td><span class="badge badge-{{ $p->status }}">{{ ucfirst($p->status) }}</span></td>
    </tr>
    @empty
    <tr><td colspan="8" style="text-align:center;color:#9ca3af">Aucun permis trouvé</td></tr>
    @endforelse
  </tbody>
</table>
@endsection
