@extends('public.header') 

@section('content')

<section class="hero background hero-faq" data-speed="2" data-type="background">
    <div class="container">
        <div class="row">
            <h1>{{ trans('public.faq.header') }}</h1>
        </div>
    </div>
</section>

<section class="faq">
    <div class="container">
        <div class="row">
            <div class="col-md-12">
                <div class="question">
                    <a class="expander" href="#">{{ trans('public.faq.question1') }}
                    </a>
                    <div class="content">
                        <p>{{ trans('public.faq.answer1') }}</p>
                    </div>
                </div>
                <div class="question">
                    <a class="expander" href="#">{{ trans('public.faq.question2') }}
                    </a>
                    <div class="content">
                        <p>{{ trans('public.faq.answer2') }}
                        </p>
                    </div>
                </div>
                <div class="question">
                    <a class="expander" href="#">{{ trans('public.faq.question3') }}
                    </a>
                    <div class="content">
                        <p>{{ trans('public.faq.answer3') }}
                        </p>
                    </div>
                </div>
                <div class="question">
                    <a class="expander" href="#">{{ trans('public.faq.question4') }}
                    </a>
                    <div class="content">
                        <p>{{ trans('public.faq.answer4') }}
                        </p>
                    </div>
                </div>
                <div class="question">
                    <a class="expander" href="#">{{ trans('public.faq.question5') }}
                    </a>
                    <div class="content">
                        <p>{{ trans('public.faq.answer5') }}</p>
                    </div>
                </div>
                <div class="question">
                    <a class="expander" href="#">{{ trans('public.faq.question6') }}
                    </a>
                    <div class="content">
                        <p>{{ trans('public.faq.answer6') }}
                        </p>
                    </div>
                </div>
                <div class="question">
                    <a class="expander" href="#">{{ trans('public.faq.question7') }}
                    </a>
                    <div class="content">
                        <p>{{ trans('public.faq.answer7') }}
                        </p>
                    </div>
                 </div>
                <div class="question">
                    <a class="expander" href="#">{{ trans('public.faq.question8') }}
                    </a>
                    <div class="content">
                        <p>{{ trans('public.faq.answer8') }}
                        </p>
                    </div>
                </div>
                <div class="question">
                    <a class="expander" href="#">{{ trans('public.faq.question9') }}
                    </a>
                    <div class="content">
                            <p>{{ trans('public.faq.answer9') }}
                        </p>
                    </div>
                </div>
                <div class="question">
                    <a class="expander" href="#">{{ trans('public.faq.question10') }}
                    </a>
                    <div class="content">
                            <p>{{ trans('public.faq.answer10') }}
                        </p>
                    </div>
                </div>
                @if (Utils::getDemoAccountId())
                <div class="question">
                    <a class="expander" href="#">{{ trans('public.faq.question11') }}</a>
                    <div class="content">
                            <p>{{ trans('public.faq.answer11') }}
                        </p>
                    </div>
                </div>                
                @endif
                <div class="question">
                    <a class="expander" href="#">{{ trans('public.faq.question12') }}
                    </a>
                    <div class="content">
                            <p>{{ trans('public.faq.answer12') }}
                        </p>
                    </div>
                </div>
                <div class="question">
                    <a class="expander" href="#">{{ trans('public.faq.question13') }}
                    </a>
                    <div class="content">
                            <p>{{ trans('public.faq.answer13') }}
                        </p>
                    </div>
                </div>
                <div class="question">
                    <a class="expander" href="#">{{ trans('public.faq.question14') }}
                    </a>
                    <div class="content">
                        <p>{{ trans('public.faq.answer14') }}
                        </p>
                    </div>
                </div>
            </div>
        </div>
        <div class="row">
            <div class="col-md-12">
                <div class="contact-box">
                    <div class="row">
                        <div class="col-md-4">
                            <img src="{{ asset('images/icon-faq.png') }}">
                            <h2>{{ trans('public.faq.miss_something') }}</h2>
                        </div>
                        <div class="col-md-8 valign">
                            <p>{{ trans('public.faq.miss_something_text') }}
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

@stop