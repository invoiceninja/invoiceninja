<div class="row">

  <div class="col-sm-12">

  {!! Former::framework('TwitterBootstrap4'); !!}

  {!! Former::horizontal_open()
        ->id('update_settings')
        ->route('client.profile.update_settings', auth()->user()->hashed_id)
        ->method('PUT');	!!}
  
  @csrf

    <div class="card">

      <div class="card-header">
        {{ ctrans('texts.client_information') }}
      </div>

      <div class="card-body">
                <button class="btn btn-primary pull-right">{{ ctrans('texts.save') }}</button>

      </div>
  
  {!! Former::close() !!}

    </div>


          