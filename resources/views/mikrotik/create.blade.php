<input type="hidden" name="radius_enabled" value="0">
<label class="inline-flex items-center gap-2 mt-2">
  <input type="checkbox" name="radius_enabled" value="1"
         class="m-inp"
         {{ isset($mikrotik) ? ($mikrotik->radius_enabled ? 'checked' : '') : 'checked' }}>
  <span class="text-slate-200">Aktifkan RADIUS</span>
</label>
