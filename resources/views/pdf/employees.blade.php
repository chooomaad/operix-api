@extends('pdf.layout')

@section('content')

<table class="kpi-table" style="margin-top:12px;">
  <tr>
    <td class="kpi-td">
      <div class="kpi-inner c-blue">
        <div class="kpi-val" style="color:#2563eb;">{{ $stats['total'] }}</div>
        <div class="kpi-lbl">Total employés</div>
      </div>
    </td>
    <td class="kpi-td">
      <div class="kpi-inner c-green">
        <div class="kpi-val" style="color:#16a34a;">{{ $stats['actifs'] }}</div>
        <div class="kpi-lbl">Actifs</div>
      </div>
    </td>
    <td class="kpi-td">
      <div class="kpi-inner">
        <div class="kpi-val" style="color:#64748b;">{{ $stats['inactifs'] }}</div>
        <div class="kpi-lbl">Inactifs</div>
      </div>
    </td>
    <td class="kpi-td">
      <div class="kpi-inner c-brand">
        <div class="kpi-val" style="color:{{ $brandColor ?? '#0f2847' }};">{{ $stats['cdi'] }}</div>
        <div class="kpi-lbl">CDI</div>
      </div>
    </td>
    <td class="kpi-td">
      <div class="kpi-inner c-amber">
        <div class="kpi-val" style="color:#d97706;">{{ $stats['cdd'] }}</div>
        <div class="kpi-lbl">CDD</div>
      </div>
    </td>
  </tr>
</table>

<table width="100%" cellpadding="0" cellspacing="0" style="margin:14px 0 6px 0;">
  <tr><td style="background-color:{{ $brandColor ?? '#0f2847' }};color:#ffffff;font-weight:bold;font-size:10px;padding:5px 10px;">Liste des employés ({{ count($employees) }})</td></tr>
</table>
<table class="data-table">
  <thead>
    <tr>
      <th style="width:65px;">Matricule</th>
      <th style="width:78px;">Nom</th>
      <th style="width:78px;">Prénom</th>
      <th>Poste</th>
      <th style="width:88px;">Département</th>
      <th style="width:50px;">Contrat</th>
      <th style="width:72px;">Embauche</th>
      <th style="width:48px;">Statut</th>
    </tr>
  </thead>
  <tbody>
    @forelse($employees as $emp)
    <tr class="{{ $loop->even ? 'even' : '' }}">
      <td class="bold muted">{{ $emp->matricule }}</td>
      <td class="bold">{{ $emp->nom }}</td>
      <td>{{ $emp->prenom }}</td>
      <td style="font-size:8.5px;">{{ $emp->poste }}</td>
      <td class="muted" style="font-size:8.5px;">{{ $emp->department?->name ?? '—' }}</td>
      <td class="center">{{ $emp->type_contrat }}</td>
      <td class="center">{{ $emp->date_embauche?->format('d/m/Y') ?? '—' }}</td>
      <td><span class="badge {{ $emp->is_active ? 'b-active' : 'b-inactive' }}">{{ $emp->is_active ? 'Actif' : 'Inactif' }}</span></td>
    </tr>
    @empty
    <tr><td colspan="8" class="center muted" style="padding:16px">Aucun employé trouvé</td></tr>
    @endforelse
  </tbody>
</table>

@endsection
