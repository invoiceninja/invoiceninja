@extends('portal.ninja2020.layout.clean')
@section('meta_title', ctrans('texts.sign_up_with_wepay'))

@section('body')
    

    <div class="flex flex-col justify-center items-center mt-10">
        <img src="{{ asset('images/wepay.svg') }}" alt="We Pay">
    </div>

    @livewire('wepay-signup', ['user_id' => $user_id, 'company_key' => $company_key])               


@endsection

@push('footer')
    <script>
    </script>
@endpush