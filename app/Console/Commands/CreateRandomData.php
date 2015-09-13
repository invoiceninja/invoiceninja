<?php namespace App\Console\Commands;

use Illuminate\Console\Command;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputArgument;

class CreateRandomData extends Command {

  protected $name = 'ninja:create-data';
  protected $description = 'Create random data';

  public function fire()
  {
    $this->info(date('Y-m-d') . ' Running CreateRandomData...');

    $user = User::first();

    if (!$user) {
      $this->error("Error: please create user account by logging in");
      return;
    }

    $productNames = ['Arkansas', 'New York', 'Arizona', 'California', 'Colorado', 'Alabama', 'Connecticut', 'Delaware', 'Florida', 'Georgia', 'Hawaii', 'Idaho', 'Illinois', 'Indiana', 'Iowa', 'Kansas', 'Kentucky', 'Louisiana', 'Maine', 'Maryland', 'Massachusetts', 'Michigan', 'Minnesota', 'Mississippi', 'Missouri', 'Montana', 'Nebraska', 'Nevada', 'New Hampshire', 'New Jersey', 'New Mexico', 'Alaska', 'North Carolina', 'North Dakota', 'Ohio', 'Oklahoma', 'Oregon', 'Pennsylvania', 'Rhode Island', 'South Carolina', 'South Dakota', 'Tennessee', 'Texas', 'Utah', 'Vermont', 'Virginia', 'Washington', 'West Virginia', 'Wisconsin', 'Wyoming'];
    $clientNames = ['IBM', 'Nestle', 'Mitsubishi UFJ Financial', 'Vodafone', 'Eni', 'Procter & Gamble', 'Johnson & Johnson', 'American International Group', 'Banco Santander', 'BHP Billiton', 'Pfizer', 'Itaú Unibanco Holding', 'Ford Motor', 'BMW Group', 'Commonwealth Bank', 'EDF', 'Statoil', 'Google', 'Siemens', 'Novartis', 'Royal Bank of Canada', 'Sumitomo Mitsui Financial', 'Comcast', 'Sberbank', 'Goldman Sachs Group', 'Westpac Banking Group', 'Nippon Telegraph & Tel', 'Ping An Insurance Group', 'Banco Bradesco', 'Anheuser-Busch InBev', 'Bank of Communications', 'China Life Insurance', 'General Motors', 'Telefónica', 'MetLife', 'Honda Motor', 'Enel', 'BASF', 'Softbank', 'National Australia Bank', 'ANZ', 'ConocoPhillips', 'TD Bank Group', 'Intel', 'UBS', 'Hewlett-Packard', 'Coca-Cola', 'Cisco Systems', 'UnitedHealth Group', 'Boeing', 'Zurich Insurance Group', 'Hyundai Motor', 'Sanofi', 'Credit Agricole', 'United Technologies', 'Roche Holding', 'Munich Re', 'PepsiCo', 'Oracle', 'Bank of Nova Scotia'];

    foreach ($productNames as $i => $value) {
      $product = Product::createNew($user);
      $product->id = $i+1;
      $product->product_key = $value;
      $product->save();        
    }

    foreach ($clientNames as $i => $value) {
      $client = Client::createNew($user);
      $client->name = $value;
      $client->save();

      $contact = Contact::createNew($user);
      $contact->email = "client@aol.com";
      $contact->is_primary = 1;
      $client->contacts()->save($contact);

      $numInvoices = rand(1, 25);
      if ($numInvoices == 4 || $numInvoices == 10 || $numInvoices == 25) {
        // leave these
      } else if ($numInvoices % 3 == 0) {
        $numInvoices = 1;
      } else if ($numInvoices > 10) {
        $numInvoices = $numInvoices % 2;
      }

      $paidUp = rand(0, 1) == 1;

      for ($j=1; $j<=$numInvoices; $j++) {

        $price = rand(10, 1000);
        if ($price < 900) {
          $price = rand(10, 150);
        }

        $invoice = Invoice::createNew($user);
        $invoice->invoice_number = $user->account->getNextInvoiceNumber();
        $invoice->amount = $invoice->balance = $price;
        $invoice->created_at = date('Y-m-d', strtotime(date("Y-m-d") . ' - ' . rand(1, 100) . ' days'));
        $client->invoices()->save($invoice);

        $productId = rand(0, 40);
        if ($productId > 20) {
          $productId = ($productId % 2) + rand(0, 2);
        }

        $invoiceItem = InvoiceItem::createNew($user);
        $invoiceItem->product_id = $productId+1;
        $invoiceItem->product_key = $productNames[$invoiceItem->product_id];
        $invoiceItem->cost = $invoice->amount;
        $invoiceItem->qty = 1;        
        $invoice->invoice_items()->save($invoiceItem);

        if ($paidUp || rand(0,2) > 1) {
          $payment = Payment::createNew($user);
          $payment->invoice_id = $invoice->id;
          $payment->amount = $invoice->amount;
          $client->payments()->save($payment);
        }
      }
    }
  }
}