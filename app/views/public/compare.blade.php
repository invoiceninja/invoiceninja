@extends('public.header')

@section('content')

<section class="hero background hero-plans" data-speed="2" data-type="background">
 <div class="container">
  <div class="row">
    <h1><img src="{{ asset('images/icon-plans.png') }}">{{ trans('public.compare.header') }}</h1>
  </div>
</div>
</section>

<section class="plans center">
  <div class="container">
    <div class="row">
      <div class="col-md-12">
        <h2>{{ trans('public.compare.free_plan_comparison') }}</h2>
        <table class="table compare-table compare-table-free">
          <thead>
            <tr>
              <th>{{ trans('public.compare.app') }}</th>
              <th>{{ trans('public.compare.cost') }}</th>
              <th>{{ trans('public.compare.clients') }}</th>
              <th>{{ trans('public.compare.invoices') }}</th>
              <th>{{ trans('public.compare.payment_gateways') }}</th>
              <th>{{ trans('public.compare.custom_logo') }}</th>
              <th>{{ trans('public.compare.multiple_templates') }}</th>
              <th>{{ trans('public.compare.recurring_payments') }}</th>
              <th>{{ trans('public.compare.open_source') }}</th>
            </tr>            
          </thead>
          <tbody>
            <tr class="active">
              <td><b><a href="https://www.invoiceninja.com" target="_blank">Invoice Ninja</a></b></td>
              <td>{{ trans('public.compare.free') }}</td>
              <td>500</td>
              <td>{{ trans('public.compare.unlimited') }}</td>
              <td>23</td>
              <td><span class="glyphicon glyphicon-ok"/></td>
              <td><span class="glyphicon glyphicon-ok"/></td>
              <td><span class="glyphicon glyphicon-ok"/></td>
              <td><span class="glyphicon glyphicon-ok"/></td>
            </tr>            
            <tr>
              <td><a href="http://www.freshbooks.com" target="_blank" rel="nofollow">FreshBooks</a></td>
              <td>{{ trans('public.compare.free') }}</td>
              <td>3</td>
              <td>{{ trans('public.compare.unlimited') }}</td>
              <td>13</td>
              <td><span class="glyphicon glyphicon-ok"/></td>
              <td><span class="glyphicon glyphicon-ok"/></td>
              <td><span class="glyphicon glyphicon-ok"/></td>
              <td><span class="glyphicon glyphicon-remove"/></td>              
            </tr>            
            <tr>
              <td><a href="http://www.waveapps.com" target="_blank" rel="nofollow">Wave</a></td>
              <td>{{ trans('public.compare.free') }}</td>
              <td>{{ trans('public.compare.unlimited') }}</td>
              <td>{{ trans('public.compare.unlimited') }}</td>
              <td>1</td>
              <td><span class="glyphicon glyphicon-ok"/></td>
              <td><span class="glyphicon glyphicon-ok"/></td>
              <td><span class="glyphicon glyphicon-ok"/></td>
              <td><span class="glyphicon glyphicon-remove"/></td>              
            </tr>            
            <tr>
              <td><a href="http://www.nutcache.com" target="_blank" rel="nofollow">NutCache</a></td>
              <td>{{ trans('public.compare.free') }}</td>
              <td>{{ trans('public.compare.unlimited') }}</td>
              <td>{{ trans('public.compare.unlimited') }}</td>
              <td>3</td>
              <td><span class="glyphicon glyphicon-ok"/></td>
              <td><span class="glyphicon glyphicon-remove"/></td>
              <td><span class="glyphicon glyphicon-remove"/></td>
              <td><span class="glyphicon glyphicon-remove"/></td>              
            </tr>            
            <tr>
              <td><a href="http://curdbee.com/" target="_blank" rel="nofollow">CurdBee</a></td>
              <td>{{ trans('public.compare.free') }}</td>
              <td>{{ trans('public.compare.unlimited') }}</td>
              <td>{{ trans('public.compare.unlimited') }}</td>
              <td>3</td>
              <td><span class="glyphicon glyphicon-ok"/></td>
              <td><span class="glyphicon glyphicon-remove"/></td>
              <td><span class="glyphicon glyphicon-remove"/></td>
              <td><span class="glyphicon glyphicon-remove"/></td>              
            </tr>            
            <tr>
              <td><a href="https://www.zoho.com/invoice/" target="_blank" rel="nofollow">Zoho Invoice</a></td>
              <td>{{ trans('public.compare.free') }}</td>
              <td>5</td>
              <td>{{ trans('public.compare.unlimited') }}</td>
              <td>6</td>
              <td><span class="glyphicon glyphicon-ok"/></td>
              <td><span class="glyphicon glyphicon-ok"/></td>
              <td><span class="glyphicon glyphicon-ok"/></td>
              <td><span class="glyphicon glyphicon-remove"/></td>              
            </tr>            
            <tr>
              <td><a href="http://www.roninapp.com/" target="_blank" rel="nofollow">Ronin</a></td>
              <td>{{ trans('public.compare.free') }}</td>
              <td>2</td>
              <td>{{ trans('public.compare.unlimited') }}</td>
              <td>1</td>
              <td><span class="glyphicon glyphicon-ok"/></td>
              <td><span class="glyphicon glyphicon-remove"/></td>
              <td><span class="glyphicon glyphicon-ok"/></td>
              <td><span class="glyphicon glyphicon-remove"/></td>              
            </tr>            
            <tr>
              <td><a href="http://invoiceable.co/" target="_blank" rel="nofollow">Invoiceable</a></td>
              <td>{{ trans('public.compare.free') }}</td>
              <td>{{ trans('public.compare.unlimited') }}</td>
              <td>{{ trans('public.compare.unlimited') }}</td>
              <td>1</td>
              <td><span class="glyphicon glyphicon-ok"/></td>
              <td><span class="glyphicon glyphicon-remove"/></td>
              <td><span class="glyphicon glyphicon-ok"/></td>
              <td><span class="glyphicon glyphicon-remove"/></td>              
            </tr>            
            <tr>
              <td><a href="http://www.getharvest.com/" target="_blank" rel="nofollow">Harvest</a></td>
              <td>{{ trans('public.compare.free') }}</td>
              <td>4</td>
              <td>{{ trans('public.compare.unlimited') }}</td>
              <td>4</td>
              <td><span class="glyphicon glyphicon-remove"/></td>
              <td><span class="glyphicon glyphicon-remove"/></td>
              <td><span class="glyphicon glyphicon-ok"/></td>
              <td><span class="glyphicon glyphicon-remove"/></td>              
            </tr>            
            <tr>
              <td><a href="http://invoiceocean.com/" target="_blank" rel="nofollow">InvoiceOcean</a></td>
              <td>{{ trans('public.compare.free') }}</td>
              <td>{{ trans('public.compare.unlimited') }}</td>
              <td>3</td>
              <td>1</td>
              <td><span class="glyphicon glyphicon-ok"/></td>
              <td><span class="glyphicon glyphicon-remove"/></td>
              <td><span class="glyphicon glyphicon-remove"/></td>
              <td><span class="glyphicon glyphicon-remove"/></td>              
            </tr>            
          </tbody>
        </table>

        <p>&nbsp;</p>
        <p>&nbsp;</p>

        <h2>{{ trans('public.compare.paid_plan_comparison') }}</h2>
        <table class="table compare-table compare-table-paid">
          <thead>
            <tr>
              <th>{{ trans('public.compare.app') }}</th>
              <th>{{ trans('public.compare.cost') }}</th>
              <th>{{ trans('public.compare.clients') }}</th>
              <th>{{ trans('public.compare.invoices') }}</th>
              <th>{{ trans('public.compare.payment_gateways') }}</th>
              <th>{{ trans('public.compare.custom_logo') }}</th>
              <th>{{ trans('public.compare.multiple_templates') }}</th>
              <th>{{ trans('public.compare.recurring_payments') }}</th>
              <th>{{ trans('public.compare.open_source') }}</th>
            </tr>            
          </thead>
          <tbody>
            <tr class="active">
              <td><b><a href="https://www.invoiceninja.com" target="_blank">Invoice Ninja</a></b></td>
              <td>$50 {{ trans('public.compare.per_year') }}</td>
              <td>{{ trans('public.compare.unlimited') }}</td>
              <td>{{ trans('public.compare.unlimited') }}</td>
              <td>23</td>
              <td><span class="glyphicon glyphicon-ok"/></td>
              <td><span class="glyphicon glyphicon-ok"/></td>
              <td><span class="glyphicon glyphicon-ok"/></td>
              <td><span class="glyphicon glyphicon-ok"/></td>
            </tr>            
            <tr>
              <td><a href="http://www.freeagent.com" target="_blank" rel="nofollow">FreeAgent</a></td>
              <td>$20 {{ trans('public.compare.per_month') }}</td>
              <td>{{ trans('public.compare.unlimited') }}</td>
              <td>{{ trans('public.compare.unlimited') }}</td>
              <td>3</td>
              <td><span class="glyphicon glyphicon-ok"/></td>
              <td><span class="glyphicon glyphicon-ok"/></td>
              <td><span class="glyphicon glyphicon-ok"/></td>
              <td><span class="glyphicon glyphicon-remove"/></td>              
            </tr>            
            <tr>
              <td><a href="https://www.xero.com/" target="_blank" rel="nofollow">Xero</a></td>
              <td>$20 {{ trans('public.compare.per_month') }}</td>
              <td>{{ trans('public.compare.unlimited') }}</td>
              <td>5</td>
              <td>8</td>
              <td><span class="glyphicon glyphicon-ok"/></td>
              <td><span class="glyphicon glyphicon-ok"/></td>
              <td><span class="glyphicon glyphicon-ok"/></td>
              <td><span class="glyphicon glyphicon-remove"/></td>              
            </tr>            
            <tr>
              <td><a href="http://www.invoice2go.com" target="_blank" rel="nofollow">Invoice2go</a></td>
              <td>$49 {{ trans('public.compare.per_year') }}</td>
              <td>50</td>
              <td>100</td>
              <td>1</td>
              <td><span class="glyphicon glyphicon-ok"/></td>
              <td><span class="glyphicon glyphicon-ok"/></td>
              <td><span class="glyphicon glyphicon-ok"/></td>
              <td><span class="glyphicon glyphicon-remove"/></td>              
            </tr>            
            <tr>
              <td><a href="http://invoicemachine.com/" target="_blank" rel="nofollow">Invoice Machine</a></td>
              <td>$12 {{ trans('public.compare.per_month') }}</td>
              <td>{{ trans('public.compare.unlimited') }}</td>
              <td>30</td>
              <td>2</td>
              <td><span class="glyphicon glyphicon-ok"/></td>
              <td><span class="glyphicon glyphicon-remove"/></td>
              <td><span class="glyphicon glyphicon-ok"/></td>
              <td><span class="glyphicon glyphicon-remove"/></td>              
            </tr>            
            <tr>
              <td><a href="http://www.freshbooks.com" target="_blank" rel="nofollow">FreshBooks</a></td>
              <td>$20 {{ trans('public.compare.per_month') }}</td>
              <td>25</td>
              <td>{{ trans('public.compare.unlimited') }}</td>
              <td>13</td>
              <td><span class="glyphicon glyphicon-ok"/></td>
              <td><span class="glyphicon glyphicon-ok"/></td>
              <td><span class="glyphicon glyphicon-ok"/></td>
              <td><span class="glyphicon glyphicon-remove"/></td>              
            </tr>            
            <tr>
              <td><a href="http://curdbee.com" target="_blank" rel="nofollow">CurdBee</a></td>
              <td>$50 {{ trans('public.compare.per_year') }}</td>
              <td>{{ trans('public.compare.unlimited') }}</td>
              <td>{{ trans('public.compare.unlimited') }}</td>
              <td>3</td>
              <td><span class="glyphicon glyphicon-ok"/></td>
              <td><span class="glyphicon glyphicon-remove"/></td>
              <td><span class="glyphicon glyphicon-remove"/></td>
              <td><span class="glyphicon glyphicon-remove"/></td>              
            </tr>            
            <tr>
              <td><a href="https://www.zoho.com/invoice/" target="_blank" rel="nofollow">Zoho Invoice</a></td>
              <td>$15 {{ trans('public.compare.per_month') }}</td>
              <td>500</td>
              <td>{{ trans('public.compare.unlimited') }}</td>
              <td>6</td>
              <td><span class="glyphicon glyphicon-ok"/></td>
              <td><span class="glyphicon glyphicon-ok"/></td>
              <td><span class="glyphicon glyphicon-ok"/></td>
              <td><span class="glyphicon glyphicon-remove"/></td>              
            </tr>            
            <tr>
              <td><a href="http://www.roninapp.com/" target="_blank" rel="nofollow">Ronin</a></td>
              <td>$29 {{ trans('public.compare.per_month') }}</td>
              <td>{{ trans('public.compare.unlimited') }}</td>
              <td>{{ trans('public.compare.unlimited') }}</td>
              <td>2</td>
              <td><span class="glyphicon glyphicon-ok"/></td>
              <td><span class="glyphicon glyphicon-remove"/></td>
              <td><span class="glyphicon glyphicon-ok"/></td>
              <td><span class="glyphicon glyphicon-remove"/></td>              
            </tr>            
            <tr>
              <td><a href="http://www.getharvest.com/" target="_blank" rel="nofollow">Harvest</a></td>
              <td>$12 {{ trans('public.compare.per_month') }}</td>
              <td>{{ trans('public.compare.unlimited') }}</td>
              <td>{{ trans('public.compare.unlimited') }}</td>
              <td>4</td>
              <td><span class="glyphicon glyphicon-ok"/></td>
              <td><span class="glyphicon glyphicon-remove"/></td>
              <td><span class="glyphicon glyphicon-ok"/></td>
              <td><span class="glyphicon glyphicon-remove"/></td>              
            </tr>            
            <tr>
              <td><a href="http://invoiceocean.com/" target="_blank" rel="nofollow">Invoice Ocean</a></td>
              <td>$9 {{ trans('public.compare.per_month') }}</td>
              <td>{{ trans('public.compare.unlimited') }}</td>
              <td>{{ trans('public.compare.unlimited') }}</td>
              <td>1</td>
              <td><span class="glyphicon glyphicon-ok"/></td>
              <td><span class="glyphicon glyphicon-remove"/></td>
              <td><span class="glyphicon glyphicon-remove"/></td>
              <td><span class="glyphicon glyphicon-remove"/></td>              
            </tr>            
            <tr>
              <td><a href="http://www.apptivo.com/" target="_blank" rel="nofollow">Apptivo</a></td>
              <td>$10 {{ trans('public.compare.per_month') }}</td>
              <td>{{ trans('public.compare.unlimited') }}</td>
              <td>{{ trans('public.compare.unlimited') }}</td>
              <td>3</td>
              <td><span class="glyphicon glyphicon-remove"/></td>
              <td><span class="glyphicon glyphicon-remove"/></td>
              <td><span class="glyphicon glyphicon-ok"/></td>
              <td><span class="glyphicon glyphicon-remove"/></td>              
            </tr>            
          </tbody>
        </table>        
      </div>
    </div>
  </div>
</section>

<section class="upper-footer white-bg">
  <div class="container">
    <div class="row">
      <div class="col-md-3 center-block">
        <a href="#">
          <div class="cta">
            <h2 onclick="return getStarted()">{{ trans('public.invoice_now') }} <span>+</span></h2>
          </div>
        </a>
      </div>
    </div>
  </div>
</section>



@stop