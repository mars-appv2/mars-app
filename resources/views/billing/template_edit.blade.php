@extends('layouts.app')
@section('title','Edit Invoice Template')

@section('content')
@if(session('ok'))<div class="m-alert m-alert-success mb-4">{{ session('ok') }}</div>@endif

<div class="m-card p-5">
  <div class="text-lg text-slate-200 font-semibold mb-3">Edit Template Invoice</div>
  <form method="POST" action="{{ route('billing.template.save') }}">
    @csrf
    <div class="mb-3 text-sm text-[var(--muted)]">
      Placeholder yg tersedia:
      <code>{{ '{number} {period} {due_date} {customer_name} {username} {mikrotik} {plan} {amount_fmt} {ppn_mode} {ppn_fmt} {disc_fmt} {total_fmt} {notes}' }}</code><br>
      Kondisi: <code>{{ '{#if ppn_line}...{/if}' }}</code>, <code>{{ '{#if disc_line}...{/if}' }}</code>
    </div>
    <textarea name="content" class="m-inp" rows="24">{{ $content }}</textarea>
    <div class="mt-3">
      <button class="m-btn m-btn-primary">Simpan</button>
    </div>
  </form>
</div>
@endsection
