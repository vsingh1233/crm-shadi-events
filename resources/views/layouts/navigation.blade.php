<nav class="crm-nav" aria-label="Main navigation">
    <a href="{{ route('dashboard') }}" class="brand"><x-application-logo /><small>THE PLANNING DESK</small></a>
    <div class="nav-links">
        @foreach(['dashboard'=>'Overview','leads.index'=>'Leads','follow-ups.index'=>'Follow-ups','notifications.index'=>'Notifications'] as $route=>$label)
            <a href="{{ route($route) }}" class="{{ request()->routeIs(explode('.',$route)[0].'*') ? 'active' : '' }}">{{ $label }}@if($route==='notifications.index' && ($unread=auth()->user()->unreadNotifications()->count())) <span class="count">{{ $unread }}</span>@endif</a>
        @endforeach
        @if(auth()->user()->isAdministrator())<a href="{{ route('team.index') }}" class="{{ request()->routeIs('team.*')?'active':'' }}">Team</a>@endif
    </div>
    <div class="nav-account"><a href="{{ route('profile.edit') }}">{{ auth()->user()->name }}</a><form method="POST" action="{{ route('logout') }}">@csrf<button class="text-sm">Log out</button></form></div>
</nav>

