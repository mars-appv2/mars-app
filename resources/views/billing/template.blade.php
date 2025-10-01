@extends('layouts.app')
@section('title','Billing — Template Invoice')

@section('content')
<div class="m-card p-5 mb-4">
  <div class="text-lg text-slate-200 font-semibold mb-4">Template Invoice (Header & Logo)</div>

  @if(session('ok'))
    <div class="mb-3 text-sm px-3 py-2 rounded bg-green-900/40 text-green-200 border border-green-800">
      {{ session('ok') }}
    </div>
  @endif
  @if(session('err'))
    <div class="mb-3 text-sm px-3 py-2 rounded bg-red-900/40 text-red-200 border border-red-800">
      {{ session('err') }}
    </div>
  @endif

  <form method="POST" action="{{ route('billing.template.save') }}" enctype="multipart/form-data" class="grid md:grid-cols-2 gap-4">
    @csrf

    <div>
      <label class="m-lab">Nama Perusahaan</label>
      <input type="text" name="company" value="{{ $tpl['company'] ?? '' }}" class="m-inp" autocomplete="organization">
    </div>
    <div>
      <label class="m-lab">Telepon</label>
      <input type="text" name="phone" value="{{ $tpl['phone'] ?? '' }}" class="m-inp" autocomplete="tel">
    </div>

    <div class="md:col-span-2">
      <label class="m-lab">Alamat</label>
      <input type="text" name="address" value="{{ $tpl['address'] ?? '' }}" class="m-inp" autocomplete="street-address">
    </div>

    <div>
      <label class="m-lab">NPWP / Tax ID</label>
      <input type="text" name="tax_id" value="{{ $tpl['tax_id'] ?? '' }}" class="m-inp">
    </div>

    <div>
      <label class="m-lab">Posisi Logo</label>
      @php $align = $tpl['logo_align'] ?? 'left'; @endphp
      <select name="logo_align" class="m-inp">
        <option value="left"   {{ $align==='left'?'selected':'' }}>Kiri</option>
        <option value="center" {{ $align==='center'?'selected':'' }}>Tengah</option>
        <option value="right"  {{ $align==='right'?'selected':'' }}>Kanan</option>
      </select>
    </div>

    <div class="md:col-span-2">
      <label class="m-lab">Logo (drag & drop / klik)</label>
      <div id="dropzone"
           class="border border-dashed border-slate-600/60 rounded-xl p-6 text-center cursor-pointer
                  bg-slate-900/40 hover:bg-slate-900/60 transition-colors">
        <input id="logoInput" type="file" name="logo" accept="image/*" class="hidden">
        <div id="dzPreview" class="flex flex-col items-center gap-2">
          @if(!empty($tpl['logo_url']))
            <img id="logoImg" src="{{ $tpl['logo_url'] }}" alt="logo" class="h-16 object-contain rounded">
            <div class="text-xs text-[var(--muted)]">Tarik & lepas file baru untuk mengganti.</div>
          @else
            <svg width="32" height="32" viewBox="0 0 24 24" fill="none" class="opacity-70">
              <path d="M12 16v-8m0 0l-3 3m3-3l3 3M4 16v2a2 2 0 002 2h12a2 2 0 002-2v-2"
                    stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
            </svg>
            <div class="text-sm">Drag & drop logo ke sini, atau klik untuk pilih.</div>
          @endif
          <div id="dzFilename" class="text-xs text-[var(--muted)]"></div>
        </div>
      </div>
    </div>

    <div class="md:col-span-2">
      <button class="m-btn">Simpan</button>
    </div>
  </form>

  <div class="text-xs text-[var(--muted)] mt-4">
    Disimpan ke <b>.env</b>: INV_COMPANY, INV_ADDRESS, INV_PHONE, INV_TAX_ID, INV_LOGO_URL, INV_LOGO_ALIGN
  </div>
</div>

<script>
(function(){
  const dz = document.getElementById('dropzone');
  const inp = document.getElementById('logoInput');
  const dzFilename = document.getElementById('dzFilename');

  function handleFiles(files){
    if(!files || !files[0]) return;
    const f = files[0];
    const url = URL.createObjectURL(f);
    let img = document.getElementById('logoImg');
    if(!img){
      img = document.createElement('img');
      img.id = 'logoImg';
      img.className = 'h-16 object-contain rounded';
      document.getElementById('dzPreview').prepend(img);
    }
    img.src = url;
    dzFilename.textContent = f.name + ' (' + Math.round(f.size/1024) + ' KB)';
  }

  dz.addEventListener('click', () => inp.click());
  inp.addEventListener('change', e => handleFiles(e.target.files));

  dz.addEventListener('dragover', e => {
    e.preventDefault();
    dz.classList.add('ring-2','ring-indigo-500/60');
  });
  dz.addEventListener('dragleave', e => {
    dz.classList.remove('ring-2','ring-indigo-500/60');
  });
  dz.addEventListener('drop', e => {
    e.preventDefault();
    dz.classList.remove('ring-2','ring-indigo-500/60');
    if(e.dataTransfer.files && e.dataTransfer.files.length){
      inp.files = e.dataTransfer.files;
      handleFiles(e.dataTransfer.files);
    }
  });
})();
</script>
@endsection

