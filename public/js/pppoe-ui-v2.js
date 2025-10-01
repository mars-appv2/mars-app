(function(){
  const $ = (s, c=document)=>c.querySelector(s);
  const $$ = (s, c=document)=>Array.from(c.querySelectorAll(s));
  const routeBase = location.pathname.replace(/\/pppoe.*$/,''); // /mikrotik/{id}
  const token = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || ($('input[name="_token"]')?.value || '');

  function flashRow(user){
    const tr = document.querySelector(`tr[data-row="${CSS.escape(user)}"]`);
    if(!tr) return;
    tr.style.outline='2px solid #3fa7ff'; setTimeout(()=>tr.style.outline='none',800);
  }

  // Search filter
  const q = $('#ppp-search');
  if(q){
    q.addEventListener('input', ()=>{
      const v = q.value.toLowerCase();
      $$('#ppp-table tbody tr').forEach(tr=>{
        tr.style.display = tr.textContent.toLowerCase().includes(v) ? '' : 'none';
      });
    });
  }

  async function post(url, data){
    const r = await fetch(url, {
      method:'POST',
      headers:{'Content-Type':'application/json','X-CSRF-TOKEN':token,'X-Requested-With':'XMLHttpRequest'},
      body: JSON.stringify(data||{})
    });
    if(!r.ok){ throw new Error(await r.text()); }
    return r;
  }

  // Add client
  const addForm = $('#ppp-add');
  if(addForm){
    addForm.addEventListener('submit', async (e)=>{
      e.preventDefault();
      const form = new FormData(addForm);
      const payload = Object.fromEntries(form.entries());
      if(payload.record) payload.record = 1;
      await post(`${routeBase}/pppoe/add`, payload);
      location.reload();
    });
  }

  // Add profile
  const pForm = $('#ppp-profile-add');
  if(pForm){
    pForm.addEventListener('submit', async (e)=>{
      e.preventDefault();
      const form = new FormData(pForm);
      await post(`${routeBase}/pppoe/profile/add`, Object.fromEntries(form.entries()));
      alert('Profil ditambahkan'); pForm.reset();
    });
  }

  // Save profile per user
  $$('.ppp-prof-save').forEach(btn=>{
    btn.addEventListener('click', async ()=>{
      const user = btn.dataset.user;
      const sel = document.querySelector(`.ppp-prof[data-user="${CSS.escape(user)}"]`);
      await post(`${routeBase}/pppoe/edit`, {name:user, profile: sel.value});
      flashRow(user);
    });
  });

  // Enable/Disable
  $$('.ppp-enable').forEach(b=>{
    b.addEventListener('click', async ()=>{
      const u = b.dataset.user; await post(`${routeBase}/pppoe/edit`, {name:u, disabled:null}); flashRow(u); location.reload();
    });
  });
  $$('.ppp-disable').forEach(b=>{
    b.addEventListener('click', async ()=>{
      const u = b.dataset.user; await post(`${routeBase}/pppoe/edit`, {name:u, disabled:'yes'}); flashRow(u); location.reload();
    });
  });

  // Delete
  $$('.ppp-del').forEach(b=>{
    b.addEventListener('click', async ()=>{
      const u = b.dataset.user;
      if(!confirm(`Hapus ${u}?`)) return;
      await post(`${routeBase}/pppoe/delete`, {name:u});
      location.reload();
    });
  });

  // Record checkbox
  $$('.ppp-record').forEach(ch=>{
    ch.addEventListener('change', async ()=>{
      const u = ch.dataset.user;
      await post(`${routeBase}/pppoe/record`, {name:u, enable: ch.checked ? 1 : 0});
      flashRow(u);
    });
  });
})();
