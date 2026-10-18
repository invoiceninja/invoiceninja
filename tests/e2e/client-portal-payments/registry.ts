import { paymentGateways as corePaymentGateways } from '../gateways/gateway-registry';
import { type BasePaymentGateway } from '../gateways/base-payment-gateway';
import { PayPalPaymentGateway } from './paypal/payment-gateway';

export const paymentGateways: BasePaymentGateway[] = [
    new PayPalPaymentGateway(),
    ...corePaymentGateways,
];
