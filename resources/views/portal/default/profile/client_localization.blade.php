<div class="row">

  <div class="col-sm-12">

  {!! Former::framework('TwitterBootstrap4'); !!}

  {!! Former::horizontal_open()
        ->id('update_settings')
        ->route('client.profile.edit_localization', auth()->user()->hashed_id)
        ->method('PUT');	!!}
  
  {!! Former::populate(auth()->user()->client->settings) !!}

  @csrf

    <div class="card">

      <div class="card-header">
        <strong> {{ ctrans('texts.localization') }} </strong>
      </div>

      <div class="card-body">
          
          {!! Former::text('timezone_id')->label( ctrans('texts.timezone_id')) !!}
          {!! Former::text('language_id')->label( ctrans('texts.language')) !!}
          {!! Former::text('date_format')->label( ctrans('texts.date_format')) !!}
          {!! Former::text('datetime_format')->label( ctrans('texts.datetime_format')) !!}

          <button class="btn btn-primary pull-right">{{ ctrans('texts.save') }}</button>

      </div>
  
  {!! Former::close() !!}

    </div>
