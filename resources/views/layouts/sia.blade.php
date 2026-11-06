@php
  $user     = auth()->user();
  $role     = $user->role;
  $menu     = \App\Support\Menu::forRole($role);
  [$crumbGroup, $crumbItem] = \App\Support\Menu::crumb($menu);
  $t        = $app['theme'];
  $roleName = ['admin'=>'Admin','keuangan'=>'Keuangan','gudang'=>'Gudang','pemilik'=>'Pemilik'][$role] ?? $role;
  $initial  = mb_strtoupper(mb_substr($user->name, 0, 1));
  $coInit   = mb_strtoupper(mb_substr($app['name'], 0, 1));
@endphp
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>@yield('title') - {{ $app['name'] }}</title>
  @if($app['favicon_url'])<link rel="icon" href="{{ $app['favicon_url'] }}">@endif
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
  <link href="{{ asset('css/sia.css') }}?v={{ @filemtime(public_path('css/sia.css')) }}" rel="stylesheet">
  <style>
    :root{
      --brand:{{ $t['brand'] }}; --accent:{{ $t['accent'] }};
      --sb-bg:{{ $t['bg'] }}; --sb-text:{{ $t['text'] }}; --sb-title:{{ $t['title'] }}; --sb-muted:{{ $t['muted'] }};
      --sb-hover:{{ $t['hover'] }}; --sb-line:{{ $t['line'] }}; --sb-active:{{ $t['active'] }};
    }
  </style>
  <script>
    try {
      var s = localStorage.getItem('sb');
      if (s === '1' || (s === null && {{ $app['collapsed'] ? 'true' : 'false' }})) document.documentElement.classList.add('sb-collapsed');
    } catch (e) {}
  </script>
</head>
<body>
<div class="app">

  {{-- ================= SIDEBAR ================= --}}
  <aside class="sidebar" id="sidebar">
    <div class="sb-brand">
      <div class="sb-logo {{ $app['logo_url'] ? '' : 'initial' }}">
        @if($app['logo_url'])<img src="{{ $app['logo_url'] }}" alt="Logo">@else{{ $coInit }}@endif
      </div>
      <div class="sb-brand-text">
        <div class="sb-name">{{ $app['name'] }}</div>
        <div class="sb-tag">{{ $app['tagline'] }}</div>
      </div>
      <button type="button" class="sb-toggle" id="sbToggle" title="Ciutkan / lebarkan"><i class="bi bi-chevron-double-left"></i></button>
    </div>

    <nav class="sb-nav">
      @foreach($menu as $i => $g)
        @if(isset($g['items']))
          <button type="button" class="sb-group {{ $g['open'] ? '' : 'collapsed' }}" data-bs-toggle="collapse"
                  data-bs-target="#grp{{ $i }}" aria-expanded="{{ $g['open'] ? 'true' : 'false' }}">
            <span class="sb-glabel">{{ $g['label'] }}</span><i class="bi bi-chevron-down"></i>
          </button>
          <div class="collapse {{ $g['open'] ? 'show' : '' }}" id="grp{{ $i }}">
            @foreach($g['items'] as $it)
              <a href="{{ route($it['route']) }}" class="sb-link {{ $it['is_active'] ? 'active' : '' }}" title="{{ $it['label'] }}">
                <i class="bi {{ $it['icon'] }}"></i><span class="sb-text">{{ $it['label'] }}</span>
              </a>
            @endforeach
          </div>
        @else
          <a href="{{ route($g['route']) }}" class="sb-link {{ $g['is_active'] ? 'active' : '' }}" title="{{ $g['label'] }}" style="margin-top:6px">
            <i class="bi {{ $g['icon'] }}"></i><span class="sb-text">{{ $g['label'] }}</span>
          </a>
        @endif
      @endforeach
    </nav>

    <div class="sb-user">
      <div class="sb-avatar">{{ $initial }}</div>
      <div class="sb-user-info">
        <div class="sb-uname">{{ $user->name }}</div>
        <div class="sb-urole">{{ $roleName }}</div>
      </div>
      <form method="POST" action="{{ route('logout') }}">@csrf
        <button class="sb-logout" title="Keluar"><i class="bi bi-box-arrow-right"></i></button>
      </form>
    </div>
  </aside>
  <div class="sb-overlay" id="sbOverlay"></div>

  {{-- ================= KONTEN ================= --}}
  <div class="main">
    <header class="topbar">
      <button type="button" class="btn btn-light d-lg-none" id="sbOpen"><i class="bi bi-list"></i></button>
      <div class="crumb">
        <i class="bi bi-house"></i>
        @if($crumbItem)
          @if($crumbGroup)<span>/</span><span>{{ $crumbGroup }}</span>@endif
          <span>/</span><b class="text-truncate">{{ $crumbItem }}</b>
        @else
          <span>/</span><b class="text-truncate">@yield('title')</b>
        @endif
      </div>

      <div class="ms-auto dropdown">
        <button class="btn btn-light d-flex align-items-center gap-2" data-bs-toggle="dropdown">
          <span class="avatar">{{ $initial }}</span>
          <span class="text-start d-none d-md-block lh-sm">
            <span class="d-block fw-semibold small">{{ $user->name }}</span>
            <span class="d-block text-muted" style="font-size:11px">{{ $roleName }}</span>
          </span>
          <i class="bi bi-chevron-down small"></i>
        </button>
        <ul class="dropdown-menu dropdown-menu-end shadow border-0">
          <li><a class="dropdown-item" href="{{ route('account.password') }}"><i class="bi bi-key me-2"></i>Ganti Kata Sandi</a></li>
          <li><hr class="dropdown-divider"></li>
          <li><form method="POST" action="{{ route('logout') }}">@csrf
            <button class="dropdown-item text-danger"><i class="bi bi-box-arrow-right me-2"></i>Keluar</button></form></li>
        </ul>
      </div>
    </header>

    <main class="content">
      @if(session('success')) <div class="alert alert-success auto-hide"><i class="bi bi-check-circle me-1"></i>{{ session('success') }}</div> @endif
      @if(session('error'))   <div class="alert alert-danger"><i class="bi bi-exclamation-triangle me-1"></i>{{ session('error') }}</div> @endif
      @if($errors->any())
        <div class="alert alert-danger"><ul class="mb-0">
          @foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach
        </ul></div>
      @endif
      @yield('content')
    </main>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
  const root = document.documentElement, sb = document.getElementById('sidebar'), ov = document.getElementById('sbOverlay');
  document.getElementById('sbToggle').addEventListener('click', () => {
    root.classList.toggle('sb-collapsed');
    try { localStorage.setItem('sb', root.classList.contains('sb-collapsed') ? '1' : '0'); } catch (e) {}
  });
  const close = () => { sb.classList.remove('open'); ov.classList.remove('show'); };
  document.getElementById('sbOpen').addEventListener('click', () => { sb.classList.add('open'); ov.classList.add('show'); });
  ov.addEventListener('click', close);
  setTimeout(() => document.querySelectorAll('.auto-hide').forEach(a => bootstrap.Alert.getOrCreateInstance(a).close()), 6000);
</script>
@stack('scripts')
</body>
</html>