<li class="nav-{{ $option }} {{ Request::is("{$option}*") ? 'active' : '' }}">

    @if ($option == 'settings')

        <a type="button" class="btn btn-default btn-sm pull-right" title="{{ Utils::getReadableUrl(request()->path()) }}"
            href="{{ Utils::getDocsUrl(request()->path()) }}" target="_blank">
            <i class="fa fa-info-circle" style="width:20px"></i>
        </a>

    @elseif (Auth::user()->can('create', $option) || Auth::user()->can('create', substr($option, 0, -1)))

        <a type="button" class="btn btn-primary btn-sm pull-right"
            href="{{ url("/{$option}/create") }}">
            <i class="fa fa-plus-circle" style="width:20px" title="{{ trans('texts.create_new') }}"></i>
        </a>

    @endif

    <a href="{{ url($option == 'recurring' ? 'recurring_invoice' : $option) }}"
        style="font-size:15px; padding-top:6px; padding-bottom:6px"
        class="nav-link {{ Request::is("{$option}*") ? 'active' : '' }}">
        <i class="fa fa-{{ empty($icon) ? \App\Models\EntityModel::getIcon($option) : $icon }}" style="width:46px; padding-right:10px"></i>
        {{ ($option == 'recurring_invoices') ? trans('texts.recurring') : mtrans($option) }}
        {!! Utils::isTrial() && in_array($option, ['quotes', 'tasks', 'expenses', 'vendors', 'reports']) ? '&nbsp;<sup>' . trans('texts.pro') . '</sup>' : '' !!}
    </a>

</li>
