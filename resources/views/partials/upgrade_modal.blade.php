<style>

  #upgrade-modal {
    display: none;
    position: absolute;
    z-index: 999999;
    #background-color: rgba(76,76,76,.99);
    background-color: rgba(0,0,0,.9);
    text-align: center;
    width: 100%;
    height: 100%;
    min-height: 1500px;
  }

  #upgrade-modal h1 {
    font-family: 'roboto-thin', 'roboto', Helvetica, arial, sans-serif;
    font-size: 28px!important;
    padding: 0 0 25px 0;
    margin: 0!important;
    color: #fff;
    padding-top: 0px;
    padding-bottom: 20px;
    font-weight: 800;
  }

  #upgrade-modal h2 {
  	font-family: 'roboto-thin', 'roboto', Helvetica, arial, sans-serif;
  	color: #36c157;
  	font-size: 34px;
  	line-height: 15px;
    padding-bottom: 4px;
  	margin: 0;
  	font-weight: 100;
  }

  #upgrade-modal h3 {
  	font-family: 'roboto-thin', 'roboto', Helvetica, arial, sans-serif;
  	margin: 20px 0 25px 0;
  	font-size: 75px;
  	padding: 0 0 8px 0;
  	color: #fff;
    font-weight: 100;
  }

  #upgrade-modal h3 span.upgrade_frequency {
  	font-size: 17px;
  	text-transform: uppercase;
  	letter-spacing: 2px;
  	vertical-align: super;
  }

  #upgrade-modal h4 {
      color: white;
  }

  #upgrade-modal ul {
  	list-style: none;
  	color: #fff;
  	padding: 20px 0;
  }

  #upgrade-modal .col-md-4 {
  	padding:75px 20px;
  	border-right: 0;
  }

  #upgrade-modal .col-md-offset-2 {
    border-top: 1px solid #343333;
    border-right: 1px solid #343333;
  }

  #upgrade-modal .columns {
    border-top: 1px solid #343333;
  }

  #upgrade-modal ul {
    border-top: 1px solid #343333;
  }

  #upgrade-modal ul li {
		font-size: 17px;
		line-height: 35px;
    font-weight: 400;
	}

  #upgrade-modal p.subhead {
  	font-size: 15px;
  	margin: 5px 0 5px 0;
    padding-top: 10px;
    padding-bottom: 10px;
    font-weight: 400;
    color: #fff;
  }

  #upgrade-modal .btn {
    width: 260px;
    padding: 16px 0 16px 0;
  }

  #upgrade-modal i.fa-close {
    cursor: pointer;
    color: #fff;
    font-size: 26px !important;
    padding-top: 30px;
  }

  #upgrade-modal label.radio-inline {
    padding: 0px 30px 30px 30px;
    font-size: 22px;
    color: #fff;
    vertical-align: middle;
  }

  #upgrade-modal select {
    vertical-align: top;
    width: 140px;
  }

</style>

{!! Former::open('settings/change_plan')->addClass('upgrade-form') !!}

<span style="display:none">
{!! Former::text('plan') !!}
</span>

<div id="upgrade-modal" class="container" style="">
<div class="row">
<div class="col-md-10 text-right">
  <a href="#"><i class="fa fa-close" onclick="hideUpgradeModal()" title="{{ trans('texts.close') }}"></i></a>
</div>
</div>
<div class="row">
<div class="col-md-12 text-center">
  <h1>{{ trans('texts.upgrade_for_features') }}</h1>
  <h4 onclick="updateUpgradePrices()">
    <label for="plan_term_month" class="radio-inline">
      <input value="month" id="plan_term_month" type="radio" name="plan_term" checked>Monthly</label>
    <label for="plan_term_year" class="radio-inline">
      <input value="year" id="plan_term_year" type="radio" name="plan_term">Annually</label>
  </h4>
  @if (Auth::user()->account->company->hasActivePromo())
    <h4>{{ Auth::user()->account->company->present()->promoMessage }}</h4><br/>
  @endif
</div>
<div class="col-md-4 col-md-offset-2 text-center">
  <h2>{{ trans('texts.pro_upgrade_title') }}</h2>
  <p class="subhead">{{ trans('texts.pay_annually_discount') }}</p>
  <img width="65" src="{{ asset('images/pro_plan/border.png') }}"/>
  <h3>$<span id="upgrade_pro_price">{{ PLAN_PRICE_PRO_MONTHLY }}</span> <span class="upgrade_frequency">/ {{ trans('texts.plan_term_month') }}</span></h3>
  <select style="visibility:hidden">
  </select>
  <p>&nbsp;</p>
  <ul>
    <li>{{ trans('texts.pro_upgrade_feature1') }}</li>
    <li>{{ trans('texts.pro_upgrade_feature2') }}</li>
    <li>{{ trans('texts.much_more') }}</li>
  </ul>
  {!! Button::success(trans('texts.go_ninja_pro'))->withAttributes(['onclick' => 'submitUpgradeForm("pro")'])->large() !!}
