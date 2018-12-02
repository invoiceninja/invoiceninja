export default class ClientContact {

	id: number
	client_id: number
	user_id: number
	company_id: number
	first_name: string 
	last_name: string 
	phone: string 
	custom_value1: string 
	custom_value2: string 
	email: string 
	email_verified_at: string 
	confirmation_code: string 
	is_primary: boolean 
	confirmed: boolean 
	failed_logins: number 
	oauth_user_id: string 
	oauth_provider_id: string 
	google_2fa_secret: string 
	accepted_terms_version: string 
	avatar: string 
	avatar_width: string 
	avatar_height: string 
	avatar_size: string 
	db: string 
	password: string 
	remember_token: string
	deleted_at: string 
	created_at: string 
	updated_at: string

	public constructor(init?:Partial<ClientContact>) {
	        (<any>Object).assign(this, init);
	    }

}