<ul class="nav nav-tabs nav nav-justified">
  {!! HTML::nav_link('company/advanced_settings/invoice_design', 'invoice_design') !!}
  {!! HTML::nav_link('company/advanced_settings/invoice_settings', 'invoice_settings') !!}
  {!! HTML::nav_link('company/advanced_settings/templates_and_reminders', 'templates_and_reminders') !!}
  {!! HTML::nav_link('company/advanced_settings/charts_and_reports', 'charts_and_reports') !!}
  {!! HTML::nav_link('company/advanced_settings/user_management', 'users_and_tokens') !!}
</ul>
<p>&nbsp;</p>

@if (!Auth::user()->account->isPro())
<center>
    <div style="font-size:larger;" class="col-md-8 col-md-offset-2">{!! trans('texts.pro_plan_advanced_settings', ['link'=>'<a href="#" onclick="showProPlan(\''.$feature.'\')">'.trans('texts.pro_plan.remove_logo_link').'</a>']) !!}</div>
    &nbsp;<p/>&nbsp;
</center>    
@endif

<br/>