(function($){

 "use strict"; // Start of use strict

$(function() {
    "use strict";
    // Just the defaults.
    $("span.pie").peity("pie",{
        width: '50',
        height: '50'
    })

    $('span.donut').peity('donut',{
        width: '50',
        height: '50'
    })


    $(".peity-line").peity("line",{
        width: '300',
        height: '100'
    })

    $(".bar").peity("bar",{
        width: '300',
        height: '100'
    })

    $(".bar-colours-1").peity("bar", {
        fill: ["red", "green", "blue"],
        width: '100',
        height: '100'
    })

    $(".bar-colours-2").peity("bar", {
        fill: function(value) {
            return value > 0 ? "green" : "red"
        },
        width: '100',
        height: '100'
    })

    $(".bar-colours-3").peity("bar", {
        fill: function(_, i, all) {
            var g = parseInt((i / all.length) * 255)
            return "rgb(255, " + g + ", 0)"
        },
        width: '100',
        height: '100'
    })

    $(".pie-colours-1").peity("pie", {
        fill: ["cyan", "magenta", "yellow", "black"],
        width: '100',
        height: '100'
    })

    $(".pie-colours-2").peity("pie", {
        fill: function(_, i, all) {
            var g = parseInt((i / all.length) * 255)
            return "rgb(255, " + g + ", 0)"
        },
        width: '100',
        height: '100'
    })

    // Using data attributes
    $(".data-attributes span").peity("donut")

    // Evented example.
    $("select").change(function() {
        var text = $(this).val() + "/" + 5

        $(this)
            .siblings("span.graph")
            .text(text)
            .change()

        $("#notice").text("Chart updated: " + text)
    }).change()

    $("span.graph").peity("pie")

    // Updating charts.
    var updatingChart = $(".updating-chart").peity("line", { width: "100%",height:100 })

    setInterval(function() {
        var random = Math.round(Math.random() * 10)
        var values = updatingChart.text().split(",")
        values.shift()
        values.push(random)

        updatingChart
            .text(values.join(","))
            .change()
    }, 1000)
})


})(jQuery);
