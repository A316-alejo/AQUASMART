@if (session('status'))
    <div class="notice ok" role="status">{{ session('status') }}</div>
@endif

@if (session('error'))
    <div class="notice crit" role="alert">{{ session('error') }}</div>
@endif
