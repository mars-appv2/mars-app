@extends('layouts.app')
@section('content')
@php
  // Helper aman untuk konversi value ke string (hindari htmlspecialchars() error)
  $toStr = function($v, $prefer=null){
    if (is_string($v)) return $v;
    if (is_numeric($v)) return (string)$v;
    if (is_array($v)) {
      if ($prefer && isset($v[$prefer])) return (string)$v[$prefer];
      foreach (['name','profile','value','.id','0'] as $k) {
        if (isset($v[$k]) && (is_string($v[$k]) || is_numeric($v[$k]))) return (string)$v[$k];
      }
      $first = reset($v);
      return is_string($first)||is_numeric($first) ? (string)$first : '';
    }
    if (is_object($v)) {
      foreach (['name','profile','value'] as $k) {
        if (isset($v->$k) && (is_string($v->$k) || is_numeric($v->$k))) return (string)$v->$k;
      }
      return method_exists($v,'__toString') ? (string)$v : '';
    }
    return '';
  };

  // Set aktif
  $activeNames = [];
  foreach (($active ?? []) as $a) { $activeNames[] = $toStr($a, 'name'); }
  $activeSet = array_flip(array_filter($activeNames));

  // Kumpulan profil (string) -> ambil dari $profiles bila ada, kalau tidak ambil unik dari $secrets
  $profileList = [];
  if (!empty($profiles) && is_iterable($profiles)) {
    foreach ($profiles as $p) $profileList[] = $toStr($p);
  } else {
    if (!empty($secrets) && is_iterable($secrets)) {
      foreach ($secrets as $u) {
        $pr = $toStr(is_array($u)?($u['profile']??'') : ($u->profile??''));
        if ($pr) $profileList[] = $pr;
      }
    }
  }
  $profileList = array_values(array_unique(array_filter($profileList)));
  if (!in_array('default', $profileList, true)) array_unshift($profileList, 'default');

  // Hitung total
  $total = is_iterable($secrets ?? []) ? iterator_count((function($it){foreach($it as $_) yield 1;})($secrets)) : (is_array($secrets ?? null) ? count($secrets) : 0);
  $activeCount = count($activeSet);
  $inactiveCount = max(0, $total - $activeCount);
@endphp

