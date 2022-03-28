<!DOCTYPE html>
<html lang="en" 5128290355>
    <head>
        <meta charset="utf-8">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ config('shopify-app.app_name') }}</title>

        @yield('styles')
		
		<link rel="stylesheet" href="//maxcdn.bootstrapcdn.com/bootstrap/3.3.7/css/bootstrap.min.css">
		<link rel="stylesheet" href="//sdks.shopifycdn.com/polaris/1.10.2/polaris.min.css" />
		<link rel="stylesheet" href="//cdnjs.cloudflare.com/ajax/libs/font-awesome/4.7.0/css/font-awesome.min.css">
		<link href="{{ asset('/css/dropzone.css') }}" media="screen" rel="stylesheet" type="text/css" />
		<link href="{{ asset('/css/style.css?v=1.3') }}" media="screen" rel="stylesheet" type="text/css" />
        <link href="{{ asset('/css/seaff.css') }}" media="screen" rel="stylesheet" type="text/css" />
        <link href="{{ asset('/css/css.css') }}" media="screen" rel="stylesheet" type="text/css" />
        <link href="{{ asset('/css/jquery.minicolors.css') }}" media="screen" rel="stylesheet" type="text/css" />
		
  
    </head>

    <body>
        <div class="app-wrapper">
            <div class="app-content">
                <main role="main">
                    @yield('content')
                </main>
            </div>
        </div>

        
		
		<script src="//code.jquery.com/jquery-1.12.4.js"></script>
		<script src="//code.jquery.com/ui/1.12.1/jquery-ui.js"></script>
		<script src="//maxcdn.bootstrapcdn.com/bootstrap/3.3.7/js/bootstrap.min.js"></script>
		<script src="//cdnjs.cloudflare.com/ajax/libs/dropzone/4.3.0/min/dropzone.min.js"></script>
		<script src="{{ asset('/js/gift_wrap.js?v=2.3') }}"></script>
		<script src="{{ asset('/js/jquery.minicolors.min.js') }}"></script>
		
        @yield('scripts')
    </body>
</html>