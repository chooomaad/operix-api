<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
<title>{{ $title ?? 'Rapport Operix HSSE' }}</title>
<style>
* { margin:0; padding:0; box-sizing:border-box; }
body {
  font-family: DejaVu Sans, Arial, sans-serif;
  font-size: 10px;
  color: #1a1a2e;
  background: #fff;
}

/* ── Bande grise sous le header ── */
.subband {
  background-color: #edf2f7;
  border-bottom: 1px solid #c8d4e0;
  padding: 5px 18px;
  font-size: 9px;
  color: #4a5568;
}
.subband-table { width:100%; border-collapse:collapse; }
.band-chip {
  background-color: {{ $brandColor ?? '#0f2847' }};
  color: #ffffff;
  font-size: 8px;
  font-weight: bold;
  padding: 2px 8px;
  border-radius: 10px;
  display: inline-block;
}

/* ── KPI Cards ── */
.kpi-section { padding: 14px 18px 6px 18px; }
.kpi-table {
  width: 100%;
  border-collapse: collapse;
  border-spacing: 0;
  margin-bottom: 4px;
}
.kpi-td {
  padding: 0 4px;
  vertical-align: top;
}
.kpi-inner {
  background-color: #f7fafc;
  border: 1px solid #dde4ed;
  border-top-width: 3px;
  border-top-color: #a0aec0;
  border-radius: 4px;
  padding: 10px 6px 8px 6px;
  text-align: center;
}
.kpi-inner.c-blue  { border-top-color: #2563eb; }
.kpi-inner.c-red   { border-top-color: #dc2626; }
.kpi-inner.c-green { border-top-color: #16a34a; }
.kpi-inner.c-amber { border-top-color: #d97706; }
.kpi-inner.c-brand { border-top-color: {{ $brandColor ?? '#0f2847' }}; }

/* Couleurs des valeurs KPI — inline dans les templates pour fiabilité DomPDF */
.kpi-val { font-size: 22px; font-weight: bold; line-height: 1.1; }
.kpi-lbl { font-size: 8.5px; color: #718096; margin-top: 4px; line-height: 1.3; }

/* ── Contenu ── */
.content { padding: 4px 18px 40px 18px; }

/* ── Section title ── */
.section-title {
  font-size: 10px;
  font-weight: bold;
  color: #ffffff;
  background-color: {{ $brandColor ?? '#0f2847' }};
  padding: 5px 10px;
  margin: 14px 0 6px 0;
}

/* ── Table d'informations (fiches profil) ── */
.info-table { width: 100%; border-collapse: collapse; margin-bottom: 14px; font-size: 9.5px; }
.info-table td { padding: 6px 9px; border: 1px solid #e5e9f0; vertical-align: middle; }
.info-table .lbl {
  background-color: #f4f6f9; color: #64748b; font-weight: bold; width: 16%;
  text-transform: uppercase; font-size: 7.5px; letter-spacing: .4px; white-space: nowrap;
}
.info-table .val { color: #1e293b; font-weight: 600; }

/* ── Sous-titres & vides (historique HSSE) ── */
.hsse-sub { font-size: 9px; font-weight: bold; color: {{ $brandColor ?? '#0f2847' }}; margin: 10px 0 3px 0; }
.hsse-empty { font-size: 8.5px; color: #a0aec0; margin: 0 0 8px 0; font-style: italic; }

/* ── Tables de données ── */
.data-table { width: 100%; border-collapse: collapse; margin-bottom: 12px; font-size: 9px; }
.data-table th {
  background-color: {{ $brandColor ?? '#0f2847' }};
  color: #ffffff;
  padding: 6px 7px;
  text-align: left;
  font-size: 8.5px;
  font-weight: bold;
}
.data-table td {
  padding: 5px 7px;
  border-bottom: 1px solid #edf0f4;
  vertical-align: top;
  color: #2d3748;
}
.data-table .even { background-color: #f7fafc; }
.center { text-align: center; }
.right  { text-align: right; }
.bold   { font-weight: bold; }
.muted  { color: #a0aec0; }

/* ── Badges ── */
.badge { display: inline-block; padding: 2px 7px; border-radius: 10px; font-size: 8px; font-weight: bold; }
.b-open       { background-color: #fef3c7; color: #92400e; }
.b-closed     { background-color: #d1fae5; color: #065f46; }
.b-resolved   { background-color: #d1fae5; color: #065f46; }
.b-pending    { background-color: #e0e7ff; color: #3730a3; }
.b-critical   { background-color: #fecaca; color: #991b1b; }
.b-high       { background-color: #fed7aa; color: #9a3412; }
.b-medium     { background-color: #fef3c7; color: #92400e; }
.b-low        { background-color: #d1fae5; color: #166534; }
.b-active     { background-color: #d1fae5; color: #065f46; }
.b-inactive   { background-color: #f1f5f9; color: #475569; }
.b-suspended  { background-color: #fef3c7; color: #92400e; }
.b-expired    { background-color: #fecaca; color: #991b1b; }
.b-valid      { background-color: #d1fae5; color: #065f46; }
.b-in_progress { background-color: #fef3c7; color: #92400e; }
.b-approved   { background-color: #d1fae5; color: #065f46; }

/* ── Deux colonnes ── */
.col-left  { float: left;  width: 49%; }
.col-right { float: right; width: 49%; }
.clearfix  { clear: both; }

/* ── Safety tracker ── */
.tracker-box {
  background-color: #f0fdf4;
  border: 1px solid #86efac;
  border-left: 5px solid #16a34a;
  padding: 10px 14px;
  margin-bottom: 12px;
}

/* ── Footer fixe ── */
.page-footer {
  position: fixed;
  bottom: 0; left: 0; right: 0;
  height: 26px;
  background-color: {{ $brandColor ?? '#0f2847' }};
  padding: 0 18px;
}
.footer-tbl { width:100%; border-collapse:collapse; height:26px; }
.footer-tbl td {
  vertical-align: middle;
  font-size: 8px;
  color: #a0c0e0;
}

.page-break { page-break-after: always; }
</style>
</head>
<body>

{{-- ══ HEADER — table avec bgcolor pour garantir le rendu DomPDF ══ --}}
<table width="100%" cellpadding="0" cellspacing="0" style="background-color:{{ $brandColor ?? '#0f2847' }};margin-bottom:0;">
  <tr>
    {{-- Logo --}}
    <td width="80" style="padding:10px 12px 10px 18px;vertical-align:middle;">
      @if(!empty($orgLogo))
        <img src="{{ $orgLogo }}" alt="{{ $orgShort ?? 'TCN' }}"
             style="width:56px;height:56px;display:block;object-fit:contain;" />
      @else
        <div style="width:56px;height:56px;background-color:rgba(255,255,255,0.15);
                    border-radius:6px;text-align:center;line-height:56px;
                    font-size:20px;font-weight:bold;color:#ffffff;">
          {{ substr($orgShort ?? 'T', 0, 1) }}
        </div>
      @endif
    </td>
    {{-- Titre --}}
    <td style="padding:10px 0;vertical-align:middle;">
      <div style="font-size:9px;color:#a8c0d6;letter-spacing:1.5px;text-transform:uppercase;">
        {{ $orgName ?? 'Terminal à Conteneurs de Nouakchott' }}
      </div>
      <div style="font-size:17px;font-weight:bold;color:#ffffff;margin-top:3px;line-height:1.2;">
        {{ $title ?? 'Rapport' }}
      </div>
    </td>
    {{-- Méta droite --}}
    <td width="170" style="padding:10px 18px 10px 0;vertical-align:middle;text-align:right;">
      <div style="display:inline-block;border:1px solid #7899b5;
                  padding:2px 8px;border-radius:2px;font-size:8px;
                  font-weight:bold;color:#d0e2ef;letter-spacing:1px;">
        CONFIDENTIEL
      </div>
      <div style="font-size:8px;color:#8fafc5;margin-top:6px;">
        {{ now()->format('d/m/Y à H:i') }}
      </div>
      <div style="font-size:8px;color:#7696ac;margin-top:2px;">
        Operix HSSE Platform
      </div>
    </td>
  </tr>
</table>

{{-- Bande inférieure du header --}}
<div class="subband">
  <table class="subband-table">
    <tr>
      <td>
        <span class="band-chip">HSSE</span>&nbsp;
        @if(!empty($period))Période : <strong>{{ $period }}</strong>@endif
      </td>
      <td style="text-align:right;">{{ $orgShort ?? 'TCN' }} &mdash; Direction HSSE</td>
    </tr>
  </table>
</div>

{{-- ══ CONTENU ══ --}}
<div class="content">
  @yield('content')
</div>

{{-- ══ FOOTER ══ --}}
<div class="page-footer">
  <table class="footer-tbl">
    <tr>
      <td style="color:#a0c0e0;">{{ $orgName ?? 'TCN' }} — Operix HSSE</td>
      <td style="text-align:center;color:#7a9cbe;">Document confidentiel — usage interne</td>
      <td style="text-align:right;color:#a0c0e0;">Page <span class="page"></span></td>
    </tr>
  </table>
</div>

</body>
</html>
