( function () {
    var registry = window.wc && window.wc.wcBlocksRegistry;
    var getSetting = window.wc && window.wc.wcSettings && window.wc.wcSettings.getSetting;
    if ( ! registry || ! getSetting ) {
        console.error( 'MamMart SePay: thieu wc-blocks-registry hoac wc-settings' );
        return;
    }

    var el = window.wp.element.createElement;
    var settings = getSetting( 'mammart_sepay_data', {} );
    var title = settings.title || 'Chuyển khoản ngân hàng qua SePay (QR Code)';

    var Label = function () { return el( 'span', null, title ); };
    var Content = function () { return el( 'div', null, settings.description || '' ); };

    registry.registerPaymentMethod( {
        name: 'mammart_sepay',
        label: el( Label, null ),
        content: el( Content, null ),
        edit: el( Content, null ),
        canMakePayment: function () { return true; },
        ariaLabel: title,
        supports: { features: [ 'products' ] },
    } );

    console.log( 'MamMart SePay block da duoc dang ky' );
} )();