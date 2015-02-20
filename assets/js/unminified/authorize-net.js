jQuery( document ).ready( function( $ ){
    var payment_form = $( '#authorize_net_payment_form');

    if( payment_form.length != 0 ){
        payment_form.find( 'input[type="submit"]').click();
    }
} );