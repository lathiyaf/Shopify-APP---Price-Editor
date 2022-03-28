import React, { Component } from 'react';
import ReactDOM from 'react-dom';
import {AppProvider, Tabs} from '@shopify/polaris';

import en from '@shopify/polaris/locales/en.json';

import '@shopify/polaris/build/esm/styles.css';

import NavigationTabs from './NavigationTabs/NavigationTabs';
import $ from "jquery";

import {getCookie, pressStar, setCookie} from "../custom";

// import 'bootstrap/dist/css/bootstrap.min.css';


let timer = setInterval(() => {
    if(typeof window.sessionToken === 'undefined') {
        return;
    }
    clearInterval(timer)
    ReactDOM.render(
        <AppProvider i18n={en} forceRedirect={true} shopOrigin={SHOPIFY_SHOP_ORIGIN} apiKey={SHOPIFY_API_KEY}>
            <NavigationTabs />
        </AppProvider>,
        document.getElementById('content'),
    );
}, 1000);

$(function() {
    var current_visites = getCookie('price_manager_settings_visits');
    if(current_visites == null){
        current_visites = 0;
    }

    if(current_visites === 2){
        // Get the modal
        var modal = document.getElementById('myModal');
        modal.style.display = "block";
        // Get the <span> element that closes the modal
        var span = document.getElementsByClassName("close_modal")[0];

        // When the user clicks on <span> (x), close the modal
        span.onclick = function() {
            modal.style.display = "none";
        };

        // When the user clicks anywhere outside of the modal, close it
        window.onclick = function(event) {
            if (event.target == modal) {
                modal.style.display = "none";
            }
        }
    }

    current_visites++;
    setCookie('price_manager_settings_visits', current_visites, 365);




    var star_modal = document.getElementsByClassName("star_modal");
    var text_star_modal = document.getElementById("text-star-modal");
    var rate_modal = 5;
    var submit_modal = 1;
    for (var i=0;i<star_modal.length;i++){
        star_modal[i].onclick = function(){
            var numstar = this.getAttribute('data-star');
            rate_modal = pressStar(numstar, text_star_modal, star_modal);
            $('#rating_value_modal').val(rate_modal);
            var feedback = document.getElementsByClassName('feedback_modal');
            if(rate_modal<=3){
                feedback[0].style.display = "block";
                submit_modal = 0;
            }else {
                feedback[0].style.display = "none";
                submit_modal = 1;
            }

        };
    }

    $('#submitbtn_modal').bind('click', function(e)
    {
        if(!submit_modal){
            if($('#note_modal').val() == ''){
                $('#note_modal').css('border-color', 'red');
                return false;
            }
            $('#submit_feedback_form_modal').submit();
            return;

        }else {
            window.open("https://apps.shopify.com/easy-sale-price?reveal_new_review=true&utm_content=contextual&utm_medium=shopify&utm_source=admin");
        }
    });



    $( "#note_modal" ).focus(function() {
        $('#note_modal').css('border-color', '#ccc');
    });


});
