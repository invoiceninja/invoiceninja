<ul class="nav nav-tabs nav nav-justified">
  {{ HTML::nav_link('company/advanced_settings/custom_fields', 'custom_fields') }}
  {{ HTML::nav_link('company/advanced_settings/invoice_design', 'invoice_design') }}
  {{ HTML::nav_link('company/advanced_settings/data_visualizations', 'data_visualizations') }}
  {{ HTML::nav_link('company/advanced_settings/chart_builder', 'chart_builder') }}
  {{ HTML::nav_link('company/advanced_settings/user_management', 'user_management') }}
</ul>
<p>&nbsp;</p>

@if (!Auth::user()->account->isPro())
<div class="container">
  <div class="row">
    <div style="font-size:larger;" class="col-md-8 col-md-offset-2">{{ trans('texts.pro_plan_advanced_settings', ['link'=>'<a href="#" onclick="showProPlan(\''.$feature.'\')">'.trans('texts.pro_plan.remove_logo_link').'</a>']) }}</div>
    &nbsp;<p/>&nbsp;
  </div>    
</div>
@endif

<br/>