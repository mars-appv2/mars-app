(function(){
  // Pindah #mon-groups ke tepat di bawah blok interface (tanpa rubah desain)
  const groups = document.getElementById('mon-groups');
  const ifaceSelect = document.querySelector('select[name="iface"]') || document.querySelector('#iface-select');
  if (groups && ifaceSelect) {
    const anchor = ifaceSelect.closest('.mb-3, .card-body, .form-group, div') || ifaceSelect.parentNode;
    if (anchor && anchor.parentNode) {
      // sisipkan setelah anchor
      if (groups.previousElementSibling !== anchor) {
        anchor.parentNode.insertBefore(groups, anchor.nextSibling);
      }
    }
  }

  // Tambah efek glow ringan pada progress bar saat polling aktif
  const style = document.createElement('style');
  style.textContent = `
    .progress .progress-bar{ box-shadow:0 0 10px rgba(98,155,255,.6); transition:width .4s ease; }
    .monitor-tile, .card.kpi-tile { transition: box-shadow .2s ease; }
    .monitor-tile.running, .kpi-running { box-shadow:0 0 0 1px #3957a7 inset, 0 0 16px rgba(98,155,255,.15); }
  `;
  document.head.appendChild(style);

  // Kalau ada tombol Mulai/Stop, set class running pada container
  const btnStart = document.querySelector('button#monitor-start, button.btn-start, button.start-mon');
  const btnStop  = document.querySelector('button#monitor-stop,  button.btn-stop,  button:contains("Stop")');
  const wrap     = document.querySelector('#monitor-tiles, .monitor-wrapper, .card');

  function addRunning(){ if(wrap) wrap.classList.add('kpi-running','running'); }
  function remRunning(){ if(wrap) wrap.classList.remove('kpi-running','running'); }

  if (btnStart) btnStart.addEventListener('click', addRunning);
  if (btnStop)  btnStop .addEventListener('click', remRunning);
})();
