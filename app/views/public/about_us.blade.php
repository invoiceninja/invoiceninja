@extends('public.header')

@section('content')
<section class="hero background hero-about" data-speed="2" data-type="background">
  <div class="container">
    <div class="row">
      <h1><img src="{{ asset('images/icon-about.png') }}">{{ trans('public.about.header') }}</h1>
    </div>
  </div>
</section>

<section class="about">
  <div class="container">
    <div class="row">
      <div class="col-md-5 valign">          
        <div class="headline">
          <h2>{{ trans('public.about.what_is') }}</h2>
        </div>
        <p class="first">{{ trans('public.description') }}</p>
      </div>
      <div class="col-md-7">
        <img src="{{ asset('images/devices3.png') }}">
      </div>
    </div>
  </div>
</section>

<section class="team center">
  <div class="container">
    <div class="row">
      <div class="col-md-8 col-md-offset-2">
        <h2>{{ trans('public.about.team_ninja') }}</h2>
        <p>{{ trans('public.about.team_ninja_text') }}</p>
      </div>
    </div>
    <div class="row">
      <div class="col-md-3">
        <div class="img-team">
          <img src="images/shalomstark.jpg" alt="Shalom Stark">
        </div>
        <h2>Shalom Stark</h2>
        <p>{{ trans('public.about.co_founder') }}, {{ trans('public.about.ceo') }}</p>
        <p class="social blue"><a href="https://twitter.com/shalomstark" target="_blank"><img src="images/twitter.svg" alt="Twitter"></a>
          <a href="http://shalomisraeltours.com/" target="_blank"><img src="images/website.svg" alt="Website"></a>
        </p>
        <p>{{ trans('public.about.shalom_bio') }}</p>
      </div>
      <div class="col-md-3">
        <div class="img-team">
          <img src="images/hillelcoren.jpg" alt="Hillel Coren">
        </div>
        <h2>Hillel Coren</h2>
        <p>{{ trans('public.about.co_founder') }}, {{ trans('public.about.cto') }}</p>
        <p class="social green"><a href="https://twitter.com/hillelcoren" target="_blank"><img src="images/twitter.svg" alt="Twitter"></a>
          <a href="http://www.linkedin.com/profile/view?id=105143214" target="_blank"><img src="images/linkedin.svg" alt=""></a>
          <a href="http://hillelcoren.com/" target="_blank"><img src="images/website.svg" alt="Website"></a>
        </p>
        <p>{{ trans('public.about.hillel_bio') }}</p>
      </div>
      <div class="col-md-3">
        <div class="img-team">
          <img src="images/razikantorp.jpg" alt="Razi Kantorp">
        </div>
        <h2>Razi Kantorp-Weglin</h2>
        <p>{{ trans('public.about.designer') }}</p>
        <p class="social red"><a href="https://twitter.com/kantorpweglin" target="_blank"><img src="images/twitter.svg" alt="Twitter"></a>
          <a href="https://www.linkedin.com/pub/razi-kantorp/35/368/973" target="_blank"><img src="images/linkedin.svg" alt=""></a>
          <a href="http://instagram.com/kantorpweglin" target="_blank"><img src="images/instagram.svg" alt="Twitter"></a>
          <a href="http://kantorp-wegl.in/" target="_blank"><img src="images/website.svg" alt="Website"></a>
        </p>
        <p>{{ trans('public.about.razi_bio') }}</p>
      </div>
      <div class="col-md-3">
        <div class="img-team">
          <img src="images/benjacobson.jpg" alt="Ben Jacobsen">
        </div>
        <h2>Ben Jacobson</h2>
        <p>{{ trans('public.about.marketing') }}</p>
        <p class="social yellow"><a href="https://twitter.com/osbennn" target="_blank"><img src="images/twitter.svg" alt="Twitter"></a>
          <a href="http://www.linkedin.com/in/osbennn" target="_blank"><img src="images/linkedin.svg" alt=""></a>
          <a href="http://about.me/osbennn" target="_blank"><img src="images/me.svg" alt="Me"></a>
          <a href="http://actionpackedmedia.com/" target="_blank"><img src="images/website.svg" alt="Website"></a>
        </p>
        <p>{{ trans('public.about.ben_bio') }}</p>
      </div>
    </div>
  </div>
</section>

@stop