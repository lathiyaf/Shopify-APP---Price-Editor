<div style=" display: none"  id="pricing_content">
<div style="padding: 2%; text-align: center;">
    <div class="box-wrapper">
        @unless ($is_payed)
            <div style="font-size:28px;" class="text-center">Upgrade today for unlimited price updates.</div>
        @endunless

        <!-- Pricing Table Section -->
        <section id="pricing-table">
            <div class="row_pricing">
                <div class="pricing row">
                    <div class="col-lg-4 col-md-12 col-sm-12 col-xs-12">
                        <div class="pricing-table">
                            <div class="pricing-header">
                                <p class="pricing-title"></p>

                                <p class="pricing-rate">FREE TRIAL</p>
                                <p class="pricing-title pricing-title-current">
                                    @if(!$is_payed || ($isUsageCharge && !$isUsageChargeMade))
                                            Your current plan
                                    @endif
                                </p>
                            </div>

                            <div class="pricing-list" id="pricing-list-trial">
                                <ul>
                                    <li><span>Free trial is limited to {{$trial_limit}} price edits. Upgrade your plan for unlimited price edits.</span></li>
                                </ul>
                            </div>
                        </div>
                    </div>

                    <div class="col-lg-4 col-md-12 col-sm-12 col-xs-12">
                        <div class="pricing-table">
                            <div class="pricing-header">
                                <p class="pricing-title">Basic</p>
                                <p class="pricing-rate"><sup>$</sup> {{$price_per_month}} <span>/Mo.</span></p>
                                @if($isUsageCharge && !$isUsageChargeMade)
                                    <p class="pricing-title pricing-title-current">Will start automatically</p>
                                @elseif ($is_payed && !$is_pro)
                                    <p class="pricing-title pricing-title-current">Your current plan</p>
                                @else
                                    <a href="/billing" class="btn btn-custom" id="upgrade_plan_btn">Upgrade now</a>
                                @endif

                            </div>

                            <div class="pricing-list">
                                <ul>
                                    <li><span>Unlimited price edits</span></li>
                                </ul>
                            </div>
                        </div>
                    </div>
                    <div class="col-lg-4 col-md-12 col-sm-12 col-xs-12">
                        <div class="pricing-table">
                            <div class="pricing-header">
                                <p class="pricing-title">Pro</p>
                                <p class="pricing-rate"><sup>$</sup> {{$price_pro_per_month}} <span>/Mo.</span></p>
                                @if ($is_pro)
                                    <p class="pricing-title pricing-title-current">Your current plan</p>
                                @else
                                    <a href="/billing?type=pro" class="btn btn-custom" id="upgrade_plan_btn">Upgrade now</a>
                                @endif

                            </div>

                            <div class="pricing-list">
                                <ul>
                                    <li><span>Unlimited price edits and history</span></li>
                                </ul>
                            </div>
                        </div>
                    </div>

                </div>
            </div>
        </section>
        <!-- Pricing Table Section End -->
    </div>


</div>
</div>
