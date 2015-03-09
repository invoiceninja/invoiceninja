<ul class="nav nav-tabs nav nav-justified">
  {{ HTML::nav_link('company/advanced_settings/invoice_settings', 'invoice_settings') }}
  {{ HTML::nav_link('company/advanced_settings/invoice_design', 'invoice_design') }}
  {{ HTML::nav_link('company/advanced_settings/email_templates', 'email_templates') }}
  {{ HTML::nav_link('company/advanced_settings/chart_builder', 'chart_builder') }}
  {{ HTML::nav_link('company/advanced_settings/user_management', 'users_and_tokens') }}
</ul>
<p>&nbsp;</p>

@if (!Auth::user()->account->isPro())
<center>
    <div style="font-size:larger;" class="col-md-8 col-md-offset-2">{{ trans('texts.pro_plan_advanced_settings', ['link'=>'<a href="#" onclick="showProPlan(\''.$feature.'\')">'.trans('texts.pro_plan.remove_logo_link').'</a>']) }}</div>
    &nbsp;<p/>&nbsp;
</center>    
@endif

<br/>