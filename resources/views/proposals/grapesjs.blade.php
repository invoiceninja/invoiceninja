<script type="text/javascript">

$(function() {

    window.grapesjsEditor = grapesjs.init({
        container : '#gjs',
        components: '{!! $entity ? $entity->html : '' !!}',
        style: '{!! $entity ? $entity->css : '' !!}',
        showDevices: false,
        categoryLabel: 'tes',
        plugins: ['gjs-preset-newsletter'],
        pluginsOpts: {
            'gjs-preset-newsletter': {
                'categoryLabel': "{{ trans('texts.standard') }}"
            }
        },
        storageManager: {type: 'none'},
    });

    var blockManager = grapesjsEditor.BlockManager;

    @foreach ($snippets as $snippet)
        blockManager.add("h{{ ($loop->index + 1) }}-block", {
            label: '{{ $snippet->name }}',
            category: '{{ $snippet->proposal_category ? $snippet->proposal_category->name : trans('texts.custom') }}',
            content: '{!! $snippet->html !!}',
            style: '{!! $snippet->css !!}',
            attributes: {
                title: '{!! $snippet->private_notes !!}',
                class:'fa fa-{!! $snippet->icon ?: 'book' !!}'
            }
        });
    @endforeach

    @if (count($snippets))
        var blockCategories = blockManager.getCategories();
        for (var i=0; i<blockCategories.models.length; i++) {
            var blockCategory = blockCategories.models[i];
            blockCategory.set('open', false);
        }
    @endif

});

</script>
