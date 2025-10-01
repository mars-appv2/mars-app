<!doctype html>
<html>
<head>
  <meta charset="utf-8">
  <style>
    body{ font-family: DejaVu Sans, sans-serif; font-size:12px; }
    table{ width:100%; border-collapse:collapse; }
    th,td{ border:1px solid #bbb; padding:6px; }
    th{ background:#eee; }
    .text-right{ text-align:right; }
  </style>
</head>
<body>
  <h3 style="margin:0 0 8px 0;">Neraca</h3>
  @if($asOf)<div>Per {{ $asOf }}</div>@endif
  <table>
    <thead><tr><th>Kelompok</th><th>Kode</th><th>Nama</th><th class="text-right">Saldo</th></tr></thead>
    <tbody>
      @foreach($grouped['assets'] as [$a,$bal])
        <tr><td>ASSET</td><td>{{ $a->code }}</td><td>{{ $a->name }}</td><td class="text-right">{{ number_format($bal,2) }}</td></tr>
      @endforeach
      <tr><td colspan="3"><b>TOTAL ASSET</b></td><td class="text-right"><b>{{ number_format($sum['assets'],2) }}</b></td></tr>

      @foreach($grouped['liabilities'] as [$a,$bal])
        <tr><td>LIABILITY</td><td>{{ $a->code }}</td><td>{{ $a->name }}</td><td class="text-right">{{ number_format($bal,2) }}</td></tr>
      @endforeach
      @foreach($grouped['equity'] as [$a,$bal])
        <tr><td>EQUITY</td><td>{{ $a->code }}</td><td>{{ $a->name }}</td><td class="text-right">{{ number_format($bal,2) }}</td></tr>
      @endforeach
      <tr><td>EQUITY</td><td></td><td>Laba/Rugi Berjalan</td><td class="text-right">{{ number_format($netIncome,2) }}</td></tr>
      <tr><td colspan="3"><b>TOTAL LIAB + EQUITY</b></td><td class="text-right"><b>{{ number_format($sum['liabilities'] + $sum['equity'],2) }}</b></td></tr>
    </tbody>
  </table>
</body>
</html>
