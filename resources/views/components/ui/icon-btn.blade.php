@props(['title' => ''])
<button type="button" title="{{ $title }}"
  class="btn btn-ghost w-9 h-9 p-0 inline-flex items-center justify-center">
  {{ $slot }}
</button>
