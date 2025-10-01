<html><head><meta charset="utf-8"><style>
body{font-family:DejaVu Sans, sans-serif; font-size:12px}
.table{width:100%; border-collapse:collapse}
.table td,.table th{border:1px solid #999; padding:6px}
.h{font-size:18px; font-weight:bold}
</style></head><body>
<div class="h">INVOICE {{ $size==='small' ? '(A6)' : '(A4)' }}</div>
<p>No: {{ $invoice->number }}<br>
Customer: {{ $invoice->customer_name }} ({{ $invoice->customer_type }})<br>
Total: Rp {{ number_format($invoice->total,0,',','.') }}<br>
Due: {{ optional($invoice->due_date)->format('Y-m-d') }}</p>
<table class="table"><tr><th>Deskripsi</th><th>Jumlah</th></tr>
<tr><td>Layanan Internet</td><td>Rp {{ number_format($invoice->total,0,',','.') }}</td></tr>
</table>
<p>Terima kasih.</p>
</body></html>
