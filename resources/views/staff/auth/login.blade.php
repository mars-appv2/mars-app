@php($appName = 'MARS DATA — STAFF')
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Masuk Staff — {{ $appName }}</title>
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="{{ asset('assets/client-auth.css') }}">
</head>
<body class="auth-bg">
  <div class="auth-wrap">
    <div class="brand">
      <img src="{{ asset('images/md-logo.svg') }}" alt="Logo" class="brand-logo">
      <div class="brand-title">PT MARS DATA</div>
      <div class="brand-sub">STAFF PORTAL</div>
    </div>

    <div class="card">
      <div class="card-title">Masuk Staff</div>
      <form method="POST" action="{{ route('staff.login.submit') }}" class="form">
        @csrf
        <label class="label">Username / Email</label>
        <div class="inp-wrap @error('identity') has-error @enderror">
          <input class="inp" type="text" name="identity" value="{{ old('identity') }}" placeholder="username / email" required>
        </div>
        @error('identity')<div class="err">{{ $message }}</div>@enderror

        <label class="label mt-12">Password</label>
        <div class="inp-wrap pass">
          <input id="password" class="inp" type="password" name="password" placeholder="********" required>
          <button type="button" class="eye" onclick="togglePass()">
            <svg width="22" height="22" viewBox="0 0 24 24" fill="none">
              <path d="M1 12s4-7 11-7 11 7 11 7-4 7-11 7S1 12 1 12Z" stroke="currentColor" stroke-width="1.8"/>
              <circle cx="12" cy="12" r="3" stroke="currentColor" stroke-width="1.8"/>
            </svg>
          </button>
        </div>

        <label class="remember"><input type="checkbox" name="remember"> Ingat saya</label>
        <button class="btn" type="submit">MASUK</button>
      </form>
    </div>

    <div class="copyright">© {{ date('Y') }} PT MARS DATA TELEKOMUNIKASI</div>
  </div>

<script>function togglePass(){const el=document.getElementById('password'); el.type=el.type==='password'?'text':'password'}</script>
</body>
</html>
