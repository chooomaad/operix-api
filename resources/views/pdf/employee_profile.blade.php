@extends('pdf.layout')

@section('content')
<div class="section-title">Informations personnelles</div>
<div class="info-grid">
  <div class="info-row"><div class="info-label">Matricule</div><div class="info-value"><strong>{{ $employee->matricule }}</strong></div></div>
  <div class="info-row"><div class="info-label">Nom complet</div><div class="info-value">{{ $employee->prenom }} {{ $employee->nom }}</div></div>
  <div class="info-row"><div class="info-label">Poste</div><div class="info-value">{{ $employee->poste }}</div></div>
  <div class="info-row"><div class="info-label">Département</div><div class="info-value">{{ $employee->department?->name ?? '-' }}</div></div>
  <div class="info-row"><div class="info-label">Type contrat</div><div class="info-value">{{ $employee->type_contrat }}</div></div>
  <div class="info-row"><div class="info-label">Date embauche</div><div class="info-value">{{ $employee->date_embauche?->format('d/m/Y') ?? '-' }}</div></div>
  <div class="info-row"><div class="info-label">Email</div><div class="info-value">{{ $employee->email ?? '-' }}</div></div>
  <div class="info-row"><div class="info-label">Téléphone</div><div class="info-value">{{ $employee->phone ?? '-' }}</div></div>
  <div class="info-row"><div class="info-label">Nationalité</div><div class="info-value">{{ $employee->nationalite ?? '-' }}</div></div>
  <div class="info-row"><div class="info-label">Statut</div><div class="info-value"><span class="badge {{ $employee->is_active ? 'badge-active' : 'badge-inactive' }}">{{ $employee->is_active ? 'Actif' : 'Inactif' }}</span></div></div>
</div>

@if($formations->count())
<div class="section-title">Formations ({{ $formations->count() }})</div>
<table>
  <thead><tr><th>Titre</th><th>Organisme</th><th>Début</th><th>Fin</th><th>Statut</th></tr></thead>
  <tbody>
    @foreach($formations as $f)
    <tr>
      <td>{{ $f->titre }}</td>
      <td>{{ $f->organisme ?? '-' }}</td>
      <td>{{ $f->date_debut?->format('d/m/Y') }}</td>
      <td>{{ $f->date_fin?->format('d/m/Y') ?? '-' }}</td>
      <td>{{ $f->statut }}</td>
    </tr>
    @endforeach
  </tbody>
</table>
@endif

@if($certifications->count())
<div class="section-title">Certifications ({{ $certifications->count() }})</div>
<table>
  <thead><tr><th>Titre</th><th>Organisme</th><th>N°</th><th>Obtention</th><th>Expiration</th><th>Statut</th></tr></thead>
  <tbody>
    @foreach($certifications as $c)
    <tr>
      <td>{{ $c->titre }}</td>
      <td>{{ $c->organisme ?? '-' }}</td>
      <td>{{ $c->numero ?? '-' }}</td>
      <td>{{ $c->date_obtention?->format('d/m/Y') }}</td>
      <td>{{ $c->date_expiration?->format('d/m/Y') ?? '-' }}</td>
      <td><span class="badge {{ $c->is_expired ? 'badge-expired' : 'badge-valid' }}">{{ $c->is_expired ? 'Expiré' : 'Valide' }}</span></td>
    </tr>
    @endforeach
  </tbody>
</table>
@endif

@if($medicalVisits->count())
<div class="section-title">Visites médicales ({{ $medicalVisits->count() }})</div>
<table>
  <thead><tr><th>Date</th><th>Type</th><th>Médecin</th><th>Résultat</th><th>Prochaine visite</th></tr></thead>
  <tbody>
    @foreach($medicalVisits as $v)
    <tr>
      <td>{{ $v->date?->format('d/m/Y') }}</td>
      <td>{{ $v->type }}</td>
      <td>{{ $v->medecin ?? '-' }}</td>
      <td>{{ $v->resultat ?? '-' }}</td>
      <td>{{ $v->prochaine_visite?->format('d/m/Y') ?? '-' }}</td>
    </tr>
    @endforeach
  </tbody>
</table>
@endif
@endsection
