<!doctype html>
<html>
<head>
  <meta charset="utf-8">
  <title>{{ $inv->number ?? 'INVOICE' }}</title>
  <style>
    body{font-family:Inter,ui-sans-serif,system-ui,Segoe UI,Roboto,Helvetica,Arial;background:#0f1220;color:#e6e8f0;margin:0}
    .wrap{max-width:840px;margin:40px auto;background:#14182b;border-radius:14px;padding:28px;box-shadow:0 10px 30px rgba(0,0,0,.35)}
    .row{display:flex;justify-content:space-between;gap:16px}
    .muted{color:#9aa3b2}
    .mt-2{margin-top:8px}.mt-4{margin-top:16px}.mt-6{margin-top:24px}
    .badge{display:inline-block;padding:4px 10px;border-radius:9999px;background:#1f233a;color:#cbd5e1;font-size:12px}
    table{width:100%;border-collapse:collapse}
    th,td{padding:10px 12px;border-bottom:1px solid #232745}
    th{color:#9aa3b2;text-align:left;font-weight:600}
    .total{font-size:18px;font-weight:700}
    .right{text-align:right}
    .btn{display:inline-block;padding:8px 14px;border-radius:10px;background:#4754ff;color:#fff;text-decoration:none}
    @media print {.btn{display:none} .wrap{box-shadow:none;background:#fff;color:#111} body{background:#fff}}
  </style>
</head>
<body>
<div class="wrap">
  <div class="row">
    <div class="flex">
      <img src="{{ $company['logo'] }}" alt="logo" style="height:42px;border-radius:8px">
      <div class="mt-2">
        <div style="font-weight:700;font-size:18px">{{ $company['name'] }}</div>
        <div class="muted">{{ $company['address'] }}</div>
        <div class="muted">{{ $company['phone'] }}</div>
      </div>
    </div>
    <div class="right">
      <div style="font-weight:700;font-size:20px">INVOICE</div>
      <div class="muted">No: {{ $inv->number ?? $inv->id }}</div>
      <div class="muted">Periode: {{ $inv->period ?? '-' }}</div>
      <div class="muted">Jatuh tempo: {{ $inv->due_date ?? '-' }}</div>
      <span class="badge">{{ $inv->status }}</span>
    </div>
  </div>

  <div class="mt-6 row">
    <div>
      <div class="muted">Tagihan untuk</div>
      <div style="font-weight:600">{{ $sub->username ?? '-' }}</div>
      @if(!empty($inv->customer_name) && ($inv->customer_name!==$sub->username))
        <div>{{ $inv->customer_name }}</div>
      @endif
    </div>
    <div class="right">
      <div class="muted">Paket</div>
      <div style="font-weight:600">{{ $plan->name ?? ($inv->plan_name ?? '-') }}</div>
    </div>
  </div>

  <div class="mt-4">
    <table>
      <thead>
        <tr>
          <th>Deskripsi</th>
          <th class="right">Nominal</th>
        </tr>
      </thead>
      <tbody>
        <tr>
          <td>Berlangganan {{ $plan->name ?? ($inv->plan_name ?? 'Internet') }} ({{ $inv->period ?? '-' }})</td>
          <td class="right">Rp {{ number_format((int)($inv->amount ?? 0),0,',','.') }}</td>
        </tr>
        @if(isset($inv->tax))
        <tr>
          <td>Pajak</td>
          <td class="right">Rp {{ number_format((int)$inv->tax,0,',','.') }}</td>
        </tr>
        @endif
        @if(isset($inv->discount) && (int)$inv->discount>0)
        <tr>
          <td>Diskon</td>
          <td class="right">- Rp {{ number_format((int)$inv->discount,0,',','.') }}</td>
        </tr>
        @endif
        <tr>
          <td class="total">TOTAL</td>
          <td class="right total">
            Rp {{
              number_format(
                (int)($inv->total ?? (($inv->amount ?? 0) + ($inv->tax ?? 0) - ($inv->discount ?? 0))),
                0,',','.'
              )
            }}
          </td>
        </tr>
      </tbody>
    </table>
  </div>

  <div class="mt-6 muted">Terima kasih. Silakan lakukan pembayaran sebelum tanggal jatuh tempo.</div>
  <div class="mt-4"><a class="btn" href="javascript:window.print()">Print</a></div>
</div>
</body>
</html>
