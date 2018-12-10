@extends('layouts.master', ['header' => $header])

@section('body')
<main class="main" id="client_edit">

    <!-- Breadcrumb-->
    {{ Breadcrumbs::render('clients.edit', $client) }}

    <vue-toastr ref="toastr"></vue-toastr>

    <div class="container-fluid">

        <div class="col" style="padding: 0px;">
            <ul class="nav nav-pills mb-1" id="pills-tab" role="tablist">
                <li class="nav-item">
                    <a class="nav-link active show" id="pills-home-tab" data-toggle="pill" href="#pills-home" role="tab" aria-controls="pills-home" aria-selected="true"><i class="icon-user"></i> Client</a>
                </li>

                <li class="nav-item">
                    <a class="nav-link" id="pills-contact-tab" data-toggle="pill" href="#pills-contact" role="tab" aria-controls="pills-contact" aria-selected="false"><i class="icon-settings"></i> Settings</a>
                </li>
            </ul>

        <div class="tab-content" id="pills-tabContent">

            <div class="tab-pane fade active show" id="pills-home" role="tabpanel" aria-labelledby="pills-home-tab" style="background-color: #e4e5e6; padding: 0px;">

                <form @submit.prevent="onSubmit" @keydown="form.errors.clear($event.target.name)">

                    <div class="row">
                        <!-- Client Details and Address Column -->
                        <div class="col-md-6">
                                            
                            @include('client.partial.client_details', $client)                               

                            @include('client.partial.client_location')

                        </div>
                        <!-- End Client Details and Address Column -->

                        <!-- Contact Details Column -->
                        <div class="col-md-6">

                            <div class="card">

                                <div class="card-header bg-primary2">{{ trans('texts.contact_information') }}

                                    <span class="float-right">

                                        <button type="button" class="btn btn-primary btn-sm" @click="add()"><i class="fa fa-plus-circle"></i> {{ trans('texts.add_contact') }}</button>

                                    </span>

                                </div>
                                
                                <template v-for="(contact, key, index) in form.contacts">

                                    @include('client.partial.contact_details')

                                </template>
                            
                            </div>    

                        </div>     
                        <!-- End Contact Details Column --> 
                    </div> 

                    <div class="row"> 

                        <div class="col-md-12 text-center">

                            <button class="btn btn-lg btn-success" type="button" @click="onSubmit"><i class="fa fa-save"></i> {{ trans('texts.save') }}</button>

                        </div>

                    </div>    

                </form>

            </div>

            <div class="tab-pane fade" id="pills-contact" role="tabpanel" aria-labelledby="pills-contact-tab">


            </div>

        </div>

    </div>

</main>

<script>
    var client_object = {!! $client !!};
    var hashed_id = '{{ $hashed_id }}';
</script>

<script defer src=" {{ mix('/js/client_edit.min.js') }}"></script>
@endsection