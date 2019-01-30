<?php

class PostmarkInboundTicket extends Illuminate\Foundation\Testing\TestCase
{

    protected function setUp()
    {
        $this->createApplication();

       // $this->inbound = new \App\Ninja\Tickets\Inbound\InboundTicketFactory(file_get_contents(__DIR__ . '/inbound.json'));

        $this->inboundTicketService = new \App\Ninja\Tickets\Inbound\InboundTicketService(new \App\Ninja\Tickets\Inbound\InboundTicketFactory(file_get_contents(__DIR__ . '/inbound_ticket.json')), new \App\Ninja\Repositories\TicketRepository());

    }


    public function testProcess()
    {
        $this->inboundTicketService->process();
        $this->assertEquals(true, true);
    }

    public function createApplication()
    {

        $app = require __DIR__.'/../../bootstrap/app.php';
        $app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
        return $app;

    }
}