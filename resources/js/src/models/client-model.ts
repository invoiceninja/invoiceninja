export default class Client {

	id: number
	name: string
	user_id: number
	company_id: number
	website: string 
	private_notes: string 
	balance: number 
	paid_to_date: number 
	last_login: string 
	industry_id: number 
	size_id: number 
	currency_id: number 
	address1: string 
	address2: string 
	city: string 
	state: string 
	postal_code: string 
	country_id: number 
	latitude: number
	longitude: number
	shipping_latitude: number
	shipping_longitude: number
	custom_value1: string 
	custom_value2: string 
	shipping_address1: string 
	shipping_address2: string 
	shipping_city: string 
	shipping_state: string 
	shipping_postal_code: string 
	shipping_country_id: number 
	is_deleted: boolean 
	payment_terms: string 
	vat_number: string 
	id_number: string 
	created_at: string 
	updated_at: string

	public constructor(init?:Partial<Client>) {
	        (<any>Object).assign(this, init);
	    }
}