<ul class="nav nav-tabs mb-4 no-print">
  <li class="nav-item"><a class="nav-link {{ request()->routeIs('settings.company*') ? 'active' : '' }}" href="{{ route('settings.company') }}">Profil Perusahaan</a></li>
  <li class="nav-item"><a class="nav-link {{ request()->routeIs('settings.appearance*') ? 'active' : '' }}" href="{{ route('settings.appearance') }}">Tampilan</a></li>
  <li class="nav-item"><a class="nav-link {{ request()->routeIs('settings.users*') ? 'active' : '' }}" href="{{ route('settings.users') }}">Pengguna</a></li>
</ul>