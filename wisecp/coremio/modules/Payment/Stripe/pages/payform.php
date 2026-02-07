<?php
    $config         = $module->config;
    $lang           = $module->lang;
    $checkout_info  = $module->checkout_info($links);
    $intent         = $checkout_info["intent"];

    if(!$intent) return false;

?>
<div align="center">
    <script type="text/javascript">
        $(function(){
            $.getScript("https://js.stripe.com/v3/", function(){

                var stripe = Stripe(
                    '<?php echo $checkout_info["key"]; ?>',
                    {
                        betas: ['payment_intent_beta_3']
                    }
                );

                var elements = stripe.elements({locale:'<?php echo ___("package/code"); ?>'});

                var style = {
                    base: {
                        color: '#32325d',
                        fontFamily: '"Helvetica Neue", Helvetica, sans-serif',
                        fontSmoothing: 'antialiased',
                        fontSize: '16px',
                        '::placeholder': {
                            color: '#aab7c4'
                        }
                    },
                    invalid: {
                        color: '#fa755a',
                        iconColor: '#fa755a'
                    }
                };

                var cardElement = elements.create('card', {style: style});

                cardElement.mount('#card-element');

                var cardButton      = document.getElementById('SubmitPayment');
                var clientSecret    = '<?php echo $intent->client_secret; ?>';

                cardButton.addEventListener('click', function(event) {

                    var before_html = cardButton.innerHTML;

                    cardButton.innerHTML = '<?php echo __("website/others/button1-pending"); ?>';

                    stripe.handleCardPayment(
                        clientSecret,
                        cardElement
                    ).then(function(result){
                        cardButton.innerHTML = before_html;
                        if (result.error){
                            alert_error(result.error.message,{timer:20000});
                        }else{
                            window.location.href = '<?php echo $links["successful-page"]; ?>';
                        }
                    });
                });

            });
        });
    </script>

    <style>
        .stripepaycon{width:50%;margin:50px 0}
        .stripepaycon h4{opacity:0.5;color:#32325d;font-weight:600}
        #card-element{margin:25px 0}
        @media only screen and (max-width: 1024px) and (min-width: 320px){
            .stripepaycon {width:100%;}
        }
    </style>

    <form action="<?php echo $checkout_info["charge_url"]; ?>" method="post" id="payment-form">
        <div class="stripepaycon">
            <h4><?php echo __("website/account_products/payment-details"); ?></h4>
            <div id="card-element"></div>
            <div class="clear"></div>
            <br>
            <a href="javascript:void 0;" id="SubmitPayment" class="lbtn green"><?php echo $lang["pay-button"]; ?></a>
        </div>
    </form>
</div>