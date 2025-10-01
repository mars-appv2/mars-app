(function(){
  const $ = (sel,ctx=document)=>ctx.querySelector(sel);
  const $$ = (sel,ctx=document)=>Array.from(ctx.querySelectorAll(sel));
  const pathMatch = location.pathname.match(/\/mikrotik\/(\d+)/);
  if(!pathMatch) return;
  const mid = pathMatch[1];

  const sel = $('#mg-select'), btnLoad=$('#mg-load'),
        inName = $('#mg-name'), btnSave=$('#mg-save'), btnDel=$('#mg-del');

  async function loadGroups(){
    try{
      const r = await fetch(`/mikrotik/${mid}/groups`, {headers:{'Accept':'application/json'}});
      const list = await r.json();
      sel.innerHTML = '';
      if(!Array.isArray(list) || list.length===0){
        const o = document.createElement('option'); o.value=''; o.textContent='(Belum ada group)';
        sel.appendChild(o); return;
      }
      list.forEach(g=>{
        const o=document.createElement('option');
        o.value=g.id; o.textContent=`${g.name} (${g.items_count})`;
        sel.appendChild(o);
      });
    }catch(e){ console.warn('loadGroups fail', e); }
  }

  // Baca interface yg sudah kamu "Tambah" (chip/badge)
  function getAddedIfaces(){
    // cari badge yang memuat nama interface (ether1, sfp-sfpplus1, dst)
    // fallback: ambil dari select utama kalau ada
    const chips = $$('.badge, .tag, .btn-chip, .iface-chip');
    const out = [];
    chips.forEach(c=>{
      const t = (c.textContent||'').trim();
      if(!t) return;
      // ambil kata pertama (sebelum spasi)
      const k = t.split(/\s+/)[0];
      if(!out.includes(k)) out.push(k);
    });
    if(out.length) return out;

    const mainSel = document.querySelector('select');
    if(mainSel && mainSel.options.length){
      const txt = (mainSel.selectedOptions[0].textContent||'').trim();
      return [txt.split(/\s+/)[0]];
    }
    return [];
  }

  async function saveGroup(){
    const name = (inName.value||'').trim();
    if(!name){ alert('Isi nama group.'); return; }
    const ifaces = getAddedIfaces();
    if(!ifaces.length){ alert('Tambahkan dulu interface, lalu simpan.'); return; }
    const r = await fetch(`/mikrotik/${mid}/groups`, {
      method:'POST',
      headers: {'Content-Type':'application/json','X-Requested-With':'XMLHttpRequest','Accept':'application/json','X-CSRF-TOKEN': getCsrf()},
      body: JSON.stringify({name, ifaces})
    });
    if(!r.ok){ alert('Gagal simpan group'); return; }
    inName.value='';
    await loadGroups();
  }

  async function deleteGroup(){
    const id = sel.value; if(!id){ alert('Pilih group'); return; }
    if(!confirm('Hapus group ini?')) return;
    const r = await fetch(`/mikrotik/${mid}/groups/${id}`, {
      method:'DELETE',
      headers: {'X-Requested-With':'XMLHttpRequest','Accept':'application/json','X-CSRF-TOKEN': getCsrf()}
    });
    if(!r.ok){ alert('Gagal hapus'); return; }
    await loadGroups();
  }

  function getCsrf(){
    const m = document.querySelector('meta[name="csrf-token"]');
    if(m) return m.getAttribute('content');
    // fallback dari input hidden di form login csrf
    const i = document.querySelector('input[name="_token"]');
    return i ? i.value : '';
  }

  async function applyGroup(){
    const id = sel.value; if(!id){ alert('Pilih group'); return; }
    const r = await fetch(`/mikrotik/${mid}/groups/${id}`, {headers:{'Accept':'application/json'}});
    if(!r.ok){ alert('Gagal ambil isi group'); return; }
    const data = await r.json();
    const ifaces = data.items || [];
    if(!ifaces.length){ alert('Group kosong'); return; }

    // Temukan select interface & tombol Tambah/Mulai di UI yang sudah ada
    // — tidak ubah desain, hanya "mensimulasikan" klik user.
    const mainSel = document.querySelector('select');
    const btnTambah = $$('button, a').find(b => (b.textContent||'').trim().toLowerCase() === 'tambah');
    const btnMulai  = $$('button, a').find(b => (b.textContent||'').trim().toLowerCase() === 'mulai');

    if(!mainSel || !btnTambah){ alert('Kontrol Tambah interface tidak ditemukan'); return; }

    for(const iface of ifaces){
      // pilih option yang teksnya diawali iface
      let found = false;
      Array.from(mainSel.options).forEach(opt=>{
        const t = (opt.textContent||'').trim();
        if(t.toLowerCase().startsWith(iface.toLowerCase())){ mainSel.value = opt.value; found = true; }
      });
      if(found){
        mainSel.dispatchEvent(new Event('change', {bubbles:true}));
        btnTambah.click();
        await new Promise(r=>setTimeout(r,120));
      }
    }
    if(btnMulai){ btnMulai.click(); }
  }

  if(sel){ loadGroups(); }
  if(btnSave) btnSave.addEventListener('click', saveGroup);
  if(btnDel)  btnDel.addEventListener('click', deleteGroup);
  if(btnLoad) btnLoad.addEventListener('click', applyGroup);
})();
