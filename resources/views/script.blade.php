<script type="text/javascript">

  var currencies = {!! \Cache::get('currencies') !!};
  var currencyMap = {};
  for (var i=0; i<currencies.length; i++) {
    var currency = currencies[i];
    currencyMap[currency.id] = currency;
  }

  var NINJA = NINJA || {};
  @if (Auth::check())
    NINJA.primaryColor = "{{ Auth::user()->account->primary_color }}";
    NINJA.secondaryColor = "{{ Auth::user()->account->secondary_color }}";
    NINJA.fontSize = {{ Auth::user()->account->font_size ?: DEFAULT_FONT_SIZE }};
  @endif

  NINJA.parseFloat = function(str) {
    if (!str) return '';
    str = (str+'').replace(/[^0-9\.\-]/g, '');
    return window.parseFloat(str);
  }

  function formatMoney(value, currency_id, hide_symbol) {
    value = NINJA.parseFloat(value);
    if (!currency_id) currency_id = {{ Session::get(SESSION_CURRENCY, DEFAULT_CURRENCY) }};
    var currency = currencyMap[currency_id];
    return accounting.formatMoney(value, hide_symbol ? '' : currency.symbol, currency.precision, currency.thousand_separator, currency.decimal_separator);
  }

</script>