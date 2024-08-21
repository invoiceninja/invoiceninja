export declare const masks: {
    visa: {
        final: RegExp;
        start: RegExp;
        length: RegExp;
    };
    mastercard: {
        final: RegExp;
        start: RegExp;
        length: RegExp;
    };
    amex: {
        final: RegExp;
        start: RegExp;
        length: RegExp;
    };
    discover: {
        final: RegExp;
        start: RegExp;
        length: RegExp;
    };
    diners: {
        final: RegExp;
        start: RegExp;
        length: RegExp;
    };
    jcb: {
        final: RegExp;
        start: RegExp;
        length: RegExp;
    };
};

export declare const numbers: RegExp;

export declare type Options = {
    fields: {
        card: {
            number: string | HTMLInputElement;
            date: string | HTMLInputElement;
            cvv: string | HTMLInputElement;
            name?: string | HTMLInputElement;
        };
    };
};

export declare class SimpleCard {
    #private;
    options: Options;
    number: HTMLInputElement;
    date: HTMLInputElement;
    cvv: HTMLInputElement;
    constructor(options: Options);
    mount(): this;
    check(): {
        valid: boolean;
        number: {
            valid: boolean;
            value: string;
        };
        date: {
            valid: boolean;
            value: string;
        };
        cvv: {
            valid: boolean;
            value: string;
        };
    };
    type(): "visa" | "mastercard" | "amex" | "discover" | "diners" | "jcb" | "unknown";
}

export declare type TypeChangeOptions = {
    type: string;
    value: string;
    valid: boolean;
};

export { }
