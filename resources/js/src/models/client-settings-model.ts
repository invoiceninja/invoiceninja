export default class ClientSettings {

	timezone_id:number
	language_id:number
	currency_id:number
	default_task_rate:number
	send_reminders:boolean
	show_tasks_in_portal:boolean
	custom_message_dashboard:string
	custom_message_unpaid_invoice:string
	custom_message_paid_invoice:string
	custom_message_unapproved_quote:string
	show_currency_symbol:boolean
	show_currency_code:boolean
	industry_id:number
	size_id:number

	constructor(init?:Partial<ClientSettings>) {
	        (<any>Object).assign(this, init);
	    }
}