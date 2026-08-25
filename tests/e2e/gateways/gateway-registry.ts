import { AuthorizePaymentGateway } from './authorize-payment-gateway';
import { BasePaymentGateway } from './base-payment-gateway';
import { BraintreePaymentGateway } from './braintree-payment-gateway';
import { CheckoutPaymentGateway } from './checkout-payment-gateway';
import { GoCardlessPaymentGateway } from './gocardless-payment-gateway';
import { StripePaymentGateway } from './stripe-payment-gateway';

export const paymentGateways: BasePaymentGateway[] = [
    new StripePaymentGateway(),
    new AuthorizePaymentGateway(),
    new CheckoutPaymentGateway(),
    new BraintreePaymentGateway(),
    new GoCardlessPaymentGateway(),
];

export function gatewayBySlug(slug: string): BasePaymentGateway | undefined {
    return paymentGateways.find((gateway) => gateway.slug === slug);
}
