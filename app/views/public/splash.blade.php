@extends('public.header')

@section('content') 

<link href="{{ asset('css/customCss.css') }}?no_cache={{ NINJA_VERSION }}" rel="stylesheet" type="text/css"/>
<section class="hero background hero1 center" data-speed="2" data-type="background">
  <div class="caption-side"></div>

  <div class="container">
    <div class="row" style="margin:0;">
      <div class="caption-wrap">
        <div class="caption">
          <h1>{{ trans('public.home.header') }}</h1>
          <p>{{ trans('public.home.sub_header') }}</p>
        </div>
      </div>
    </div>
  </div>

  
  <div class="container">
    <div class="row">
      <div class="col-md-3 center-block">
        <a href="#">
          <div class="cta">
            <h2 id="startButton" onclick="return getStarted()">{{ trans('public.invoice_now') }} <span>+</span></h2>
          </div>
        </a>
      </div>
    </div>
  </div>

  
</section>

<section class="features-splash">
  <div class="container">
    <div class="row">
      <div class="col-md-3 one">
        <div class="box">
          <div class="icon free"><span class="img-wrap"><img src="{{ asset('images/icon-free.png') }}"></span></div>
          <h2>{{ trans('public.home.free_always') }}</h2>
          <p>{{ trans('public.home.free_always_text') }}</p>              
        </div>
      </div>

      <div class="col-md-3 two">
        <div class="box">
          <div class="icon open"><span class="img-wrap"><img src="{{ asset('images/icon-opensource.png') }}"></span></div>
          <h2>{{ trans('public.home.open_source') }}</h2>
          <p>{{ trans('public.home.open_source_text') }}</p>              
        </div>
      </div>

      <div class="col-md-3 three">
        <div class="box">
          <div class="icon pdf"><span class="img-wrap"><img src="{{ asset('images/icon-pdf.png') }}"></span></div>
          <h2>{{ trans('public.home.live_pdf') }}</h2>
          <p>{{ trans('public.home.live_pdf_text') }}</p>              
        </div>
      </div>

      <div class="col-md-3 four">
        <div class="box">
          <div class="icon pay"><span class="img-wrap"><img src="{{ asset('images/icon-payment.png') }}"></span></div>
          <h2>{{ trans('public.home.online_payments') }}</h2>
          <p>{{ trans('public.home.online_payments_text') }}</p>              
          <p></p>
        </div>
      </div>
    </div>
  </div>
