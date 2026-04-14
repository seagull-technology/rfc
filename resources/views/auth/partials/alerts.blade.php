@if (session('status'))
    <div class="alert alert-success mb-3">{{ session('status') }}</div>
@endif

@if ($errors->any())
    <div class="alert alert-danger mb-3">{{ $errors->first() }}</div>
@endif
