<div style= "padding: 2%; text-align: center; display: none" id="feedback_content">
    <form id="submit_feedback_form" action="{{$feedback_url}}" method="POST"  class="" enctype="multipart/form-data">
        <p class="paragraph">Thank you for your interest in our app. Please provide us your feedback so we can continuously improve the app.</p>
        <div class="wrap" style="text-align:center;">
            <div class="rate-area">
                <div data-star="1" class="1-star star">
                    <div class="icon"><i class="fa fa-star" aria-hidden="true"></i></div>
                </div>
                <div data-star="2" class="2-star star">
                    <div class="icon"><i class="fa fa-star" aria-hidden="true"></i></div>
                </div>
                <div data-star="3" class="3-star star">
                    <div class="icon"><i class="fa fa-star" aria-hidden="true"></i></div>
                </div>
                <div data-star="4" class="4-star star">
                    <div class="icon"><i class="fa fa-star" aria-hidden="true"></i></div>
                </div>
                <div data-star="5" class="5-star star">
                    <div class="icon"><i class="fa fa-star" aria-hidden="true"></i></div>
                </div>
            </div>


        </div>
        <div class="wrapfeedback">
            <div class="feedback" style="display: none;">
                <label class="label" for="note">Why this app is rated <span id="text-star">3</span> star?</label>
                <textarea id="note" name="feedback" rows="5"></textarea>
            </div>
        </div>
        <input type="hidden" id="rating_value" name="rating_value">
        <button type="button" id="submitbtn" class="btn-submit" name="button">Rate</button>
    </form>
    <div id="thank" class="thank">
        <h1>Thanks for your feedback.</h1>
    </div>
</div>



<div id="myModal" class="modal">

    <!-- Modal content -->
    <div class="modal-content" style="text-align: center;">
        <span class="close close_modal">&times;</span>
        <div class="modal_header" style="border-bottom: solid 1px #dfe3e8;">
            <h3 style="text-align: left; margin-top:0; margin-bottom: 15px;">Your Feedback</h3>

        </div>

        <form id="submit_feedback_form_modal" action="{{$feedback_url}}" method="POST"  class="" enctype="multipart/form-data">
            {!! csrf_field() !!}                             <p class="paragraph">Thank you for your interest in our app. Please provide us your feedback so we can continuously improve the app.</p>
            <div class="wrap" style="text-align:center;">
                <div class="rate-area">
                    <div data-star="1" class="1-star star_modal">
                        <div class="icon"><i class="fa fa-star" aria-hidden="true"></i></div>
                    </div>
                    <div data-star="2" class="2-star star_modal">
                        <div class="icon"><i class="fa fa-star" aria-hidden="true"></i></div>
                    </div>
                    <div data-star="3" class="3-star star_modal">
                        <div class="icon"><i class="fa fa-star" aria-hidden="true"></i></div>
                    </div>
                    <div data-star="4" class="4-star star_modal">
                        <div class="icon"><i class="fa fa-star" aria-hidden="true"></i></div>
                    </div>
                    <div data-star="5" class="5-star star_modal">
                        <div class="icon"><i class="fa fa-star" aria-hidden="true"></i></div>
                    </div>
                </div>


            </div>
            <div class="wrapfeedback">
                <div class="feedback_modal" style="display: none;">
                    <label class="label" for="note_modal">Why this app is rated <span id="text-star-modal">3</span> star?</label>
                    <textarea id="note_modal" name="feedback" rows="5"></textarea>
                </div>
            </div>
            <input type="hidden" id="rating_value_modal" name="rating_value">
            <button type="button" id="submitbtn_modal" class="btn-submit" name="button">Rate</button>
        </form>
        <div id="thank" class="thank">
            <h1>Thanks for your feedback.</h1>
        </div>
    </div>

</div>

