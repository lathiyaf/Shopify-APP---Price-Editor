@extends('layouts.default')

@yield('styles')


@section('content')
    <div id="content"></div>

    {!! csrf_field() !!}


    <input type="hidden" id="is_app_payed" value="{{$is_payed}}">


    @include('Dashboard.contact-us')
    @include('Dashboard.help')
    @include('Dashboard.leave-feedback', ['feedback_url' => $feedback_url])
    @include('Dashboard.pricing', [
    'price_per_month' => $price_per_month,
    'price_pro_per_month' => $price_pro_per_month,
    'is_payed' => $is_payed,
    'is_pro' => $is_pro,
    'trial_limit'    => $trial_limit
    ])

@endsection


@section('scripts')
    @parent

    <script>
        var SHOPIFY_API_KEY = '{{ config('shopify-app.api_key') }}';
        var SHOPIFY_SHOP_ORIGIN = '{{ $shopDomain }}';
        var SHOPIFY_TRIAL_MODE_LIMIT = <?php echo $trial_limit ?? 0;?>;
        var SHOPIFY_TRIAL_ITEMS_USED = <?php echo $trial_items_used ?? 0;?>;
        var SHOPIFY_UPDATES_COUNT = <?php echo $updates_count ?? 0;?>;
        var SHOPIFY_IS_TRIAL = <?php echo  $isTrial ?? 1;?>;
        var SHOPIFY_IS_PRO = <?php echo  $is_pro ?? 0;?>;
        var SHOPIFY_IS_USAGE_CHARGE = <?php echo  $isUsageCharge ?? 1;?>;
        var SHOPIFY_IS_USAGE_CHARGE_MADE = <?php echo  $isUsageChargeMade ?? 0;?>;
    </script>
    <script src="{{ asset('js/app.js?version=69') }}"></script>
    <script src="{{ asset('/js/jquery.minicolors.min.js') }}"></script>

    <script type='text/javascript'>
        window.__lo_site_id = 164360;

        (function() {
            var wa = document.createElement('script'); wa.type = 'text/javascript'; wa.async = true;
            wa.src = 'https://d10lpsik1i8c69.cloudfront.net/w.js';
            var s = document.getElementsByTagName('script')[0]; s.parentNode.insertBefore(wa, s);
        })();
    </script>
    <script src="//code.tidio.co/3oknuul4mo9fl0ghkopsloiobdqox1xb.js" async></script>
@endsection

