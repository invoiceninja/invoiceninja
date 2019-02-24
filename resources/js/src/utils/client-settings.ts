import CSettings from '../models/client-settings-model';

export default class ClientSettings {

	client_settings:any

	company_settings:any

	settings:any

	languages:any

    currencies:any

    payment_terms:any

    industries:any

    sizes:any


    /**
     * Create a new Client Settings instance.
     */
    constructor(
        client_settings: any, 
        company_settings: any, 
        languages: any,
        currencies: any,
        payment_terms: any,
        industries: any,
        sizes: any
        ) {
    	this.client_settings = client_settings
    	this.company_settings = company_settings
    	this.languages = languages
        this.currencies = currencies
        this.payment_terms = payment_terms
        this.industries = industries
        this.sizes = sizes
    }

    /**
     * Build Settings object
     */
    build() {

        this.settings = new CSettings(this.client_settings)
        if (this.client_settings.currency_id !== null) { 

            this.settings.currency_id = this.currencies.find(obj => {
                                            return obj.id == this.client_settings.currency_id
                                        })

        }

        if(this.client_settings.show_currency_symbol == null)
            this.settings.show_currency_symbol = this.company_settings.show_currency_symbol

        if(this.client_settings.show_currency_code == null)
            this.settings.show_currency_code = this.company_settings.show_currency_code

        if (this.client_settings.language_id !== null) { 

            this.settings.language_id = this.languages.find(obj => {
                                            return obj.id == this.client_settings.language_id
                                        })

        }

        if (this.client_settings.payment_terms !== null) { 

            this.settings.payment_terms = this.payment_terms.find(obj => {
                                            return obj.id == this.client_settings.payment_terms
                                        })
        }

        this.settings.default_task_rate = this.client_settings.default_task_rate ? this.client_settings.default_task_rate : this.company_settings.default_task_rate

        if(this.client_settings.send_reminders)
            this.settings.send_reminders = this.client_settings.send_reminders
        else
            this.settings.send_reminders = this.company_settings.send_reminders

        if(this.client_settings.show_tasks_in_portal)
            this.settings.show_tasks_in_portal = this.client_settings.show_tasks_in_portal
        else
            this.settings.show_tasks_in_portal = this.company_settings.show_tasks_in_portal

        if(this.client_settings.custom_message_dashboard && this.client_settings.custom_message_dashboard.length >=1)
            this.settings.custom_message_dashboard = this.client_settings.custom_message_dashboard
        else
            this.settings.custom_message_dashboard = this.company_settings.custom_message_dashboard

        if(this.client_settings.custom_message_unpaid_invoice && this.client_settings.custom_message_unpaid_invoice.length >=1)
            this.settings.custom_message_unpaid_invoice = this.client_settings.custom_message_unpaid_invoice
        else
            this.settings.custom_message_unpaid_invoice = this.company_settings.custom_message_unpaid_invoice

        if(this.client_settings.custom_message_paid_invoice && this.client_settings.custom_message_paid_invoice.length >=1)
            this.settings.custom_message_paid_invoice = this.client_settings.custom_message_paid_invoice
        else
            this.settings.custom_message_paid_invoice = this.company_settings.custom_message_paid_invoice

        if(this.client_settings.custom_message_unapproved_quote && this.client_settings.custom_message_unapproved_quote.length >=1)
            this.settings.custom_message_unapproved_quote = this.client_settings.custom_message_unapproved_quote
        else
            this.settings.custom_message_unapproved_quote = this.company_settings.custom_message_unapproved_quote

        if (this.client_settings.industry_id !== null) { 

            this.settings.industry_id = this.industries.find(obj => {
                                            return obj.id == this.client_settings.industry_id
                                        })
        }

        if (this.client_settings.size_id !== null) { 

            this.settings.size_id = this.sizes.find(obj => {
                                            return obj.id == this.client_settings.size_id
                                        })
        }         

        return this.settings
    }



}
