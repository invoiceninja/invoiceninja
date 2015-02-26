<?php

use Illuminate\Console\Command;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputArgument;
use ninja\mailers\ContactMailer as Mailer;

class SendRecurringInvoices extends Command {

	protected $name = 'ninja:send-invoices';
	protected $description = 'Send recurring invoices';
	protected $mailer;

	public function __construct(Mailer $mailer)
	{
		parent::__construct();

		$this->mailer = $mailer;
	}

	public function fire()
	{
		$this->info(date('Y-m-d') . ' Running SendRecurringInvoices...');
		$today = new DateTime();			
		
		$invoices = Invoice::with('account.timezone', 'invoice_items', 'client', 'user')
			->whereRaw('is_deleted IS FALSE AND deleted_at IS NULL AND is_recurring IS TRUE AND start_date <= ? AND (end_date IS NULL OR end_date >= ?)', array($today, $today))->get();
		$this->info(count($invoices) . ' recurring invoice(s) found');

		foreach ($invoices as $recurInvoice)
		{
			if ($recurInvoice->client->deleted_at)
			{
				continue;
			}

            if (!$recurInvoice->user->confirmed)
            {
                continue;
            }

			$this->info('Processing Invoice ' . $recurInvoice->id . ' - Should send ' . ($recurInvoice->shouldSendToday() ? 'YES' : 'NO'));
			
			if (!$recurInvoice->shouldSendToday())
			{
				continue;
			}
			
			$invoice = Invoice::createNew($recurInvoice);
			$invoice->client_id = $recurInvoice->client_id;
			$invoice->recurring_invoice_id = $recurInvoice->id;
			$invoice->invoice_number = 'R' . $recurInvoice->account->getNextInvoiceNumber();
			$invoice->amount = $recurInvoice->amount;
			$invoice->balance = $recurInvoice->amount;
			$invoice->invoice_date = date_create()->format('Y-m-d');
			$invoice->discount = $recurInvoice->discount;
			$invoice->po_number = $recurInvoice->po_number;
			$invoice->public_notes = $recurInvoice->public_notes;
			$invoice->terms = $recurInvoice->terms;
			$invoice->tax_name = $recurInvoice->tax_name;
			$invoice->tax_rate = $recurInvoice->tax_rate;
			$invoice->invoice_design_id = $recurInvoice->invoice_design_id;
			$invoice->custom_value1 = $recurInvoice->custom_value1;
			$invoice->custom_value2 = $recurInvoice->custom_value2;
			$invoice->custom_taxes1 = $recurInvoice->custom_taxes1;
			$invoice->custom_taxes2 = $recurInvoice->custom_taxes2;
			$invoice->is_amount_discount = $recurInvoice->is_amount_discount;

			if ($invoice->client->payment_terms)
			{
				$invoice->due_date = date_create()->modify($invoice->client->payment_terms . ' day')->format('Y-m-d');
			}
			
			$invoice->save();
				
			foreach ($recurInvoice->invoice_items as $recurItem)
			{
				$item = InvoiceItem::createNew($recurItem);
				$item->product_id = $recurItem->product_id;
				$item->qty = $recurItem->qty;
				$item->cost = $recurItem->cost;
				$item->notes = Utils::processVariables($recurItem->notes);
				$item->product_key = Utils::processVariables($recurItem->product_key);
				$item->tax_name = $recurItem->tax_name;
				$item->tax_rate = $recurItem->tax_rate;
				$invoice->invoice_items()->save($item);
			}

			foreach ($recurInvoice->invitations as $recurInvitation)
			{
				$invitation = Invitation::createNew($recurInvitation);
				$invitation->contact_id = $recurInvitation->contact_id;
				$invitation->invitation_key = str_random(RANDOM_KEY_LENGTH);
				$invoice->invitations()->save($invitation);
			}

			$this->mailer->sendInvoice($invoice);

			$recurInvoice->last_sent_date = Carbon::now()->toDateTimeString();
			$recurInvoice->save();			
		}		

		$this->info('Done');
	}

	protected function getArguments()
	{
		return array(
			//array('example', InputArgument::REQUIRED, 'An example argument.'),
		);
	}

	protected function getOptions()
	{
		return array(
			//array('example', null, InputOption::VALUE_OPTIONAL, 'An example option.', null),
		);
	}

}