<style>
  .badge{font-size:.72rem;padding:.2rem .45rem;border-radius:.4rem;border:1px solid #2a3350;background:rgba(99,102,241,.15);color:#cbd5e1}
  .m-card{background:#121827;border:1px solid #2a3350;border-radius:14px}
  .m-muted{color:#95a3bf}
  .m-btn{background:#1e293b;color:#fff;border:1px solid #334155;border-radius:10px;padding:.55rem 1rem;transition:.15s}
  .m-btn:hover{filter:brightness(1.12);transform:translateY(-1px)}
  .m-btnp{background:#3b82f6;border-color:#3b82f6}
  .m-inp,.m-sel{background:#0c101c;border:1px solid #2a3350;border-radius:10px;color:#e6eaf2;padding:.55rem .75rem}
  .m-sel{padding-right:2.2rem;appearance:none}
  .m-selwrap{position:relative}
  .m-selwrap:after{content:'▾';position:absolute;right:.65rem;top:50%;transform:translateY(-50%);color:#95a3bf}
  .small{font-size:.85rem;color:#95a3bf}
  .tbl th,.tbl td{padding:.7rem .8rem;border-bottom:1px solid #1f2942}
  .pill{font-size:.75rem;border-radius:9999px;padding:.15rem .5rem}
  .pill-on{background:#064e3b;color:#bbf7d0}
  .pill-off{background:#3f1d1d;color:#fecaca}
</style>

<div class="container mx-auto px-2 md:px-4">
  <div class="mb-4 flex gap-2 items-center">
    <a href="{{ route('mikrotik.index') }}" class="m-btn">Table List</a>
    <a href="{{ route('mikrotik.dashboard',$mikrotik) }}" class="m-btn">Dashboard</a>
    <a href="{{ route('mikrotik.pppoe',$mikrotik) }}" class="m-btn m-btnp">PPPoE</a>
    <a href="{{ route('mikrotik.ipstatic',$mikrotik) }}" class="m-btn">IP Static</a>
    <span class="badge">hotfix-pppoe-ui</span>
  </div>

  <div class="grid md:grid-cols-2 gap-4">
    <!-- Tambah Client -->
    <div class="m-card">
      <div class="p-4 space-y-3">
        <div class="text-lg font-semibold mb-1">Tambah Client</div>
        <form method="POST" action="{{ route('mikrotik.pppoe.add',$mikrotik) }}" class="space-y-3">
          @csrf
          <div>
            <label class="small block mb-1">Username <span class="m-muted">mis: user01</span></label>
            <input name="name" class="m-inp w-full" required>
          </div>
          <div>
            <label class="small block mb-1">Password</label>
            <input name="password" type="password" class="m-inp w-full" required>
          </div>
          <div>
            <label class="small block mb-1">Profil</label>
            <div class="m-selwrap">
              <select name="profile" class="m-sel w-full">
                @foreach($profileList as $p)
                  <option value="{{ $p }}">{{ $p }}</option>
                @endforeach
              </select>
            </div>
          </div>
          <label class="small flex items-center gap-2"><input type="checkbox" name="record"> Rekam trafik client ini</label>
          <button class="m-btn m-btnp">SIMPAN</button>
        </form>
      </div>
    </div>

    <!-- Tambah Profil -->
    <div class="m-card">
      <div class="p-4 space-y-3">
        <div class="text-lg font-semibold mb-1">Tambah Profil</div>
        <form method="POST" action="{{ route('mikrotik.pppoe.profile.add',$mikrotik) }}" class="space-y-3">
          @csrf
          <div>
            <label class="small block mb-1">Nama Profil <span class="m-muted">mis: 10M</span></label>
            <input name="name" class="m-inp w-full" required>
          </div>
          <div>
            <label class="small block mb-1">Rate-limit (up/down) <span class="m-muted">mis: 10M/10M</span></label>
            <input name="rate" class="m-inp w-full" placeholder="10M/10M" required>
          </div>
          <div>
            <label class="small block mb-1">Parent Queue <span class="m-muted">(opsional)</span></label>
            <input name="parent" class="m-inp w-full" placeholder="mis: global">
          </div>
          <button class="m-btn m-btnp">SIMPAN</button>
        </form>
      </div>
    </div>
  </div>

  <!-- Kelola Client -->
  <div class="m-card mt-4">
    <div class="p-4">
      <div class="flex items-center justify-between mb-3">
        <div class="small">Total: <b>{{ $total }}</b> — Active: <b>{{ $activeCount }}</b> — Inactive: <b>{{ $inactiveCount }}</b></div>
        <div class="m-selwrap" style="min-width:280px">
          <input id="pppSearch" class="m-inp w-full" placeholder="Cari username / profil / status ...">
        </div>
      </div>

      <div class="overflow-auto">
        <table class="w-full tbl" id="pppTable">
          <thead class="text-left m-muted">
            <tr>
              <th>USERNAME</th>
              <th>PROFIL</th>
              <th>SERVICE</th>
              <th>STATUS</th>
              <th>REKAM</th>
              <th>AKSI</th>
            </tr>
          </thead>
          <tbody>
          @foreach(($secrets ?? []) as $u)
            @php
              $uname   = $toStr(is_array($u)?($u['name']??'') : ($u->name??''));
              $uprofil = $toStr(is_array($u)?($u['profile']??'') : ($u->profile??''));
              $svc     = $toStr(is_array($u)?($u['service']??'pppoe') : ($u->service??'pppoe')) ?: 'pppoe';
              $disabled= strtolower($toStr(is_array($u)?($u['disabled']??'no') : ($u->disabled??'no'))) === 'yes';
              $isActive= isset($activeSet[$uname]);
            @endphp
            <tr data-row="{{ strtolower($uname.' '.$uprofil.' '.($isActive?'active':'inactive')) }}">
              <td class="whitespace-nowrap">{{ $uname }}</td>
              <td class="whitespace-nowrap">
                <form method="POST" action="{{ route('mikrotik.pppoe.edit',$mikrotik) }}" class="flex items-center gap-2">
                  @csrf
                  <input type="hidden" name="name" value="{{ $uname }}">
                  <div class="m-selwrap">
                    <select name="profile" class="m-sel">
                      @foreach($profileList as $p)
                        <option value="{{ $p }}" {{ $p===$uprofil?'selected':'' }}>{{ $p }}</option>
                      @endforeach
                    </select>
                  </div>
                  <button class="m-btn">Ubah</button>
                </form>
              </td>
              <td class="whitespace-nowrap">{{ $svc }}</td>
              <td class="whitespace-nowrap">
                @if($isActive)
                  <span class="pill pill-on">active</span>
                @else
                  <span class="pill pill-off">inactive</span>
                @endif
              </td>
              <td class="whitespace-nowrap">
                <label class="small flex items-center gap-2">
                  <input type="checkbox" class="js-record" data-name="{{ $uname }}">
                  Rekam
                </label>
              </td>
              <td class="whitespace-nowrap">
                <div class="flex items-center gap-2">
                  <form method="POST" action="{{ route('mikrotik.pppoe.edit',$mikrotik) }}">
                    @csrf
                    <input type="hidden" name="name" value="{{ $uname }}">
                    <input type="hidden" name="disabled" value="{{ $disabled ? 'no' : 'yes' }}">
                    <button class="m-btn">{{ $disabled ? 'Enable' : 'Disable' }}</button>
                  </form>
                  <form method="POST" action="{{ route('mikrotik.pppoe.delete',$mikrotik) }}" onsubmit="return confirm('Hapus {{ $uname }} ?')">
                    @csrf
                    <input type="hidden" name="name" value="{{ $uname }}">
                    <button class="m-btn">Delete</button>
                  </form>
                </div>
              </td>
            </tr>
          @endforeach
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>

<script>
(function(){
  // Search filter
  const q=document.getElementById('pppSearch');
  const tb=document.getElementById('pppTable')?.querySelector('tbody');
  if(q && tb){
    q.addEventListener('input',()=>{
      const v=q.value.trim().toLowerCase();
      tb.querySelectorAll('tr').forEach(tr=>{
        const h=tr.getAttribute('data-row')||'';
        tr.style.display = h.includes(v)?'':'none';
      });
    });
  }

  // Rekam checkbox -> POST AJAX ke mikrotik.pppoe.record
  const token = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '{{ csrf_token() }}';
  document.querySelectorAll('.js-record').forEach(cb=>{
    cb.addEventListener('change', async (e)=>{
      const enable = e.target.checked ? 1 : 0;
      const name = e.target.getAttribute('data-name');
      try {
        await fetch("{{ route('mikrotik.pppoe.record',$mikrotik) }}", {
          method:'POST',
          headers: {'Content-Type':'application/json','X-CSRF-TOKEN':token},
          body: JSON.stringify({ name, enable })
        });
      } catch {}
    });
  });
})();
</script>
<!-- mk-autosubmit-v1 --><script>(function(){  function markButtons(scope){    (scope||document).querySelectorAll('button, a.btn').forEach(function(b){      var txt=(b.textContent||'').trim().toLowerCase();      if(['simpan','delete','hapus','disable','enable','rekam','ubah'].indexOf(txt)>-1){        b.setAttribute('data-autosubmit','1');      }    });  }  document.addEventListener('click',function(ev){    var btn=ev.target.closest('[data-autosubmit]');    if(!btn) return;    var f=btn.closest('form');    if(f){ ev.preventDefault(); try{ f.submit(); }catch(e){ console.error(e);} }  });  document.addEventListener('DOMContentLoaded',function(){ markButtons(document); });})();</script>
<!-- mk-force-submit-v3 --><script>(function(){  if(window.__mk_force_submit_v3) return; window.__mk_force_submit_v3=true;  function fix(){    document.querySelectorAll('form').forEach(function(f){      f.querySelectorAll('button').forEach(function(b){        var t=(b.getAttribute('type')||'').toLowerCase();        if(t===''||t==='button'){ b.setAttribute('type','submit'); }      });    });  }  document.addEventListener('DOMContentLoaded',fix);})();</script>
<!-- mk-helpers-v1 start (pppoe) --><div id="mk-helpers" hidden>  <form id="mk-pppoe-add" method="POST" action="/mikrotik/{{ $mikrotik->id }}/pppoe/add">    <input type="hidden" name="_token" value="{{ csrf_token() }}">    <input type="hidden" name="name">    <input type="hidden" name="password">    <input type="hidden" name="profile">    <input type="hidden" name="record">  </form>  <form id="mk-pppoe-del" method="POST" action="/mikrotik/{{ $mikrotik->id }}/pppoe/delete">    <input type="hidden" name="_token" value="{{ csrf_token() }}">    <input type="hidden" name="name">  </form>  <form id="mk-pppoe-edit" method="POST" action="/mikrotik/{{ $mikrotik->id }}/pppoe/edit">    <input type="hidden" name="_token" value="{{ csrf_token() }}">    <input type="hidden" name="name">    <input type="hidden" name="password">    <input type="hidden" name="profile">    <input type="hidden" name="disabled">    <input type="hidden" name="record">  </form>  <form id="mk-pppoe-profadd" method="POST" action="/mikrotik/{{ $mikrotik->id }}/pppoe/profile/add">    <input type="hidden" name="_token" value="{{ csrf_token() }}">    <input type="hidden" name="name">    <input type="hidden" name="rate_up">    <input type="hidden" name="rate_down">    <input type="hidden" name="parent">  </form></div><!-- mk-helpers-v1 end --><!-- mk-wire-v1 start (pppoe) --><script>(function(){ if(window.__mk_wire_pppoe_v1) return; window.__mk_wire_pppoe_v1=true; const Q=(r,sel)=>r.querySelector(sel); const QQ=(r,sel)=>Array.from(r.querySelectorAll(sel)); function txt(el){return (el.textContent||'').trim().toLowerCase()} function toast(m){ if(window.toastr){toastr.info(m)} else console.log(m) } function submitForm(fid, data){ const f=document.getElementById(fid); if(!f) return; Object.keys(data||{}).forEach(k=>{ const inp=f.querySelector(); if(inp) inp.value=(data[k]??''); }); f.submit(); } document.addEventListener('click', function(ev){   const b=ev.target.closest('button, a.btn, .m-btn, [role=button]'); if(!b) return;   const label=txt(b);   const card=b.closest('.card, .m-card, .p-4, .container')||document;   /* Tambah Client PPPoE (cari input name/password/profile di kartu/form yang sama) */   if(label.includes('simpan')||label.includes('tambah')){     const nm=Q(card,'input[name=name], input#pppoe-name, input[name="pppoe[name]"]');     const pw=Q(card,'input[name=password], input#pppoe-pass');     if(nm && pw){ ev.preventDefault();       const prof=Q(card,'select[name=profile], select#pppoe-profile');       const rec=Q(card,'input[name=record], input#pppoe-record');       submitForm('mk-pppoe-add',{         name:nm.value||'', password:pw.value||'', profile:(prof&&prof.value)||'', record:(rec&&rec.checked)?'on':''       });       return;     }   }   /* Hapus Client PPPoE (button Hapus di baris tabel) */   if(label.includes('hapus')||label.includes('delete')){     const row=b.closest('tr, .list-group-item, .card')||card;     let name='';     const nmInp=Q(row,'input[name=name]'); if(nmInp) name=nmInp.value;     if(!name){ const c0=row.querySelector('td, .cell, .small, strong'); if(c0) name=(c0.textContent||'').trim().split(/s+/)[0]; }     if(name){ ev.preventDefault(); submitForm('mk-pppoe-del',{name}); return; }   }   /* Ubah/Edit Client PPPoE (toggle disable/profile/password) */   if(label.includes('ubah')||label.includes('edit')||label.includes('enable')||label.includes('disable')){     const row=b.closest('tr, .list-group-item, .card')||card;     let name=''; let profile=''; let password=''; let disabled=''; let record='';     const nm=Q(row,'input[name=name]'); if(nm) name=nm.value;     if(!name){ const c0=row.querySelector('td, .cell, .small, strong'); if(c0) name=(c0.textContent||'').trim().split(/s+/)[0]; }     const pr=Q(row,'select[name=profile]'); if(pr) profile=pr.value;     const pw=Q(row,'input[name=password]'); if(pw) password=pw.value;     const dis=Q(row,'input[name=disabled], input[type=checkbox][name=disabled]'); if(dis) disabled=dis.checked?'on':'';     const rec=Q(row,'input[name=record]'); if(rec) record=rec.checked?'on':'';     if(name){ ev.preventDefault(); submitForm('mk-pppoe-edit',{name,profile,password,disabled,record}); return; }   }   /* Tambah Profil PPPoE (nama + rate up/down + parent) */   if(label.includes('profil') && (label.includes('simpan')||label.includes('tambah'))){     const wrap=b.closest('.card, .m-card, .p-4, form')||document;     const name=Q(wrap,'input[name=name], #profile-name');     const up=Q(wrap,'input[name=rate_up], #rate-up');     const down=Q(wrap,'input[name=rate_down], #rate-down');     const parent=Q(wrap,'input[name=parent], select[name=parent], #parent-queue');     if(name){ ev.preventDefault(); submitForm('mk-pppoe-profadd',{       name:name.value||'', rate_up:(up&&up.value)||'', rate_down:(down&&down.value)||'', parent:(parent&&parent.value)||''     }); return; }   } });})();</script><!-- mk-wire-v1 end -->
<!-- mk-helpers-v2-pppoe start --><div id="mk-helpers-pppoe" hidden>  <form id="mk-pppoe-add" method="POST" action="/mikrotik/{{ $mikrotik->id }}/pppoe/add">    <input type="hidden" name="_token" value="{{ csrf_token() }}">    <input type="hidden" name="name">    <input type="hidden" name="password">    <input type="hidden" name="profile">    <input type="hidden" name="record">  </form>  <form id="mk-pppoe-del" method="POST" action="/mikrotik/{{ $mikrotik->id }}/pppoe/delete">    <input type="hidden" name="_token" value="{{ csrf_token() }}">    <input type="hidden" name="name">  </form>  <form id="mk-pppoe-edit" method="POST" action="/mikrotik/{{ $mikrotik->id }}/pppoe/edit">    <input type="hidden" name="_token" value="{{ csrf_token() }}">    <input type="hidden" name="name">    <input type="hidden" name="password">    <input type="hidden" name="profile">    <input type="hidden" name="disabled">    <input type="hidden" name="record">  </form>  <form id="mk-pppoe-profadd" method="POST" action="/mikrotik/{{ $mikrotik->id }}/pppoe/profile/add">    <input type="hidden" name="_token" value="{{ csrf_token() }}">    <input type="hidden" name="name">    <input type="hidden" name="rate_up">    <input type="hidden" name="rate_down">    <input type="hidden" name="parent">  </form></div><!-- mk-helpers-v2-pppoe end -->
@endsection
