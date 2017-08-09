var package = {};

var purchase_token = {}; // token used for buying single item
var subscribe_token = {}; // subscription token
var donate_token = {}; // donate token
var ajax_url = {}; // Ajax URL
var main_url = {}; // Main url
var custom_redirect = ''; // Custom redirect (after donation for instance)

jQuery(function() {
    // Trying to find comments closed text to substitute via js (this string is created by the theme, no control there )
    document.body.innerHTML = document.body.innerHTML.replace(/Comments are closed./g, 'You must purchase this article with Transact or subscribe to read or post comments.');

    main_url = url;
    ajax_url = url.ajaxurl;

    if (url.redirect_after_donation) {
        custom_redirect = url.redirect_after_donation;
    }

    if (url.donation == 1) {
        getDonationTokenAjaxCall(url.price); 
    } else {
        jQuery.getJSON(ajax_url, { 'action' : 'get_token', 'post_id' : url.post_id, 'affiliate_id' : url.affiliate_id })
            .success(function(data) {
                console.log('got token: '+ data.token);
                purchase_token = data.token;
                //transactApi.setToken(data.token);
            })
            .fail(function(data) {
                console.log('Failed to get Transact token');
            });

        // If subscription button is on the site, get subscription token too
        if(document.getElementById('button_purchase subscription')) {
            jQuery.getJSON(ajax_url, { 'action' : 'get_subscription_token', 'post_id' : url.post_id, 'affiliate_id' : url.affiliate_id })
                .success(function(data) {
                    console.log('got subscribe_token: '+ data.token);
                    subscribe_token = data.token;
                    //transactApi.setToken(data.token);
                })
                .fail(function(data) {
                    console.log('Failed to get Transact token');
                });
        }
    }
});

/**
 * Gets a new donate token every time the user changes donation value
 */
function setDonateAmount() {
    var donate = jQuery('#donate_val').val();
    if (!donate) {
        alert('Invalid Donation Amount');
        return;
    }
    console.log('setDonateAmount', donate);
    getDonationTokenAjaxCall(donate);
}

/**
 * AJAX call responsible of donation token
 */
function getDonationTokenAjaxCall(donate) {
    jQuery.getJSON(ajax_url, { 'action' : 'get_donation_token', 'post_id' : url.post_id, 'price' : donate, 'affiliate_id' : main_url.affiliate_id })
        .success(function(data) {
            console.log('got donate_token: '+ data.token);
            donate_token = data.token;
            jQuery('#donation').removeAttr("disabled");
        })
        .fail(function(data) {
            jQuery('#donation').attr("disabled");
            console.log('Failed to get Transact donate token');
        });
}

/**
 * onclick for purchase button
 * Will set up purchase token on transact
 * and get the callback
 */
function doPurchase() {
    console.log('doPurchase');
    console.log('Setting Purchase token');
    transactApi.setToken(purchase_token);
    // Call authorize() which will load the popup,
    // passing in callback function (PurchasePopUpClosed)
    transactApi.authorize(PurchasePopUpClosed);
}

function doDonate() {
    console.log('doDonate');
    transactApi.setToken(donate_token);
    // Call authorize() which will load the popup,
    // passing in callback function (PurchasePopUpClosed)
    transactApi.authorize(PurchasePopUpClosed);
}

/**
 * onclick for purchase button
 * Will set up subscription token on transact
 * and get the callback
 */
function doSubscription() {
    console.log('doSubscription');
    console.log('Setting Subscription token');
    console.log(subscribe_token);
    transactApi.setToken(subscribe_token);
    // Call authorize() which will load the popup,
    // passing in callback function (PurchasePopUpClosed)
    transactApi.authorize(PurchasePopUpClosed);
}

function PurchasePopUpClosed(popup, event) {

    var ajax_url = url.ajaxurl;
    console.log('PurchasePopUpClosed');
    console.log('Event data:', event.data);

    if (event && event.data) {
        console.log(event.data);
        var validation_data = event.data;
        validation_data.action = 'get_purchased_content';
        validation_data.post_id = url.post_id;

        var jqxhr = jQuery.getJSON(ajax_url, validation_data)
            .done(function(resp_data) {
                console.log('Success Response data:', resp_data);
                // Handles cookie
                handleCookies(validation_data, resp_data);
                // if custom redirect, send user to it, otherwise reload
                if (custom_redirect.length > 0) {
                    window.location = custom_redirect;
                } else {
                    location.reload();
                }
            })
            .fail(function(resp_data) {
                console.log('Error Response data:', resp_data);
                jQuery('#button_purchase').html('purchase failed');
            })
            .always(function() {
                console.log( "finished" );
            });
    }
}

function handleCookies(validation_data, resp_data)
{
    console.log(resp_data, resp_data.decoded);
    // Set or Update Cookie
    if (resp_data.subscription == '1') {
        handleSubscriptionCookies(resp_data);
    } else {
        handlePurchaseCookies(validation_data, resp_data);
    }
}

function handleSubscriptionCookies(resp_data)
{
    console.log('setting wp_subscription_transact_ cookie');
    var cookie = getCookie('wp_subscription_transact_');
    if (cookie != '') {
        cookie_array = JSON.parse(cookie);
        var new_cookie = {};
        new_cookie['expiration']  = resp_data.decoded.sub_expires;
        new_cookie['uid'] = resp_data.decoded.uid;
        cookie_array.push(new_cookie);
        setCookie('wp_subscription_transact_', JSON.stringify(cookie_array), 365);
    } else {
        var new_cookie = {};
        new_cookie['expiration']  = resp_data.decoded.sub_expires;
        new_cookie['uid'] = resp_data.decoded.uid;
        var cookies = [];
        cookies.push(new_cookie);
        setCookie('wp_subscription_transact_', JSON.stringify(cookies), 365);
    }
}

function handlePurchaseCookies(validation_data, resp_data)
{
    console.log('setting wp_transact_ cookie');
    var cookie = getCookie('wp_transact_');
    if (cookie != '') {
        cookie_array = JSON.parse(cookie);
        var new_cookie = {};
        new_cookie['id']  = validation_data.post_id;
        new_cookie['uid'] = resp_data.decoded.uid;
        cookie_array.push(new_cookie);
        setCookie('wp_transact_', JSON.stringify(cookie_array), 365);

    } else {
        var new_cookie = {};
        new_cookie['id']  = validation_data.post_id;
        new_cookie['uid'] = resp_data.decoded.uid;
        var cookies = [];
        cookies.push(new_cookie);

        setCookie('wp_transact_', JSON.stringify(cookies), 365);
    }
}

function getCookie(cname) {
    var name = cname + "=";
    var ca = document.cookie.split(';');
    for(var i = 0; i < ca.length; i++) {
        var c = ca[i];
        while (c.charAt(0) == ' ') {
            c = c.substring(1);
        }
        if (c.indexOf(name) == 0) {
            return c.substring(name.length, c.length);
        }
    }
    return "";
}

function setCookie(cname, cvalue, exdays) {
    var d = new Date();
    d.setTime(d.getTime() + (exdays*24*60*60*1000));
    var expires = "expires="+d.toUTCString();
    document.cookie = cname + "=" + cvalue + ";" + expires + ";path=/";
}