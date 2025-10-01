@php $mk = $mikrotik ?? null; @endphp
@if($mk)
  <div class="mk-tabs">
    <a class="mk-tab" href="{{ route('mikrotik.index') }}">Table List</a>
    <a class="mk-tab {{ request()->routeIs('mikrotik.dashboard') ? 'active' : '' }}"
       href="{{ route('mikrotik.dashboard',['mikrotik'=>$mk->id]) }}">Dashboard</a>
    <a class="mk-tab {{ request()->routeIs('mikrotik.pppoe') ? 'active' : '' }}"
       href="{{ route('mikrotik.pppoe',['mikrotik'=>$mk->id]) }}">PPPoE</a>
    <a class="mk-tab {{ request()->routeIs('mikrotik.ipstatic') ? 'active' : '' }}"
       href="{{ route('mikrotik.ipstatic',['mikrotik'=>$mk->id]) }}">IP Static</a>
  </div>
  <style>
    .mk-tabs{display:flex;gap:.5rem;margin:.25rem 0 1rem}
    .mk-tab{background:#0b1020;border:1px solid #243154;border-radius:.6rem;
            padding:.35rem .7rem;color:#cfe3ff;text-decoration:none;font-weight:600}
    .mk-tab.active{background:#4da3ff;color:#001a33;border-color:#4da3ff}
  </style>
@endif