</div>
<div class="col-md-4 columns text-center">
  <h2>{{ trans('texts.plan_enterprise') }}</h2>
  <p class="subhead">{{ trans('texts.pay_annually_discount') }}</p>
  <img width="65" src="{{ asset('images/pro_plan/border.png') }}"/>
  <h3>$<span id="upgrade_enterprise_price">{{ PLAN_PRICE_ENTERPRISE_MONTHLY_2 }}</span> <span class="upgrade_frequency">/ {{ trans('texts.plan_term_month') }}</span></h3>
  <select name="num_users" id="upgrade_num_users" onchange="updateUpgradePrices()">
      <option value="2">1 to 2 {{ trans('texts.users') }}</option>
      <option value="5">3 to 5 {{ trans('texts.users') }}</option>
      <option value="10">6 to 10 {{ trans('texts.users') }}</option>
  </select>
  <p>&nbsp;</p>
  <ul>
    <li>{{ trans('texts.enterprise_upgrade_feature1') }}</li>
    <li>{{ trans('texts.enterprise_upgrade_feature2') }}</li>
    <li>{{ trans('texts.much_more') }}</li>
  </ul>
  {!! Button::success(trans('texts.go_enterprise'))->withAttributes(['onclick' => 'submitUpgradeForm("enterprise")'])->large() !!}
</div>
</div>
</div>

{!! Former::close() !!}

<script type="text/javascript">

  function showUpgradeModal() {
    @if ( ! Auth::check() || ! Auth::user()->confirmed)
        swal("{!! trans('texts.confirmation_required') !!}");
        return;
    @endif

    $(window).scrollTop(0);
    $('#upgrade-modal').fadeIn();
  }

  function hideUpgradeModal() {
    $('#upgrade-modal').fadeOut();
  }

  function updateUpgradePrices() {
    var planTerm = $('input[name=plan_term]:checked').val();
    var numUsers = $('#upgrade_num_users').val();
    if (planTerm == 'month') {
      var proPrice = {{ PLAN_PRICE_PRO_MONTHLY }};
      if (numUsers == 2) {
          var enterprisePrice = {{ PLAN_PRICE_ENTERPRISE_MONTHLY_2 }};
      } else if (numUsers == 5) {
          var enterprisePrice = {{ PLAN_PRICE_ENTERPRISE_MONTHLY_5 }};
      } else if (numUsers == 10) {
          var enterprisePrice = {{ PLAN_PRICE_ENTERPRISE_MONTHLY_10 }};
      }
      var label = "{{ trans('texts.freq_monthly') }}";
    } else {
      var proPrice = {{ PLAN_PRICE_PRO_MONTHLY * 10 }};
      if (numUsers == 2) {
          var enterprisePrice = {{ PLAN_PRICE_ENTERPRISE_MONTHLY_2 * 10 }};
      } else if (numUsers == 5) {
          var enterprisePrice = {{ PLAN_PRICE_ENTERPRISE_MONTHLY_5 * 10 }};
      } else if (numUsers == 10) {
          var enterprisePrice = {{ PLAN_PRICE_ENTERPRISE_MONTHLY_10 * 10 }};
      }
      var label = "{{ trans('texts.freq_annually') }}";
    }
    @if (Auth::user()->account->company->hasActivePromo())
        proPrice = proPrice - (proPrice * {{ Auth::user()->account->company->discount }});
        enterprisePrice = enterprisePrice - (enterprisePrice * {{ Auth::user()->account->company->discount }});
    @endif
    $('#upgrade_pro_price').text(proPrice);
    $('#upgrade_enterprise_price').text(enterprisePrice);
    $('span.upgrade_frequency').text(label);
  }

  function submitUpgradeForm(plan) {
    $('#plan').val(plan);
    $('.upgrade-form').submit();
  }

  $(function() {

    @if (Auth::user()->account->company->hasActivePromo())
        updateUpgradePrices();
    @endif

    $(document).keyup(function(e) {
         if (e.keyCode == 27) { // escape key maps to keycode `27`
            hideUpgradeModal();
        }
    });
  })

</script>
