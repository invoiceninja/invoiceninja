@foreach (App\Services\AuthService::$providers as $provider)
<button type="button" class="btn btn-primary btn-block" onclick="socialSignup('{{ strtolower($provider) }}')" id="{{ strtolower($provider) }}LoginButton">
    <i class="fa fa-{{ strtolower($provider) }}"></i> &nbsp;
    {{ $provider }}
</button>
@endforeach

<script type="text/javascript">
    function socialSignup(provider) {
        trackEvent('/account', '/social_{{ $type }}/' + provider);
        localStorage.setItem('auth_provider', provider);
        setTimeout(function() {
            window.location = '{{ SITE_URL }}/auth/' + provider;
        }, 150);
    }
</script>