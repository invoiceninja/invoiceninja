@extends('public.header')

@section('content')

<section class="hero background hero-plans" data-speed="2" data-type="background">
 <div class="container">
  <div class="row">
    <h1><img src="{{ asset('images/icon-plans.png') }}"><span class="thin">How We</span> Compare</h1>
  </div>
</div>
</section>

<section class="plans center">
  <div class="container">
    <div class="row">
      <div class="col-md-12">
        <h2>Free Plan Comparison</h2>
        <table class="table compare-table compare-table-free">
          <thead>
            <tr>
              <th>App</th>
              <th>Cost</th>
              <th>Clients</th>
              <th>Invoices</th>
              <th>Payment Gateways</th>
              <th>Custom Logo</th>
              <th>Multiple Templates</th>
              <th>Recurring Payments</th>
              <th>Open Source</th>
            </tr>            
          </thead>
          <tbody>
            <tr class="active">
              <td><b><a href="https://www.invoiceninja.com" target="_blank">Invoice Ninja</a></b></td>
              <td>Free</td>
              <td>500</td>
              <td>Unlimited</td>
              <td>23</td>
              <td><span class="glyphicon glyphicon-ok"/></td>
              <td><span class="glyphicon glyphicon-ok"/></td>
              <td><span class="glyphicon glyphicon-ok"/></td>
              <td><span class="glyphicon glyphicon-ok"/></td>
            </tr>            
            <tr>
              <td><a href="http://www.freshbooks.com" target="_blank">FreshBooks</a></td>
              <td>Free</td>
              <td>3</td>
              <td>Unlimited</td>
              <td>13</td>
              <td><span class="glyphicon glyphicon-ok"/></td>
              <td><span class="glyphicon glyphicon-ok"/></td>
              <td><span class="glyphicon glyphicon-ok"/></td>
              <td><span class="glyphicon glyphicon-remove"/></td>              
            </tr>            
            <tr>
              <td><a href="http://www.waveapps.com" target="_blank">Wave</a></td>
              <td>Free</td>
              <td>Unlimited</td>
              <td>Unlimited</td>
              <td>1</td>
              <td><span class="glyphicon glyphicon-ok"/></td>
              <td><span class="glyphicon glyphicon-ok"/></td>
              <td><span class="glyphicon glyphicon-ok"/></td>
              <td><span class="glyphicon glyphicon-remove"/></td>              
            </tr>            
            <tr>
              <td><a href="http://www.nutcache.com" target="_blank">NutCache</a></td>
              <td>Free</td>
              <td>Unlimited</td>
              <td>Unlimited</td>
              <td>3</td>
              <td><span class="glyphicon glyphicon-ok"/></td>
              <td><span class="glyphicon glyphicon-remove"/></td>
              <td><span class="glyphicon glyphicon-remove"/></td>
              <td><span class="glyphicon glyphicon-remove"/></td>              
            </tr>            
            <tr>
              <td><a href="http://curdbee.com/" target="_blank">CurdBee</a></td>
              <td>Free</td>
              <td>Unlimited</td>
              <td>Unlimited</td>
              <td>3</td>
              <td><span class="glyphicon glyphicon-ok"/></td>
              <td><span class="glyphicon glyphicon-remove"/></td>
              <td><span class="glyphicon glyphicon-remove"/></td>
              <td><span class="glyphicon glyphicon-remove"/></td>              
            </tr>            
            <tr>
              <td><a href="https://www.zoho.com/invoice/" target="_blank">Zoho Invoice</a></td>
              <td>Free</td>
              <td>5</td>
              <td>Unlimited</td>
              <td>6</td>
              <td><span class="glyphicon glyphicon-ok"/></td>
              <td><span class="glyphicon glyphicon-ok"/></td>
              <td><span class="glyphicon glyphicon-ok"/></td>
              <td><span class="glyphicon glyphicon-remove"/></td>              
            </tr>            
            <tr>
              <td><a href="http://www.roninapp.com/" target="_blank">Ronin</a></td>
              <td>Free</td>
              <td>2</td>
              <td>Unlimited</td>
              <td>1</td>
              <td><span class="glyphicon glyphicon-ok"/></td>
              <td><span class="glyphicon glyphicon-remove"/></td>
              <td><span class="glyphicon glyphicon-ok"/></td>
              <td><span class="glyphicon glyphicon-remove"/></td>              
            </tr>            
            <tr>
              <td><a href="http://invoiceable.co/" target="_blank">Invoiceable</a></td>
              <td>Free</td>
              <td>Unlimited</td>
              <td>Unlimited</td>
              <td>1</td>
              <td><span class="glyphicon glyphicon-ok"/></td>
              <td><span class="glyphicon glyphicon-remove"/></td>
              <td><span class="glyphicon glyphicon-ok"/></td>
              <td><span class="glyphicon glyphicon-remove"/></td>              
            </tr>            
            <tr>
              <td><a href="http://www.getharvest.com/" target="_blank">Harvest</a></td>
              <td>Free</td>
              <td>4</td>
              <td>Unlimited</td>
              <td>4</td>
              <td><span class="glyphicon glyphicon-remove"/></td>
              <td><span class="glyphicon glyphicon-remove"/></td>
              <td><span class="glyphicon glyphicon-ok"/></td>
              <td><span class="glyphicon glyphicon-remove"/></td>              
            </tr>            
            <tr>
              <td><a href="http://invoiceocean.com/" target="_blank">InvoiceOcean</a></td>
              <td>Free</td>
              <td>Unlimited</td>
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

        <h2>Paid Plan Comparison</h2>
        <table class="table compare-table compare-table-paid">
          <thead>
            <tr>
              <th>App</th>
              <th>Cost</th>
              <th>Clients</th>
              <th>Invoices</th>
              <th>Payment Gateways</th>
              <th>Custom Logo</th>
              <th>Multiple Templates</th>
              <th>Recurring Payments</th>
              <th>Open Source</th>
            </tr>            
          </thead>
          <tbody>
            <tr class="active">
              <td><b><a href="https://www.invoiceninja.com" target="_blank">Invoice Ninja</a></b></td>
              <td>$50 per year</td>
              <td>Unlimited</td>
              <td>Unlimited</td>
              <td>23</td>
              <td><span class="glyphicon glyphicon-ok"/></td>
              <td><span class="glyphicon glyphicon-ok"/></td>
              <td><span class="glyphicon glyphicon-ok"/></td>
              <td><span class="glyphicon glyphicon-ok"/></td>
            </tr>            
            <tr>
              <td><a href="http://www.freeagent.com" target="_blank">FreeAgent</a></td>
              <td>$20 per month</td>
              <td>Unlimited</td>
              <td>Unlimited</td>
              <td>3</td>
              <td><span class="glyphicon glyphicon-ok"/></td>
              <td><span class="glyphicon glyphicon-ok"/></td>
              <td><span class="glyphicon glyphicon-ok"/></td>
              <td><span class="glyphicon glyphicon-remove"/></td>              
            </tr>            
            <tr>
              <td><a href="https://www.xero.com/" target="_blank">Xero</a></td>
              <td>$20 per month</td>
              <td>Unlimited</td>
              <td>5</td>
              <td>8</td>
              <td><span class="glyphicon glyphicon-ok"/></td>
              <td><span class="glyphicon glyphicon-ok"/></td>
              <td><span class="glyphicon glyphicon-ok"/></td>
              <td><span class="glyphicon glyphicon-remove"/></td>              
            </tr>            
            <tr>
              <td><a href="http://www.invoice2go.com" target="_blank">Invoice2go</a></td>
              <td>$49 per year</td>
              <td>50</td>
              <td>100</td>
              <td>1</td>
              <td><span class="glyphicon glyphicon-ok"/></td>
              <td><span class="glyphicon glyphicon-ok"/></td>
              <td><span class="glyphicon glyphicon-ok"/></td>
              <td><span class="glyphicon glyphicon-remove"/></td>              
            </tr>            
            <tr>
              <td><a href="http://invoicemachine.com/" target="_blank">Invoice Machine</a></td>
              <td>$12 per month</td>
              <td>Unlimited</td>
              <td>30</td>
              <td>2</td>
              <td><span class="glyphicon glyphicon-ok"/></td>
              <td><span class="glyphicon glyphicon-remove"/></td>
              <td><span class="glyphicon glyphicon-ok"/></td>
              <td><span class="glyphicon glyphicon-remove"/></td>              
            </tr>            
            <tr>
              <td><a href="http://www.freshbooks.com" target="_blank">FreshBooks</a></td>
              <td>$20 per month</td>
              <td>25</td>
              <td>Unlimited</td>
              <td>13</td>
              <td><span class="glyphicon glyphicon-ok"/></td>
              <td><span class="glyphicon glyphicon-ok"/></td>
              <td><span class="glyphicon glyphicon-ok"/></td>
              <td><span class="glyphicon glyphicon-remove"/></td>              
            </tr>            
            <tr>
              <td><a href="http://curdbee.com" target="_blank">CurdBee</a></td>
              <td>$50 per year</td>
              <td>Unlimited</td>
              <td>Unlimited</td>
              <td>3</td>
              <td><span class="glyphicon glyphicon-ok"/></td>
              <td><span class="glyphicon glyphicon-remove"/></td>
              <td><span class="glyphicon glyphicon-remove"/></td>
              <td><span class="glyphicon glyphicon-remove"/></td>              
            </tr>            
            <tr>
              <td><a href="https://www.zoho.com/invoice/" target="_blank">Zoho Invoice</a></td>
              <td>$15 per month</td>
              <td>500</td>
              <td>Unlimited</td>
              <td>6</td>
              <td><span class="glyphicon glyphicon-ok"/></td>
              <td><span class="glyphicon glyphicon-ok"/></td>
              <td><span class="glyphicon glyphicon-ok"/></td>
              <td><span class="glyphicon glyphicon-remove"/></td>              
            </tr>            
            <tr>
              <td><a href="http://www.roninapp.com/" target="_blank">Ronin</a></td>
              <td>$29 per month</td>
              <td>Unlimited</td>
              <td>Unlimited</td>
              <td>2</td>
              <td><span class="glyphicon glyphicon-ok"/></td>
              <td><span class="glyphicon glyphicon-remove"/></td>
              <td><span class="glyphicon glyphicon-ok"/></td>
              <td><span class="glyphicon glyphicon-remove"/></td>              
            </tr>            
            <tr>
              <td><a href="http://www.getharvest.com/" target="_blank">Harvest</a></td>
              <td>$12 per month</td>
              <td>Unlimited</td>
              <td>Unlimited</td>
              <td>4</td>
              <td><span class="glyphicon glyphicon-ok"/></td>
              <td><span class="glyphicon glyphicon-remove"/></td>
              <td><span class="glyphicon glyphicon-ok"/></td>
              <td><span class="glyphicon glyphicon-remove"/></td>              
            </tr>            
            <tr>
              <td><a href="http://invoiceocean.com/" target="_blank">Invoice Ocean</a></td>
              <td>$9 per month</td>
              <td>Unlimited</td>
              <td>Unlimited</td>
              <td>1</td>
              <td><span class="glyphicon glyphicon-ok"/></td>
              <td><span class="glyphicon glyphicon-remove"/></td>
              <td><span class="glyphicon glyphicon-remove"/></td>
              <td><span class="glyphicon glyphicon-remove"/></td>              
            </tr>            
            <tr>
              <td><a href="http://www.apptivo.com/" target="_blank">Apptivo</a></td>
              <td>$10 per month</td>
              <td>Unlimited</td>
              <td>Unlimited</td>
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
            <h2 onclick="return getStarted()">Invoice Now <span>+</span></h2>
          </div>
        </a>
      </div>
    </div>
  </div>
</section>



@stop