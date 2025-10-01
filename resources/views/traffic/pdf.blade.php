<!DOCTYPE html>
<html>
<head>
  <meta charset="utf-8">
  <title>Traffic Report – Device {{ $mikrotik_id }} – {{ $target }}</title>
  <style>
    body { font-family: DejaVu Sans, Arial, sans-serif; font-size: 12px; color:#111;}
    h1 { font-size: 18px; margin: 0 0 8px 0; }
    h2 { font-size: 14px; margin: 16px 0 6px 0; }
    table { width:100%; border-collapse: collapse; }
    th, td { border:1px solid #ccc; padding:6px 8px; }
    th { background:#f1f5f9; }
    .muted{ color:#555; }
  </style>
</head>
<body>
  <h1>Traffic Report</h1>
  <div class="muted">Device: {{ $mikrotik_id }} &nbsp;&nbsp; Target: {{ $target }}</div>

  @foreach($sections as $sec)
    <h2>{{ $sec['label'] }}</h2>
    <table>
      <thead>
        <tr>
          <th>Waktu</th>
          <th>RX Avg (bps)</th>
          <th>TX Avg (bps)</th>
          <th>RX Max (bps)</th>
          <th>TX Max (bps)</th>
        </tr>
      </thead>
      <tbody>
        @forelse($sec['rows'] as $r)
          <tr>
            <td>{{ $r->t }}</td>
            <td>{{ number_format($r->rx, 0, ',', '.') }}</td>
            <td>{{ number_format($r->tx, 0, ',', '.') }}</td>
            <td>{{ number_format($r->rx_max, 0, ',', '.') }}</td>
            <td>{{ number_format($r->tx_max, 0, ',', '.') }}</td>
          </tr>
        @empty
          <tr><td colspan="5">Tidak ada data.</td></tr>
        @endforelse
      </tbody>
    </table>
  @endforeach
</body>
</html>
