@if(app()->environment() == 'testing' || app()->environment() == 'local')
    {{ $slot }}
@endif