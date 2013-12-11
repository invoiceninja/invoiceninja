<?php

use Illuminate\Console\Command;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputArgument;
use Ninja\Mailers\ContactMailer as Mailer;

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

		$invoices = Invoice::with('account', 'invoice_items')->whereRaw('frequency_id > 0 AND start_date <= ? AND (end_date IS NULL OR end_date >= ?)', array($today, $today))->get();
		$this->info(count($invoices) . ' recurring invoice(s) found');

		foreach ($invoices as $recurInvoice)
		{
			$this->info('Processing Invoice ' . $recurInvoice->id . ' - Should send ' . ($recurInvoice->shouldSendToday() ? 'YES' : 'NO'));

			if (!$recurInvoice->shouldSendToday())
			{
				continue;
			}
			
			$invoice = Invoice::createNew($recurInvoice);									
			$invoice->client_id = $recurInvoice->client_id;
			$invoice->recurring_invoice_id = $recurInvoice->id;
			$invoice->invoice_number = $recurInvoice->account->getNextInvoiceNumber();
			$invoice->total = $recurInvoice->total;
			$invoice->invoice_date = new DateTime();
			$invoice->due_date = new DateTime();
			$invoice->save();
			
			foreach ($recurInvoice->invoice_items as $recurItem)
			{
				$item = InvoiceItem::createNew($recurItem);
				$item->product_id = $recurItem->product_id;
				$item->qty = $recurItem->qty;
				$item->cost = $recurItem->cost;
				$item->notes = Utils::processVariables($recurItem->notes);
				$item->product_key = Utils::processVariables($recurItem->product_key);				
				$invoice->invoice_items()->save($item);				
			}

			$recurInvoice->last_sent_date = new DateTime();
			$recurInvoice->save();

			$this->mailer->sendInvoice($invoice, $invoice->client->contacts()->first());
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