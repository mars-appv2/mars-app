@extends('staff.layouts.app')
@section('title','Tickets — Staff')

@section('content')
<div class="grid g2">
  <div class="card">
    <div style="font-weight:600;margin-bottom:8px">Buat Ticket</div>
    @if(session('ok'))<div class="badge ok" style="display:block;margin-bottom:8px">{{ session('ok') }}</div>@endif
    @if(session('err'))<div class="badge bad" style="display:block;margin-bottom:8px">{{ session('err') }}</div>@endif

    <form method="POST" action="{{ route('staff.tickets.store') }}" class="grid g1" style="display:grid;gap:10px">
      @csrf
      <div>
        <label class="stat-label">Subject</label>
        <input class="input" name="subject" value="{{ old('subject') }}" required maxlength="200">
        @error('subject')<div class="badge bad" style="margin-top:8px">{{ $message }}</div>@enderror
      </div>
      <div>
        <label class="stat-label">Deskripsi</label>
        <textarea class="input" name="description" rows="4" maxlength="2000">{{ old('description') }}</textarea>
      </div>
      <div>
        <label class="stat-label">Prioritas</label>
        <select class="input" name="priority">
          <option value="normal">Normal</option>
          <option value="high">High</option>
          <option value="urgent">Urgent</option>
        </select>
      </div>
      <div><button class="btn" type="submit">Buat Ticket</button></div>
    </form>
  </div>

  <div class="card">
    <div style="font-weight:600;margin-bottom:8px">Daftar Ticket Terbaru</div>
    @if(empty($tickets) || count($tickets)===0)
      <div class="stat-label">Belum ada ticket.</div>
    @else
      <table class="table">
        <thead><tr><th>ID</th><th>Subject</th><th>Status</th><th>Priority</th><th>Tanggal</th></tr></thead>
        <tbody>
          @foreach($tickets as $t)
            <tr>
              <td>{{ $t->id }}</td>
              <td>{{ $t->subject ?? '-' }}</td>
              <td><span class="badge">{{ $t->status ?? '-' }}</span></td>
              <td>{{ $t->priority ?? '-' }}</td>
              <td>{{ \Carbon\Carbon::parse($t->created_at ?? now())->format('d M Y H:i') }}</td>
            </tr>
          @endforeach
        </tbody>
      </table>
    @endif
  </div>
</div>
@endsection
