@props(['active' => false, 'text' => null])
@php
  $on = (bool) $active;
  $label = $text ?? ($on ? 'AKTIF' : 'NONAKTIF');
@endphp
<span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-semibold
  {{ $on ? 'bg-emerald-500/15 text-emerald-300' : 'bg-rose-500/15 text-rose-300' }}">
  {{ $label }}
</span>
