@extends('master')

@section('body')
    <div class="navbar navbar-inverse navbar-fixed-top">
      <div class="container">
        <div class="navbar-header">
          <button type="button" class="navbar-toggle" data-toggle="collapse" data-target=".navbar-collapse">
            <span class="icon-bar"></span>
            <span class="icon-bar"></span>
            <span class="icon-bar"></span>
          </button>
          <a class="navbar-brand" href="#">Invoice Ninja</a>
        </div>
        <div class="navbar-collapse collapse">
          {{ Form::open(array('url' => 'login', 'class' => 'navbar-form navbar-right')) }}
            <div class="form-group">
              {{ Form::text('email', Input::old('email'), array('placeholder' => 'Email')) }}
            </div>
            <div class="form-group">
              {{ Form::password('password', array('placeholder' => 'Password')) }}
            </div>
            <button type="submit" class="btn btn-success">Sign in</button>
          {{ Form::close() }}
        </div><!--/.navbar-collapse -->
      </div>
    </div>

    <!-- Main jumbotron for a primary marketing message or call to action -->
    <div class="jumbotron">
      <div class="container">
        <h1>Hello, world!</h1>
        <p>This is a template for a simple marketing or informational website. It includes a large callout called a jumbotron and three supporting pieces of content. Use it as a starting point to create something more unique.</p>
        <p>
          {{ Form::open(array('url' => 'get_started')) }}
          {{ Form::hidden('guest_key') }}
          {{ Button::lg_primary_submit('Get Started &raquo;') }}
          {{ Form::close() }}
        </p>
      </div>
    </div>

    <div class="container">
      <!-- Example row of columns -->
      <div class="row">
        <div class="col-md-4">
          <h2>Heading</h2>
          <p>Donec id elit non mi porta gravida at eget metus. Fusce dapibus, tellus ac cursus commodo, tortor mauris condimentum nibh, ut fermentum massa justo sit amet risus. Etiam porta sem malesuada magna mollis euismod. Donec sed odio dui. </p>
          <p><a class="btn btn-default" href="#" role="button">View details &raquo;</a></p>
        </div>
        <div class="col-md-4">
          <h2>Heading</h2>
          <p>Donec id elit non mi porta gravida at eget metus. Fusce dapibus, tellus ac cursus commodo, tortor mauris condimentum nibh, ut fermentum massa justo sit amet risus. Etiam porta sem malesuada magna mollis euismod. Donec sed odio dui. </p>
          <p><a class="btn btn-default" href="#" role="button">View details &raquo;</a></p>
       </div>
        <div class="col-md-4">
          <h2>Heading</h2>
          <p>Donec sed odio dui. Cras justo odio, dapibus ac facilisis in, egestas eget quam. Vestibulum id ligula porta felis euismod semper. Fusce dapibus, tellus ac cursus commodo, tortor mauris condimentum nibh, ut fermentum massa justo sit amet risus.</p>
          <p><a class="btn btn-default" href="#" role="button">View details &raquo;</a></p>
        </div>
      </div>

      <hr>

      <footer>
        <p>&copy; Company 2013</p>
      </footer>

    <script type="text/javascript">

      $(function() {

        function isStorageSupported() {
          try {
              return 'localStorage' in window && window['localStorage'] !== null;
          } catch (e) {
              return false;
          }
        }

        if (isStorageSupported()) {
          @if (Session::get('clearGuestKey'))
              localStorage.setItem('guest_key', '');
          @else
              $('[name="guest_key"]').val(localStorage.getItem('guest_key'));
          @endif
        }
        

      });

  </script>

</div>

@stop