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

@php
  $limit = fn ($s) => \Illuminate\Support\Str::limit($s ?? '', 55);
  $d     = fn ($x) => $x ? \Illuminate\Support\Carbon::parse($x)->format('d/m/Y') : '-';
@endphp

<div class="section-title">Historique HSSE
  &nbsp;— Incidents ({{ $incidents->count() }}), Near Miss ({{ $nearMiss->count() }}),
  Breach of Process ({{ $breaches->count() }}), Environnement ({{ $environment->count() }})
</div>

<div class="hsse-sub">Incidents ({{ $incidents->count() }})</div>
@if($incidents->count())
<table class="data-table">
  <thead><tr><th>Réf.</th><th>Type</th><th>Date</th><th>Heure</th><th>Lieu</th><th>Gravité</th><th>Statut</th><th>Description</th></tr></thead>
  <tbody>
    @foreach($incidents as $i)
    <tr>
      <td><strong>{{ $i->reference }}</strong></td>
      <td>{{ $i->type ?? '-' }}</td>
      <td>{{ $d($i->date) }}</td>
      <td>{{ $i->time ?? '-' }}</td>
      <td>{{ $i->location ?? '-' }}</td>
      <td>{{ strtoupper($i->severity ?? '-') }}</td>
      <td>{{ strtoupper($i->status ?? '-') }}</td>
      <td>{{ $limit($i->description) }}</td>
    </tr>
    @endforeach
  </tbody>
</table>
@else<div class="hsse-empty">Aucun incident.</div>@endif

<div class="hsse-sub">Near Miss ({{ $nearMiss->count() }})</div>
@if($nearMiss->count())
<table class="data-table">
  <thead><tr><th>Réf.</th><th>Date</th><th>Heure</th><th>Lieu</th><th>Gravité</th><th>Statut</th><th>Description</th></tr></thead>
  <tbody>
    @foreach($nearMiss as $n)
    <tr>
      <td><strong>{{ $n->reference }}</strong></td>
      <td>{{ $d($n->date) }}</td>
      <td>{{ $n->time ?? '-' }}</td>
      <td>{{ $n->location ?? '-' }}</td>
      <td>{{ strtoupper($n->severity ?? '-') }}</td>
      <td>{{ strtoupper($n->status ?? '-') }}</td>
      <td>{{ $limit($n->description) }}</td>
    </tr>
    @endforeach
  </tbody>
</table>
@else<div class="hsse-empty">Aucun near miss.</div>@endif

<div class="hsse-sub">Breach of Process ({{ $breaches->count() }})</div>
@if($breaches->count())
<table class="data-table">
  <thead><tr><th>Réf.</th><th>Type</th><th>Date</th><th>Lieu</th><th>Gravité</th><th>Statut</th><th>Description</th></tr></thead>
  <tbody>
    @foreach($breaches as $b)
    <tr>
      <td><strong>{{ $b->reference }}</strong></td>
      <td>{{ $b->type ?? '-' }}</td>
      <td>{{ $d($b->date) }}</td>
      <td>{{ $b->location ?? '-' }}</td>
      <td>{{ strtoupper($b->severity ?? '-') }}</td>
      <td>{{ strtoupper($b->status ?? '-') }}</td>
      <td>{{ $limit($b->description) }}</td>
    </tr>
    @endforeach
  </tbody>
</table>
@else<div class="hsse-empty">Aucun breach of process.</div>@endif

<div class="hsse-sub">Environnement ({{ $environment->count() }})</div>
@if($environment->count())
<table class="data-table">
  <thead><tr><th>Réf.</th><th>Type</th><th>Date</th><th>Lieu</th><th>Gravité</th><th>Statut</th><th>Description</th></tr></thead>
  <tbody>
    @foreach($environment as $e)
    <tr>
      <td><strong>{{ $e->reference }}</strong></td>
      <td>{{ $e->type ?? '-' }}</td>
      <td>{{ $d($e->date) }}</td>
      <td>{{ $e->location ?? '-' }}</td>
      <td>{{ strtoupper($e->severity ?? '-') }}</td>
      <td>{{ strtoupper($e->status ?? '-') }}</td>
      <td>{{ $limit($e->description) }}</td>
    </tr>
    @endforeach
  </tbody>
</table>
@else<div class="hsse-empty">Aucun événement environnemental.</div>@endif

@if($formations->count())
<div class="section-title">Formations ({{ $formations->count() }})</div>
<table class="data-table">
  <thead><tr><th>Titre</th><th>Organisme</th><th>Début</th><th>Fin</th><th>Statut</th><th>Justificatif</th></tr></thead>
  <tbody>
    @foreach($formations as $f)
    <tr>
      <td>{{ $f->titre }}</td>
      <td>{{ $f->organisme ?? '-' }}</td>
      <td>{{ $f->date_debut?->format('d/m/Y') }}</td>
      <td>{{ $f->date_fin?->format('d/m/Y') ?? '-' }}</td>
      <td>{{ $f->statut }}</td>
      <td>@if($f->img_data)<img src="{{ $f->img_data }}" style="max-height:40px; max-width:80px;">@else-@endif</td>
    </tr>
    @endforeach
  </tbody>
</table>
@endif

@if($certifications->count())
<div class="section-title">Certifications ({{ $certifications->count() }})</div>
<table class="data-table">
  <thead><tr><th>Titre</th><th>Organisme</th><th>N°</th><th>Obtention</th><th>Expiration</th><th>Statut</th><th>Justificatif</th></tr></thead>
  <tbody>
    @foreach($certifications as $c)
    <tr>
      <td>{{ $c->titre }}</td>
      <td>{{ $c->organisme ?? '-' }}</td>
      <td>{{ $c->numero ?? '-' }}</td>
      <td>{{ $c->date_obtention?->format('d/m/Y') }}</td>
      <td>{{ $c->date_expiration?->format('d/m/Y') ?? '-' }}</td>
      <td><span class="badge {{ $c->is_expired ? 'badge-expired' : 'badge-valid' }}">{{ $c->is_expired ? 'Expiré' : 'Valide' }}</span></td>
      <td>@if($c->img_data)<img src="{{ $c->img_data }}" style="max-height:40px; max-width:80px;">@else-@endif</td>
    </tr>
    @endforeach
  </tbody>
</table>
@endif

@if($medicalVisits->count())
<div class="section-title">Visites médicales ({{ $medicalVisits->count() }})</div>
<table class="data-table">
  <thead><tr><th>Date</th><th>Type</th><th>Médecin</th><th>Résultat</th><th>Prochaine visite</th><th>Justificatif</th></tr></thead>
  <tbody>
    @foreach($medicalVisits as $v)
    <tr>
      <td>{{ $v->date?->format('d/m/Y') }}</td>
      <td>{{ $v->type }}</td>
      <td>{{ $v->medecin ?? '-' }}</td>
      <td>{{ $v->resultat ?? '-' }}</td>
      <td>{{ $v->prochaine_visite?->format('d/m/Y') ?? '-' }}</td>
      <td>@if($v->img_data)<img src="{{ $v->img_data }}" style="max-height:40px; max-width:80px;">@else-@endif</td>
    </tr>
    @endforeach
  </tbody>
</table>
@endif
@endsection
