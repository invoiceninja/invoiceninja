{!! Former::select('tax_select1')
  ->addOption('','')
  ->label(isset($taxRateLabel) ? $taxRateLabel : trans('texts.tax_rate'))
  ->onchange('taxSelectChange(event)')
  ->fromQuery($taxRates) !!}

<div style="display:none">
  {!! Former::input('tax_rate1') !!}
  {!! Former::input('tax_name1') !!}
</div>

<div style="display:{{ $account->enable_second_tax_rate ? 'block' : 'none' }}">
  {!! Former::select('tax_select2')
      ->addOption('','')
      ->label(isset($taxRateLabel) ? $taxRateLabel : trans('texts.tax_rate'))
      ->onchange('taxSelectChange(event)')
      ->fromQuery($taxRates) !!}

  <div style="display:none">
      {!! Former::input('tax_rate2') !!}
      {!! Former::input('tax_name2') !!}
  </div>
</div>

<script type="text/javascript">

    var taxRates = {!! $taxRates !!};

    function taxSelectChange(event) {
        var $select = $(event.target);
        var tax = $select.find('option:selected').text();

        var index = tax.lastIndexOf(': ');
        var taxName =  tax.substring(0, index);
        var taxRate = tax.substring(index + 2, tax.length - 1);

        var selectName = $select.attr('name');
        var instance = selectName.substring(selectName.length - 1);

        $('#tax_name' + instance).val(taxName);
        $('#tax_rate' + instance).val(taxRate);
    }

    function setTaxSelect(instance) {
        var $select = $('#tax_select' + instance);
        var taxName = $('#tax_name' + instance).val();
        var taxRate = $('#tax_rate' + instance).val();
        if (!taxRate || !taxName) {
            return;
        }
        var tax = _.findWhere(taxRates, {name:taxName, rate:taxRate});
        if (tax) {
            $select.val(tax.public_id);
        } else {
            var option = new Option(taxName + ': ' + taxRate + '%', '');
            option.selected = true;
            $select.append(option);
        }
    }

    $(function() {
        setTaxSelect(1);
        setTaxSelect(2);
    });

</script>
