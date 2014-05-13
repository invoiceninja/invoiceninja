@extends('public.header')

@section('content')

<section class="hero background hero-secure center" data-speed="2" data-type="background">
  <div class="container">
    <div class="row">
      <h1>Secure Payment</h1>
      <p class="thin"><img src="{{ asset('images/icon-secure-pay.png') }}">256-BiT Encryption</p>
      <img src="{{ asset('images/providers.png') }}">
    </div>
  </div>
</section>

<section class="secure">
  <div class="container">
    <div id="secure-form" class="row">          
      <div class="col-md-7 info">
        <form>
          <div class="row">
            <div class="form-group col-md-6">
              <label for="firstname">First Name</label>
              <input type="text" class="form-control" id="firstname" name="firstname">
              <span class="help-block" style="display: none;">Please enter your first name.</span>

            </div>
            <div class="form-group col-md-6">
              <label for="lastname">Last name</label>
              <input type="text" class="form-control" id="lastname" name="lastname">
              <span class="help-block" style="display: none;">Please enter your last name.</span>
            </div>
          </div>

          <div class="row">
            <div class="form-group col-md-12">
              <label for="streetadress">Street Address</label>
              <input type="text" class="form-control" id="streetadress" name="streetadress">
              <span class="help-block" style="display: none;">Please enter addess.</span>

            </div>
          </div>

          <div class="row">
            <div class="form-group col-md-3">
              <label for="apt">Apt/Ste</label>
              <input type="text" class="form-control" id="apt" name="apt">
              <span class="help-block" style="display: none;">Please enter your Apt/Ste.</span>
            </div>

            <div class="form-group col-md-3">
              <label for="apt">City</label>
              <input type="text" class="form-control" id="city" name="city">
              <span class="help-block" style="display: none;">Please enter your city.</span>
            </div>

            <div class="form-group col-md-3">
              <label for="apt">State/Province</label>
              <input type="text" class="form-control" id="state" name="state">
              <span class="help-block" style="display: none;">Please enter your State/Province.</span>
            </div>

            <div class="form-group col-md-3">
              <label for="apt">Postal Code</label>
              <input type="text" class="form-control" id="postal" name="postal">
              <span class="help-block" style="display: none;">Please enter your Postal Code.</span>
            </div>
          </div>

        </div>
        <div class="col-md-5">
          <div class="card">
            <div class="row">
              <div class="form-group col-md-12">
                <label for="streetadress">Card number</label>
                <input type="text" class="form-control with-icon" id="cardnumber" name="cardnumber">
                <span class="glyphicon glyphicon-lock"></span>
                <span class="help-block" style="display: none;">Please enter your card number.</span>

              </div>
            </div>
            <div class="row">
              <div class="form-group col-md-6">
                <label for="firstname">Expiration Month</label>
                <select class="form-control" id="month" name="month">
                  <option>January</option>
                </select>
                <span class="help-block" style="display: none;">Please select the month.</span>

              </div>
              <div class="form-group col-md-6">
                <label for="firstname">Expiration year</label>
                <select class="form-control" id="year" name="year">
                  <option>2016</option>
                </select>
                <span class="help-block" style="display: none;">Please select the year.</span>

              </div>
            </div>


            <div class="row">
              <div class="form-group col-md-6">
                <label for="apt">CVV</label>
                <input type="text" class="form-control" id="cvv" name="cvv">
                <span class="help-block" style="display: none;">Please enter the CVV.</span>
              </div>

              <div class="col-md-6">
                <p><span class="glyphicon glyphicon-credit-card" style="margin-right: 10px;"></span><a href="#">Where Do I find CVV?</a></p>
              </div>
            </div>
          </div>


        </div>
      </div>
      <div class="row">
        <div class="col-md-12">
          <button type="submit" id="feedbackSubmit" class="btn btn-primary btn-lg green">PAY NOW - $2.00</button>
        </div>
      </form>
    </div>
  </div>
</div>


</section>


@stop