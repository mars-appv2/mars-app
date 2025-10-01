@extends('layouts.app')
@section('title','Traffic — Detail')
@section('content')
@if(session('ok'))<div class="text-green-300 mb-3">{{session('ok')}}</div>@endif
@if(session('err'))<div class="text-rose-300 mb-3">{{session('err')}}</div>@endif

<a href="{{ url()->previous() }}" class="m-btn m-btn-outline mb-4">← Kembali</a>

<div class="card p-4">
  <div class="flex items-center justify-between mb-3">
    <div>
      <div class="text-xs text-[var(--muted)] uppercase tracking-wider">{{ strtoupper($row->target_type) }}</div>
      <div class="text-xl font-semibold">{{ $label }}</div>
    </div>
    @if($row->target_type==='content')
      <form method="POST" action="{{ route('traffic.graphs.ping', $row->id) }}">@csrf
        <button class="m-btn m-btn-outline">Update sekarang</button>
      </form>
    @else
      <form method="POST" action="{{ route('traffic.graphs.poll', $row->id) }}">@csrf
        <button class="m-btn m-btn-outline">Update sekarang</button>
      </form>
    @endif
  </div>

  <div class="mb-3">
    <label class="inline-flex items-center gap-2">
      <input type="checkbox" id="auto" checked>
      <span>Auto-refresh tiap 10 detik</span>
    </label>
  </div>

  <div class="grid gap-4">
    @foreach(['day'=>'Daily (5-min avg)','week'=>'Weekly (30-min avg)','month'=>'Monthly (2-hour avg)','year'=>'Yearly (1-day avg)'] as $p=>$cap)
      <div class="border border-[var(--line)] rounded-xl p-3">
        <div class="text-[var(--muted)] mb-2">{{ $cap }}</div>
        @if($png[$p])
          <img id="img-{{ $p }}" src="{{ route('traffic.graphs.png', [$group,$key,$p]) }}" class="w-full rounded">
        @else
          <div class="text-slate-400">Belum ada PNG ({{ $p }}). Klik “Update sekarang”.</div>
        @endif
      </div>
    @endforeach
  </div>
</div>

@push('scripts')
<script>
(function(){
  function bust(id){
    var el=document.getElementById('img-'+id); if(!el) return;
    var src=el.getAttribute('src').split('?')[0]; el.setAttribute('src',src+'?t='+(new Date().getTime()));
  }
  var on=true, chk=document.getElementById('auto');
  if(chk){ chk.addEventListener('change', function(){ on=chk.checked; }); }
  setInterval(function(){ if(on){ ['day','week','month','year'].forEach(bust); }}, 10000);
})();
</script>
@endpush
@endsection
