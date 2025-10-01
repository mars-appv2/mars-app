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
  <h3 style="margin:0 0 8px 0;">Neraca Percobaan</h3>
  @if($start || $end)
    <div>Periode: {{ $start ?: 'awal' }} s/d {{ $end ?: 'akhir' }}</div>
  @endif
  <table>
    <thead><tr><th>Kode</th><th>Nama Akun</th><th class="text-right">Debit</th><th class="text-right">Kredit</th></tr></thead>
    <tbody>
      @foreach($rows as $r)
        <tr>
          <td>{{ $r['code'] }}</td>
          <td>{{ $r['name'] }}</td>
          <td class="text-right">{{ number_format($r['debit'],2) }}</td>
          <td class="text-right">{{ number_format($r['credit'],2) }}</td>
        </tr>
      @endforeach
      <tr>
        <td colspan="2"><b>TOTAL</b></td>
        <td class="text-right"><b>{{ number_format($TD,2) }}</b></td>
        <td class="text-right"><b>{{ number_format($TC,2) }}</b></td>
      </tr>
    </tbody>
  </table>
</body>
</html>
