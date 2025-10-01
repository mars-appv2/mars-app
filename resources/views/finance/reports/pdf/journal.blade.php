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
  <h3 style="margin:0 0 8px 0;">Jurnal Umum</h3>
  @if($start || $end)
    <div>Periode: {{ $start ?: 'awal' }} s/d {{ $end ?: 'akhir' }}</div>
  @endif
  <table>
    <thead>
      <tr><th>Tanggal</th><th>Ref</th><th>Deskripsi</th><th>Akun</th><th class="text-right">Debit</th><th class="text-right">Kredit</th><th>Memo</th></tr>
    </thead>
    <tbody>
      @foreach($entries as $e)
        @foreach($e->lines as $l)
          <tr>
            <td>{{ $e->date->format('Y-m-d') }}</td>
            <td>{{ $e->ref }}</td>
            <td>{{ $e->description }}</td>
            <td>{{ optional($l->account)->code }} {{ optional($l->account)->name }}</td>
            <td class="text-right">{{ number_format($l->debit,2) }}</td>
            <td class="text-right">{{ number_format($l->credit,2) }}</td>
            <td>{{ $l->memo }}</td>
          </tr>
        @endforeach
      @endforeach
    </tbody>
  </table>
</body>
</html>
