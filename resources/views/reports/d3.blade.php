@extends('header')

@section('head')
  @parent

  @include('money_script')
  <script src="{!! asset('js/d3.min.js') !!}" type="text/javascript"></script>

  <style type="text/css">

  #tooltip {
    position: absolute;
    width: 200px;
    height: auto;
    padding: 10px 10px 2px 10px;
    background-color: #F6F6F6;
    -webkit-border-radius: 10px;
    -moz-border-radius: 10px;
    border-radius: 10px;
    -webkit-box-shadow: 4px 4px 10px rgba(0, 0, 0, 0.4);
    -moz-box-shadow: 4px 4px 10px rgba(0, 0, 0, 0.4);
    box-shadow: 4px 4px 10px rgba(0, 0, 0, 0.4);
  }

  .no-pointer-events {
    pointer-events: none;
  }

  </style>
@stop

@section('content')
  @parent
  @include('accounts.nav', ['selected' => ACCOUNT_DATA_VISUALIZATIONS, 'advanced' => true])

  <div id="tooltip" class="hidden">
    <p>
      <strong><span id="tooltipTitle"></span></strong>
      <a class="pull-right" href="#" target="_blank">View</a>
    </p>
    <p>Total <span id="tooltipTotal" class="pull-right"></span></p>
    <p>Balance <span id="tooltipBalance" class="pull-right"></span></p>
    <p>Age <span id="tooltipAge" class="pull-right"></span></p>
  </div>

  <form class="form-inline" role="form">
    Group By &nbsp;&nbsp;
    <select id="groupBySelect" class="form-control" onchange="update()" style="background-color:white !important">
      <option>Clients</option>
      <option>Invoices</option>
      <option>Products</option>
    </select>
    &nbsp;&nbsp; <b>{!! $message !!}</b>
  </form>

  <p>&nbsp;</p>

  <div class="svg-div"/>

  <script type="text/javascript">

    // store data as JSON
    var data = {!! $clients !!};

    _.each(data, function(client) {
      _.each(client.invoices, function(invoice) {
        _.each(invoice.invoice_items, function(invoice_item) {
          invoice_item.invoice = invoice;
        });
      });
    });

    // pre-process the possible groupings (clients, invoices and products)
    var clients = data.concat();
    var invoices = _.flatten(_.pluck(clients, 'invoices'));

    // remove quotes and recurring invoices
    invoices = _.filter(invoices, function(invoice) {
      return parseInt(invoice.invoice_type_id) == {{ INVOICE_TYPE_STANDARD }} && !invoice.is_recurring;
    });

    var products = _.flatten(_.pluck(invoices, 'invoice_items'));
    products = d3.nest()
      .key(function(d) { return d.product_key; })
      .sortKeys(d3.ascending)
      .rollup(function(d) { return {
        amount: d3.sum(d, function(g) {
          return g.qty * g.cost;
        }),
        paid: d3.sum(d, function(g) {
          return g.invoice && g.invoice.invoice_status_id == {{ INVOICE_STATUS_PAID }} ? (g.qty * g.cost) : 0;
        }),
        age: d3.mean(d, function(g) {
          return calculateInvoiceAge(g.invoice) || null;
        }),
      }})
      .entries(products);

    // create standardized display properties
    _.each(clients, function(client) {
      client.displayName = getClientDisplayName(client);
      client.displayTotal = +client.paid_to_date + +client.balance;
      client.displayBalance = +client.balance;
      client.displayPercent = (+client.paid_to_date / (+client.paid_to_date + +client.balance)).toFixed(2);
      var oldestInvoice = _.max(client.invoices, function(invoice) { return calculateInvoiceAge(invoice) });
      client.displayAge = oldestInvoice ? calculateInvoiceAge(oldestInvoice) : -1;
    });

    _.each(invoices, function(invoice) {
      invoice.displayName = invoice.invoice_number;
      invoice.displayTotal = +invoice.amount;
      invoice.displayBalance = +invoice.balance;
      invoice.displayPercent = (+invoice.amount - +invoice.balance) / +invoice.amount;
      invoice.displayAge = calculateInvoiceAge(invoice);
    });

    _.each(products, function(product) {
      product.displayName = product.key;
      product.displayTotal = product.values.amount;
      product.displayBalance = product.values.amount - product.values.paid;
      product.displayPercent = (product.values.paid / product.values.amount).toFixed(2);
      product.displayAge = product.values.age;
    });

    //console.log(JSON.stringify(clients));
    //console.log(JSON.stringify(invoices));
    //console.log(JSON.stringify(products));

    var arc = d3.svg.arc()
      .innerRadius(function(d) { return d.r })
      .outerRadius(function(d) { return d.r - 8 })
      .startAngle(0);

    var fullArc = d3.svg.arc()
      .innerRadius(function(d) { return d.r - 1 })
      .outerRadius(function(d) { return d.r - 7 })
      .startAngle(0)
      .endAngle(2 * Math.PI);

    var diameter = 800,
    format = d3.format(",d");
    //color = d3.scale.category10();

    var color = d3.scale.linear()
      .domain([0, 100])
      .range(["yellow", "red"]);

    var bubble = d3.layout.pack()
      .sort(null)
      .size([diameter, diameter])
      .value(function(d) { return Math.max(30, d.displayTotal) })
      .padding(12);

    var svg = d3.select(".svg-div").append("svg")
      .attr("width", "100%")
      .attr("height", "1142px")
      .attr("class", "bubble");

    svg.append("rect")
      .attr("stroke-width", "1")
      .attr("stroke", "rgb(150,150,150)")
      .attr("width", "99%")
      .attr("height", "100%")
      .attr("fill", "white");

    function update() {

      var data = {};
      var groupBy = $('#groupBySelect').val().toLowerCase();
      data.children = window[groupBy];

      data = bubble.nodes(data).filter(function(d) {
        return !d.children && d.displayTotal && d.displayName;
      });

      var selection = svg.selectAll(".node")
        .data(data, function(d) { return d.displayName; });

      var node = selection.enter().append("g")
        .attr("class", "node")
        .attr("transform", function(d) { return "translate(" + (d.x+20) + "," + (d.y+20) + ")"; });

      var visibleTooltip = false;
      node.on("mousemove", function(d) {
        if (!visibleTooltip || visibleTooltip != d.displayName) {
          d3.select("#tooltip")
            .classed("hidden", false)
            .style("left", (d3.event.offsetX + 40) + "px")
            .style("top", (d3.event.offsetY + 40) + "px");
          visibleTooltip = d.displayName;
          //console.log(d3.event);
        }

        d3.select("#tooltipTitle").text(truncate(d.displayName, 18));
        d3.select("#tooltipTotal").text(formatMoney(d.displayTotal));
        d3.select("#tooltipBalance").text(formatMoney(d.displayBalance));
        d3.select("#tooltipAge").text(pluralize('? day', parseInt(Math.max(0, d.displayAge))));

        if (groupBy == "products" || !d.public_id) {
          d3.select("#tooltip a").classed("hidden", true);
        } else {
          d3.select("#tooltip a").classed("hidden", false);
          d3.select("#tooltip a").attr("href", "/" + groupBy + "/" + d.public_id);
        }
      });

      svg.on("click", function() {
        visibleTooltip = false;
        d3.select("#tooltip")
          .classed("hidden", true);
      });

      node.append("circle")
        .attr("fill", "#ffffff")
        .attr("r", function(d) { return d.r });

      node.append("path")
        .each(function(d) { d.endAngle = 0; })
        .attr("class", "no-pointer-events")
        .attr("class", "animate-fade")
        .attr("d", fullArc)
        .style("fill", function(d, i) { return 'white'; });

      node.append("text")
        .attr("dy", ".3em")
        .attr("class", "no-pointer-events")
        .style("text-anchor", "middle")
        .text(function(d) { return d.displayName; });

      node.append("path")
        .each(function(d) { d.endAngle = 0; })
        .attr("class", "no-pointer-events")
        .attr("class", "animate-grow")
        .attr("d", arc)
        .style("fill", function(d, i) { return '#2e9e49'; });

      d3.selectAll("path.animate-grow")
        .transition()
        .delay(function(d, i) { return (Math.random() * 500) })
        .duration(1000)
        .call(arcTween, 5);

      d3.selectAll("path.animate-fade")
        .transition()
        .duration(1000)
        .style("fill", function(d, i) {
          return 'red';
        });

      selection.exit().remove();
    }

    update();

    // http://bl.ocks.org/mbostock/5100636
    function arcTween(transition, newAngle) {
      transition.attrTween("d", function(d) {
        var interpolate = d3.interpolate( 0, 360 * d.displayPercent * Math.PI/180 );
        return function(t) {
          d.endAngle = interpolate(t);
          return arc(d);
        };
      });
    }

    function calculateInvoiceAge(invoice) {
      if (!invoice || invoice.invoice_status_id == 5) {
        return -1;
      }
      var dayInSeconds = 1000*60*60*24;
      @if (Auth::user()->account->hasFeature(FEATURE_REPORTS))
        var date = convertToJsDate(invoice.created_at);
      @else
        var date = new Date().getTime() - (dayInSeconds * Math.random() * 100);
      @endif

      return parseInt((new Date().getTime() - date) / dayInSeconds);
    }

    function convertToJsDate(isoDate) {
      if (!isoDate) {
        return false;
      }
      var t = isoDate.split(/[- :]/);
      return new Date(t[0], t[1]-1, t[2], t[3], t[4], t[5]);
    }


    function pluralize(string, count) {
      string = string.replace('?', count);
      if (count !== 1) {
        string += 's';
      }
      return string;
    };

  </script>

@stop
