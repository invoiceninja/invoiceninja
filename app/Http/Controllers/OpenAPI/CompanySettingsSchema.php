<?php
/**
 * @OA\Schema(
 *   schema="CompanySettings",
 *   type="object",
 *       @OA\Property(property="timezone_id", type="string", example="15", description="The timezone id"),
 *       @OA\Property(property="date_format_id", type="string", example="15", description="____________"),
 *       @OA\Property(property="datetime_format_id", type="string", example="15", description="____________"),
 *       @OA\Property(property="military_time", type="boolean", example=true, description="____________"),
 * )
 */


        start_of_week: 
          type: integer
          description: 'References the start day of the week'
          example: 1
        financial_year_start:
          type: string
          example: "2000-01-01"
          description: 'The start date of the financial year for the company'
        language_id: 
          type: integer
          example: 1
        currency_id:
          type: integer
          example: 1
        precision: 
          type: integer
          example: 2
          description: 'References the default currency precision'
        show_currency_symbol:
          type: boolean
        show_currency_code:
          type: boolean
        payment_terms:
          type: integer
          example: 7
          description: 'The company level default payments terms'
        custom_label1:
          type: string
          nullable: true
          example: 'A custom label for a custom value - 1'
        custom_value1: 
          type: string
          nullable: true
          example: 'A custom value for a custom label - 1'
        custom_label2:
          type: string
          nullable: true
          example: 'A custom label for a custom value - 2'
        custom_value2: 
          type: string
          nullable: true
          example: 'A custom value for a custom label - 2'
        custom_label3:
          type: string
          nullable: true
          example: 'A custom label for a custom value - 3'
        custom_value3: 
          type: string
          nullable: true
          example: 'A custom value for a custom label - 3'
        custom_label4:
          type: string
          nullable: true
          example: 'A custom label for a custom value - 4'
        custom_value4: 
          type: string
          nullable: true
          example: 'A custom value for a custom label - 4'
        custom_client_label1: 
          type: string
          nullable: true
          example: 'A custom value for a custom client label - 1'
        custom_client_label2: 
          type: string
          nullable: true
          example: 'A custom value for a custom client label - 2'
        custom_client_label3: 
          type: string
          nullable: true
          example: 'A custom value for a custom client label - 3'
        custom_client_label4: 
          type: string
          nullable: true
          example: 'A custom value for a custom client label - 4'
        custom_client_contact_label1:
          type: string
          nullable: true
          example: 'A custom value for a custom client contact label - 1'
        custom_client_contact_label2: 
          type: string
          nullable: true
          example: 'A custom value for a custom client contact label - 2' 
        custom_client_contact_label3: 
          type: string
          nullable: true
          example: 'A custom value for a custom client contact label - 3'
        custom_client_contact_label4:
          type: string
          nullable: true
          example: 'A custom value for a custom client contact label - 4'
        custom_invoice_label1: 
          type: string
          nullable: true
          example: 'A custom value for a custom invoice label - 1'
        custom_invoice_label2: 
          type: string
          nullable: true
          example: 'A custom value for a custom invoice label - 2'
        custom_invoice_label3:
          type: string
          nullable: true
          example: 'A custom value for a custom invoice label - 3'
        custom_invoice_label4:
          type: string
          nullable: true
          example: 'A custom value for a custom invoice label - 4'
        custom_product_label1: 
          type: string
          nullable: true
          example: 'A custom value for a custom product label - 1'
        custom_product_label2:
          type: string
          nullable: true
          example: 'A custom value for a custom product label - 2'
        custom_product_label3:
          type: string
          nullable: true
          example: 'A custom value for a custom product label - 3'
        custom_product_label4:
          type: string
          nullable: true
          example: 'A custom value for a custom product label - 4'
        custom_task_label1: 
          type: string
          nullable: true
          example: 'A custom value for a custom task label - 1'
        custom_task_label2: 
          type: string
          nullable: true
          example: 'A custom value for a custom task label - 2'
        custom_task_label3:
          type: string
          nullable: true
          example: 'A custom value for a custom task label - 3'
        custom_task_label4:
          type: string
          nullable: true
          example: 'A custom value for a custom task label - 4'
        custom_expense_label1: 
          type: string
          nullable: true
          example: 'A custom value for a custom expense label - 1'
        custom_expense_label2: 
          type: string
          nullable: true
          example: 'A custom value for a custom expense label - 2'
        custom_expense_label3:
          type: string
          nullable: true
          example: 'A custom value for a custom expense label - 3'
        custom_expense_label4:
          type: string
          nullable: true
          example: 'A custom value for a custom expense label - 4'
        custom_taxes1:
          type: boolean
          description: 'Determines whether custom fields are also taxed'
        custom_taxes2: 
          type: boolean
          description: 'Determines whether custom fields are also taxed'
        default_task_rate:
          type: number
          example: 10.00
          format: double
          description: 'The company level default task rate'
        send_reminders:
          type: boolean
          description: 'Flag for sending reminders'
        show_tasks_in_portal:
          type: boolean
          description: 'Flag for showing tasks in the client portal'
        custom_message_dashboard:
          type: string
          nullable: true
          example: 'Please pay invoices immediately'
          description: 'Pins a permanent message to the dashboard'
        custom_message_unpaid_invoice:
          type: string
          nullable: true
          example: 'Please pay invoice immediately'
          description: 'Pins a permanent message to a unpaid invoice'    
        custom_message_paid_invoice:
          type: string
          nullable: true
          example: 'Thanks for paying this invoice!'
          description: 'Pins a permanent message to a paid invoice'  
        custom_message_unapproved_quote:
          type: string
          nullable: true
          example: 'Please approve quote'
          description: 'Pins a permanent message to a quote'  
        lock_sent_invoices: 
          type: boolean
          description: 'Prevents an invoice from modification after it has been sent / marked sent'
        auto_archive_invoice:
          type: boolean
          description: 'Flag for automatically archiving an invoice on successful payment'
        inclusive_taxes: 
          type: boolean
          description: 'Flag for the use of inclusive taxes, TRUE = inclusive, FALSE = exclusive'
        translations: 
          type: object
          description: 'Allows a user to override the default translation to use their own specific translations'
          properties:
            key:
              type: string
              example: 'invoice'
              description: 'The key'
            value:
              type: string
              example: 'Facturer'
              description: 'The custom translated value'
        invoice_number_prefix:
          type: string
          example: 'R'
          description: 'This string is prepended to the invoice number'
        invoice_number_pattern: 
          type: string
          nullable: true
          example: '{$year}-{$counter}'
          description: 'Allows customisation of the invoice number pattern'
        invoice_number_counter:
          type: integer
          example: 200
          description: 'The next invoice number counter.'
        quote_number_prefix: 
          type: string
          example: 'Q'
          description: 'This string is prepended to the quote number'
        quote_number_pattern:
          type: string
          nullable: true
          example: '{$year}-{$counter}'
          description: 'Allows customisation of the quote number pattern'
        quote_number_counter:
          type: integer
          example: 200
          description: 'The next quote number counter.'
        client_number_prefix: 
          type: string
          example: 'C'
          description: 'This string is prepended to the client number'
        client_number_pattern: 
          type: string
          nullable: true
          example: '{$year}-{$counter}'
          description: 'Allows customisation of the client number pattern'
        client_number_counter:
          type: integer
          example: 200
          description: 'The next client number counter.'
        credit_number_prefix:
          type: string
          example: 'CREDIT-'
          description: 'This string is prepended to the credit number'
        credit_number_pattern:
          type: string
          nullable: true
          example: '{$year}-{$counter}'
          description: 'Allows customisation of the credit number pattern'
        credit_number_counter:
          type: integer
          example: 200
          description: 'The next credit number counter.'
        shared_invoice_quote_counter:
          type: boolean
          description: 'Flags whether to share the counter for invoices and quotes'
        recurring_invoice_number_prefix:
          type: string
          example: 'R'
          description: 'This string is prepended to the recurring invoice number'
        reset_counter_frequency_id: 
          type: integer
          example: 1
          description: 'CONSTANT which is used to apply the frequency which the counters are reset'
        reset_counter_date: 
          type: string
          nullable: true
          example: '2019-01-01'
          description: 'The explicit date which is used to reset counters'
        counter_padding: 
          type: integer
          example: 0
          description: 'Pads the counter with leading zeros'
        default_gateway: 
          type: integer
          example: 1
          description: 'The company level payment gateway to be used'
        entity: 
          type: string
          example: 'App\\Models\\Company'
          description: 'The entity which these settings apply - allows generic handling'