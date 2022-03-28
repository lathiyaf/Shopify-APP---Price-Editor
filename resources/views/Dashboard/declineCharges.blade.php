@extends('layouts.app')

@section('styles')
    <link href="https://fonts.googleapis.com/css?family=Raleway:100,600" rel="stylesheet" type="text/css">
  <meta name="csrf-token" content="{{ csrf_token() }}">
    <style>
        html, body {
            background-color: #fff;
            color: #636b6f;
            font-family: 'Raleway', sans-serif;
            font-weight: 100;
            height: 100vh;
            margin: 0;
        }
        .full-height {
            height: 100vh;
        }
        .flex-center {
            align-items: center;
            display: flex;
            justify-content: center;
        }
        .position-ref {
            position: relative;
        }
        .top-right {
            position: absolute;
            right: 10px;
            top: 18px;
        }
        .content {
            text-align: center;
        }
        .title {
            font-size: 84px;
        }
        .links > a {
            color: #636b6f;
            padding: 0 25px;
            font-size: 12px;
            font-weight: 600;
            letter-spacing: .1rem;
            text-decoration: none;
            text-transform: uppercase;
        }
        .m-b-md {
            margin-bottom: 30px;
        }
    </style>
@endsection

@section('content')
<div class="Polaris-Page app-wrapper">
   <div class="Polaris-Page__Header Polaris-Page__Header--hasBreadcrumbs Polaris-Page__Header--hasSecondaryActions">
      <div class="Polaris-Page__MainContent">
         <div class="Polaris-Page__TitleAndActions">
            <div class="Polaris-Page__Title">
               <h1 class="Polaris-DisplayText Polaris-DisplayText--sizeLarge">Charge was declined</h1>
            </div>
         </div>
      </div>
   </div>
   <div class="Polaris-Page__Content">
      <div class="Polaris-Layout">
         <div class="Polaris-Layout__Section clearfix">
            <div class="Polaris-Card">
              <div class="Polaris-Card__Header">
                <h2 class="Polaris-Heading">You have declined the charge for this app.</h2>
              </div>
              <div class="Polaris-Card__Section">
                <div class="Polaris-Card__SectionHeader">
                  <h3 aria-label="Items" class="Polaris-Subheading">We are charging ${{ env('SHOPIFY_BILLING_PRICE', 2.99) }} for app features. If you want to uninstall the app you need to follow below steps:</h3>
                </div>
                {{-- <ul class="Polaris-List Polaris-List--typeBullet">
                  <li class="Polaris-List__Item">1 × Isis Glass, 4-Pack</li>
                  <li class="Polaris-List__Item">1 × Anubis Cup, 2-Pack</li>
                </ul> --}}
              
            
            <p class="c3 c10"><span class="c0"></span></p>
            

            <h2 class="c5" id="h.ognlnx7b18j6"><span class="c15 c12">#1 Delete snippet code from cart.liquid template</span></h2>
            <p class="c3 c10"><span class="c0"></span></p>
            <p class="c3"><span class="c0">Navigate to Online store&gt; Themes&gt; Actions&gt;Edit Code</span></p>
            <p class="c3 c10"><span class="c0"></span></p>
            <p class="c3"><span class="c0">Find your cart template and find the snippet code <code>{% include 'send_as_gift' %}</code> and delete it.</span></p>
            <p class="c3"><span style="overflow: hidden; display: inline-block; margin: 0.00px 0.00px; border: 0.00px solid #000000; transform: rotate(0.00rad) translateZ(0px); -webkit-transform: rotate(0.00rad) translateZ(0px); width: 707.50px; height: 461.46px;"><img alt="" src="/images/image4.png" style="width: 707.50px; height: 461.46px; margin-left: 0.00px; margin-top: 0.00px; transform: rotate(0.00rad) translateZ(0px); -webkit-transform: rotate(0.00rad) translateZ(0px);" title=""></span></p>
            <h2 class="c5" id="h.niy4u25f443j"><span class="c12">#2 Delete Send as Gift snippet from theme</span></h2>
            <p class="c3"><span class="c0">Find "send_as_gift.liquid' under snippets sections in theme, and delete it.</span></p>
            <p class="c3 c10"><span class="c0"></span></p>
            </div>
              {{-- <div class="Polaris-Card__Footer">
                <div class="Polaris-ButtonGroup">
                  <div class="Polaris-ButtonGroup__Item"><a href="/billing" class="Polaris-Button Polaris-Button--primary"><span class="Polaris-Button__Content"><span>Continue for Payment</span></span></a></div>
                  
                </div>
              </div> --}}
            </div>
         </div>
     </div>
    </div>
</div>

@endsection



