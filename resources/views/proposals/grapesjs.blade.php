<script type="text/javascript">

$(function() {

    window.grapesjsEditor = grapesjs.init({
        container : '#gjs',
        components: {!! json_encode($entity ? $entity->html : '') !!},
        style: {!! json_encode($entity ? $entity->css : '') !!},
        showDevices: false,
        noticeOnUnload: false,
        plugins: ['gjs-preset-newsletter'],
        pluginsOpts: {
            'gjs-preset-newsletter': {
                'categoryLabel': "{{ trans('texts.standard') }}"
            }
        },
        storageManager: {
            type: 'none',
            autosave: false,
            autoload: false,
            storeComponents: false,
            storeStyles: false,
            storeHtml: false,
            storeCss: false,
        },
        assetManager: {
            assets: {!! json_encode($documents) !!},
            noAssets: "{{ trans('texts.no_assets') }}",
            addBtnText: "{{ trans('texts.add_image') }}",
            modalTitle: "{{ trans('texts.select_image') }}",
            @if (Utils::isSelfHost() || $account->isEnterprise())
                upload: {!! json_encode(url('/documents')) !!},
                uploadText: "{{ trans('texts.dropzone_default_message') }}",
            @else
                upload: false,
                uploadText: "{{ trans('texts.upgrade_to_upload_images') }}",
            @endif
            uploadName: 'files',
            params: {
                '_token': '{{ Session::token() }}',
                'grapesjs': true,
            }
        }
    });

    var panelManager = grapesjsEditor.Panels;
    panelManager.addButton('options', [{
        id: 'undo',
        className: 'fa fa-undo',
        command: 'undo',
        attributes: { title: 'Undo (CTRL/CMD + Z)'}
    },{
        id: 'redo',
        className: 'fa fa-repeat',
        attributes: {title: 'Redo'},
        command: 'redo',
        attributes: { title: 'Redo (CTRL/CMD + SHIFT + Z)' }
    }]);

    var blockManager = grapesjsEditor.BlockManager;
    
    blockManager.get('text').set('content', {
        type: 'text',
        content: 'Insert your text here',
        activeOnRender: 1
    });

    blockManager.get('grid-items').set('content', '\
    <table>\
        <tr>\
            <td class="card-content">\
                <img src="" alt="Image"/>\
                <h1 class="card-title">Title here</h1>\
                <p class="card-text">Lorem ipsum dolor sit amet, consectetur adipiscing elit, sed do eiusmod tempor incididunt</p>\
            </td>\
            <td class="card-content">\
                <img src="" alt="Image"/>\
                <h1 class="card-title">Title here</h1>\
                <p class="card-text">Lorem ipsum dolor sit amet, consectetur adipiscing elit, sed do eiusmod tempor incididunt</p>\
            </td>\
        </tr>\
    </table>');

    blockManager.get('list-items').set('content', '\
  <table>\
    <tr>\
      <td class="card-content">\
        <img alt="Image"//>\
      </td>\
      <td class="card-content">\
        <h1 class="card-title">Title here</h1>\
        <p class="card-text">Lorem ipsum dolor sit amet, consectetur adipiscing elit, sed do eiusmod tempor incididunt</p>\
      </td>\
    </tr>\
  </table>\
  <table>\
    <tr>\
      <td class="card-content">\
        <img alt="Image"/>\
      </td>\
      <td class="card-content">\
        <h1 class="card-title">Title here</h1>\
        <p class="card-text">Lorem ipsum dolor sit amet, consectetur adipiscing elit, sed do eiusmod tempor incididunt</p>\
      </td>\
    </tr>\
  </table>');


    @foreach ($snippets as $snippet)
        blockManager.add("h{{ ($loop->index + 1) }}-block", {
            label: '{{ $snippet->name }}',
            category: '{{ $snippet->proposal_category ? $snippet->proposal_category->name : trans('texts.custom') }}',
            content: {!! json_encode($snippet->html) !!},
            style: {!! json_encode($snippet->css) !!},
            attributes: {
                title: {!! json_encode($snippet->private_notes) !!},
                class:'fa fa-{{ $snippet->icon ?: 'font' }}'
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

    grapesjsEditor.on('component:update', function(a, b) {
        NINJA.formIsChanged = true;
    });

    grapesjsEditor.on('asset:remove', function(asset) {
        sweetConfirm(function() {
            $.ajax({
                url: "{{ url('/documents') }}/" + asset.attributes.public_id,
                type: 'DELETE',
                success: function(result) {
                    console.log('result: %s', result);
                }
            });
        }, "{{ trans('texts.delete_image_help') }}", "{{ trans('texts.delete_image') }}", function() {
            var assetManager = grapesjsEditor.AssetManager;
            assetManager.add([asset.attributes]);
        });
    });

});

</script>
