class ProcessSOFORT {
    constructor(key) {
        this.key = key;
    }

    setupStripe = () => {
        this.stripe = Stripe(this.key);

        return this;
    };

    handle = () => {
        let data = {
            type: 'sofort',
            amount: Math.round(
                document.querySelector('meta[name="amount"]').content
            ),
            currency: 'eur',
            redirect: {
                return_url: document.querySelector('meta[name="return-url"]')
                    .content,
            },
            sofort: {
                country: 'DE',
            },
        };

        document.getElementById('pay-now').addEventListener('submit', (e) => {
            e.preventDefault();

            this.stripe.createSource(data).then(function(result) {
                if (result.hasOwnProperty('source')) {
                    return window.location = result.source.redirect.url;
                }

                console.log(result.error);
            });
        });
    };
}

const publishableKey = document.querySelector(
    'meta[name="stripe-publishable-key"]'
).content;

new ProcessSOFORT(publishableKey).setupStripe().handle();
