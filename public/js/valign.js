(function ($) {
$.fn.vAlign = function() {
	return this.each(function(i){
        $(this).children().wrapAll('<div class="nitinh-vAlign" style="position:relative;"></div>');
        var div = $(this).children('div.nitinh-vAlign');
        var ph = $(this).innerHeight();
        var dh = div.height();
        var mh = (ph - dh) / 2;
        div.css('top', mh);
	});
};
})(jQuery);