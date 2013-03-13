$(document).ready(function () {

    // scroll-dependent animations
    $(window).scroll(function() {    
      	if ( $(this).scrollTop()>=45 ) {
            if (!$("#cart-summary").hasClass('empty')) {
              	$("#cart-summary").addClass( "fixed" );
            }
    	}
    	else if ( $(this).scrollTop()<40 ) {
    		$("#cart-summary").removeClass( "fixed" );
    	}    
    });

    $("form.addtocart").submit(function () {
        var f = $(this);
        $.post(f.attr('action'), f.serialize(), function (response) {
            if (response.status == 'ok') {
                var cart_total = $(".cart-total");
                if (f.closest(".product-list").get(0).tagName.toLowerCase() == 'table') {
                    var origin = f.closest('tr');
                    var block = $('<div></div>').append($('<table></table>').append(origin.clone()));
                } else {
                    var origin = f.closest('li');
                    var block = $('<div></div>').append(origin.html());
                }
                block.css({
                    'z-index': 10,
                    top: origin.offset().top,
                    left: origin.offset().left,
                    width: origin.width()+'px',
                    height: origin.height()+'px',
                    position: 'absolute',
                    overflow: 'hidden'
                }).insertAfter(origin).animate({
                        top: cart_total.offset().top,
                        left: cart_total.offset().left,
                        width: 0,
                        height: 0,
                        opacity: 0.5
                    }, 500, function() {
                        $(this).remove();
                        cart_total.html(response.data.total);
                        cart_total.closest('#cart').removeClass('empty');
                    });
            }
        }, "json");
        return false;
    });
  
});
