export default class NumberFormat {

	amount:any

	currency:any

	symbol_decorator:boolean

	language:any

    /**
     * Create a new Number Format instance.
     */
    constructor(amount: any, currency: any, symbol_decorator: boolean, language: any) {
    	this.amount = amount
    	this.currency = currency
    	this.symbol_decorator = symbol_decorator
    	this.language = language
    }

    format() {
    	this.amount = new Intl.NumberFormat(this.language.locale.replace("_", "-"), {style: 'decimal',currency: this.currency.code} ).format(this.amount)

    	if(this.symbol_decorator)
    		this.amount = this.currency.symbol + this.amount
    	else
    		this.amount = this.amount + " " + this.currency.code


    	return this.amount
    }


}
