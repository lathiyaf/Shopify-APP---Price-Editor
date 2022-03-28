import React, {Component} from 'react';
import $ from "jquery";

import '../../custom.js'

import { pressStar } from '../../custom.js';


/**
 * @return {string}
 */
export default class LeaveFeedback extends Component {
    constructor(props) {
        super(props);
    }


    render() {
        const elementID = "feedback_content";
        let content =  document.getElementById(elementID).innerHTML;
        $('#'+elementID).remove();
        $('#feedback_content_wrap').remove();
        return <div id={"feedback_content"} style={{
            padding: '2%',
            textAlign: "center"
        }} dangerouslySetInnerHTML={{ __html: content}} />
    }

    componentWillUnmount(){
        let content =  $('#feedback_content').html();
        content = "<div id='feedback_content_wrap'><div id='feedback_content'>"+content+"</div></div>";
        $('html').append(content);
        $('#feedback_content_wrap #feedback_content').hide();
    }


    componentDidMount() {
        var star = document.getElementsByClassName("star");
        var text_star = document.getElementById("text-star");
        var rate = 5;
        var submit = 1;
        for (var i=0;i<star.length;i++){
            star[i].onclick = function(){
                var numstar = this.getAttribute('data-star');
                rate = pressStar(numstar, text_star, star);
                $('#rating_value').val(rate);
                var feedback = document.getElementsByClassName('feedback');
                if(rate<=3){
                    feedback[0].style.display = "block";
                    submit = 0;
                }else {
                    feedback[0].style.display = "none";
                    submit = 1;
                }

            };
        }


        $('#submitbtn').bind('click', function(e)
        {

            if(!submit){
                if($('#note').val() == ''){
                    $('#note').css('border-color', 'red');
                    return false;
                }
                $('#submit_feedback_form').submit();
                return;

            }else {
                window.open("https://apps.shopify.com/easy-sale-price?reveal_new_review=true&utm_content=contextual&utm_medium=shopify&utm_source=admin");
            }
        });


        $( "#note" ).focus(function() {
            $('#note').css('border-color', '#ccc');
        });


    }



}

import './LeaveFeedback.css';