</section>
<section class="features-splash customContainer">
	      <div class="container">
	      	
	      	<div class="row col-md-12">
	      		<div class="col-md-5 col-md-offset-4 customFontHead">
	      			<div class="col-md-12">
	      				<span class="glyphicon glyphicon-ok"></span>
	      			</div>
	        		<div class="col-md-12 customTextBorder">
	        			Invoice Ninja Does Everything You'd Expect Your Online Invoicing App to Do
	        		</div>
	        	</div>
	       </div>
	        <div class="row">
	        
	          <div class="col-md-2 customMenuOne">
	          		<div class="customMenuDiv">
	          			<span class="img-wrap shiftLeft" ><img src="{{ asset('images/InvoiceClientsViaEmail.png') }}"></span>
	          			<span class="customSubMenu shiftLeft"> Invoice clients via email </span>
	          		</div>
	              	<div class="customMenuDiv" >
	          			<span class="img-wrap shiftLeft" ><img src="{{ asset('images/InTuitiveEditingInterface.png') }}"></span>
	          			<span class="customSubMenu shiftLeft"> Intuitive editing interface</span>
	          		</div>
	              
	                <div class="customMenuDiv" >
	          			<span class="img-wrap shiftLeft" ><img src="{{ asset('images/PrintablePDFInvoices.png') }}"></span>
	          			<span class="customSubMenu shiftLeft"> Printable .pdf invoices</span>
	          		</div>
	          		<div class="customMenuDiv" >
	          			<span class="img-wrap shiftLeft" ><img src="{{ asset('images/MultipleTaxSettings.png') }}"></span>
	          			<span class="customSubMenu shiftLeft"> Multiple tax settings</span>
	          		</div>
	          		<div class="customMenuDiv" >
	          			<span class="img-wrap shiftLeft" ><img src="{{ asset('images/ImportExportRecords.png') }}"></span>
	          			<span class="customSubMenu shiftLeft"> Import/export records </span>
	          		</div>
	          </div>
	
	         <div class="col-md-2 customMenuOne">
	          		<div class="customMenuDiv">
	          			<span class="img-wrap shiftLeft" ><img src="{{ asset('images/AcceptPaymentsOnline.png') }}"></span>
	          			<span class="customSubMenu shiftLeft"> Accept payments online</span>
	          		</div>
	              	<div class="customMenuDiv" >
	          			<span class="img-wrap shiftLeft" ><img src="{{ asset('images/BestInClassSecurity.png') }}"></span>
	          			<span class="customSubMenu shiftLeft"> Best-in-class data security </span>
	          		</div>
	              
	                <div class="customMenuDiv" >
	          			<span class="img-wrap shiftLeft" ><img src="{{ asset('images/AdjustablePaymentTerms.png') }}"></span>
	          			<span class="customSubMenu shiftLeft"> Adjustable payment terms </span>
	          		</div>
	          		<div class="customMenuDiv" >
	          			<span class="img-wrap shiftLeft" ><img src="{{ asset('images/MultipleCurrencySupport.png') }}"></span>
	          			<span class="customSubMenu shiftLeft"> Multiple currency support </span>
	          		</div>
	          		<div class="customMenuDiv" >
	          			<span class="img-wrap shiftLeft" ><img src="{{ asset('images/RecurringInvoiceProfiles.png') }}"></span>
	          			<span class="customSubMenu shiftLeft"> Recurring invoice profiles </span>
	          		</div>
	          </div>
	
	           <div class="col-md-2 customMenuOne">
	          		<div class="customMenuDiv">
	          			<span class="img-wrap shiftLeft" ><img src="{{ asset('images/FreeInvoicing.png') }}"></span>
	          			<span class="customSubMenu shiftLeft"> Free invoicing platform </span>
	          		</div>
	              	<div class="customMenuDiv" >
	          			<span class="img-wrap shiftLeft" ><img src="{{ asset('images/EstimatesProForma.png') }}"></span>
	          			<span class="customSubMenu shiftLeft"> Estimates & pro-forma </span>
	          		</div>
	              
	                <div class="customMenuDiv" >
	          			<span class="img-wrap shiftLeft" ><img src="{{ asset('images/PaymentTracking.png') }}"></span>
	          			<span class="customSubMenu shiftLeft"> Payment tracking </span>
	          		</div>
	          		<div class="customMenuDiv" >
	          			<span class="img-wrap shiftLeft" ><img src="{{ asset('images/MultilingualSupport.png') }}"></span>
	          			<span class="customSubMenu shiftLeft"> Multilingual support </span>
	          		</div>
	          		<div class="customMenuDiv" >
	          			<span class="img-wrap shiftLeft" ><img src="{{ asset('images/SelfHostedAvailable.png') }}"></span>
	          			<span class="customSubMenu shiftLeft"> Self-hosted available </span>
	          		</div>
	          </div>
	
	           <div class="col-md-2 customMenuOne">
	          		<div class="customMenuDiv">
	          			<span class="img-wrap shiftLeft" ><img src="{{ asset('images/LivePDFCreation.png') }}"></span>
	          			<span class="customSubMenu shiftLeft"> Live .pdf creation </span>
	          		</div>
	              	<div class="customMenuDiv" >
	          			<span class="img-wrap shiftLeft" ><img src="{{ asset('images/CloneInvoices.png') }}"></span>
	          			<span class="customSubMenu shiftLeft"> Clone invoices </span>
	          		</div>
	              
	                <div class="customMenuDiv" >
	          			<span class="img-wrap shiftLeft" ><img src="{{ asset('images/CloudBasedApp.png') }}"></span>
	          			<span class="customSubMenu shiftLeft"> Cloud-based app </span>
	          		</div>
	          		<div class="customMenuDiv" >
	          			<span class="img-wrap shiftLeft" ><img src="{{ asset('images/OpenSource.png') }}"></span>
	          			<span class="customSubMenu shiftLeft"> Open source </span>
	          		</div>
	          		<div class="customMenuDiv" >
	          			<span class="img-wrap shiftLeft" ><img src="{{ asset('images/BeautifulTemplates.png') }}"></span>
	          			<span class="customSubMenu shiftLeft"> Beautiful templates </span>
	          		</div>
	          </div>
	              
	           <div class="col-md-4 customMenuOne">
	          		<div class="customMenuDiv">
	          			<span class="img-wrap shiftLeft" ><img src="{{ asset('images/CustomizeInvoices.png') }}"></span>
	          			<span class="customSubMenu shiftLeft"> Cutomize invoices with your company logo </span>
	          		</div>
	              	<div class="customMenuDiv" >
	          			<span class="img-wrap shiftLeft" ><img src="{{ asset('images/PersonalizeInvoiceColor.png') }}"></span>
	          			<span class="customSubMenu shiftLeft"> Personalize invoice color schemes </span>
	          		</div>
	              
	                <div class="customMenuDiv" >
	          			<span class="img-wrap shiftLeft" ><img src="{{ asset('images/InvoiceClientsViaEmail.png') }}"></span>
	          			<span class="customSubMenu shiftLeft"> Alerts when invoices are viewed or paid </span>
	          		</div>
	          		<div class="customMenuDiv" >
	          			<span class="img-wrap shiftLeft" ><img src="{{ asset('images/IntegrateWithPaymentGateways.png') }}"></span>
	          			<span class="customSubMenu shiftLeft"> Integrate with top payment gateways </span>
	          		</div>
	          		<div class="customMenuDiv" >
	          			<span class="img-wrap shiftLeft" ><img src="{{ asset('images/ManualAutomaticInvoiceNumbers.png') }}"></span>
	          			<span class="customSubMenu shiftLeft"> Manual or automatic invoice numbers </span>
	          		</div>
	          </div>
	              
	            </div>
	          </div>
	        </section>

<section class="blue">
  <div class="container">
    <div class="row">
      <div class="col-md-5">
       <h1>{{ trans('public.home.footer') }}</h1>
       <div class="row">
        <div class="col-md-7">
          <a href="#">
            <div class="cta">
              <h2 onclick="return getStarted()">{{ trans('public.invoice_now') }} <span>+</span></h2>
            </div>
          </a>

        </div>
      </div>
      <p>{{ trans('public.no_signup_needed') }}</p>
    </div>
    <div class="col-md-7">
      <img src="{{ asset('images/devices.png') }}">
    </div>
  </div>
</div>
</section>


@stop