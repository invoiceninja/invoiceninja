@extends('portal.ninja2020.layout.app')

@section('header')
    <div class="bg-white shadow rounded mb-4" translate>
        <div class="px-4 py-5 sm:p-6">
            <div class="sm:flex sm:items-start sm:justify-between">
                <div>
                    <h3 class="text-lg leading-6 font-medium text-gray-900">
                        {{ ctrans('texts.dashboard') }}
                    </h3>
                    <div class="mt-2 max-w-xl text-sm leading-5 text-gray-500">
                        <p>
                            Quick overview and statistics.
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection


@section('body')
    Lorem ipsum dolor sit amet, consectetur adipisicing elit. Amet esse magnam nam numquam omnis optio, pariatur
    perferendis quae quaerat quam, quas quos repellat sapiente sit soluta, tenetur totam ut vel veritatis voluptatibus?
    Aut, dolor illo? Asperiores eum eveniet quae sed?
@endsection
