<div class="card" id="localization">
    <div class="card-header bg-primary2">@lang('texts.localization')</div>
    <div class="card-body">
        <div class="form-group row">
            <label for="name" class="col-sm-3 col-form-label text-right">@lang('texts.client_name')</label>
            <div class="col-sm-9">
                <v-select v-model="selected" :options="options"></v-select>
                <div v-if="form.errors.has('name')" class="text-danger" v-text="form.errors.get('name')"></div>
            </div>
        </div>

    </div>
</div>


<script>
</script>

<script defer src=" {{ mix('/js/localization.min.js') }}"></script>
