<?php

class PostmarkInbound extends PHPUnit_Framework_TestCase{

    protected function setUp()
    {
        $this->inbound = new \App\Ninja\Tickets\Inbound\InboundTicketFactory(file_get_contents('inbound.json'));
    }

    public function testTo()
    {
        $this->assertEquals($this->inbound->to(), '451d9b70cf9364d23ff6f9d51d870251569e+ahoy@inbound.postmarkapp.com');
    }

    public function testSubject()
    {
        $this->assertEquals($this->inbound->subject(), 'This is an inbound message');
    }

    public function testFromEmail()
    {
        $this->assertEquals($this->inbound->fromEmail(), 'myUser@theirDomain.com');
    }

    public function testFromFull()
    {
        $this->assertEquals($this->inbound->fromFull(), 'John Doe <myUser@theirDomain.com>');
    }

    public function testFromName()
    {
        $this->assertEquals($this->inbound->fromName(), 'John Doe');
    }

    public function testDate()
    {
        $this->assertEquals($this->inbound->date(), 'Thu, 5 Apr 2012 16:59:01 +0200');
    }

    public function testOriginalRecipient()
    {
        $this->assertEquals($this->inbound->originalRecipient(), '451d9b70cf9364d23ff6f9d51d870251569e+ahoy@inbound.postmarkapp.com');
    }

    public function testReplyTo()
    {
        $this->assertEquals($this->inbound->replyTo(), 'myUsersReplyAddress@theirDomain.com');
    }

    public function testMailboxHash()
    {
        $this->assertEquals($this->inbound->mailboxHash(), 'ahoy');
    }

    public function testTag()
    {
        $this->assertEquals($this->inbound->tag(), 'awesome');
    }

    public function testMessageID()
    {
        $this->assertEquals($this->inbound->messageID(), '22c74902-a0c1-4511-804f2-341342852c90');
    }

    public function testTextBody()
    {
        $this->assertEquals(strlen($this->inbound->textBody()), 7);
    }

    public function testHtmlBody()
    {
        $this->assertEquals(strlen($this->inbound->htmlBody()), 15);
    }

    public function testStrippedTextReply()
    {
        $this->assertEquals('Ok, thanks for letting me know!', $this->inbound->strippedTextReply());
    }

    public function testHeaders()
    {
        $this->assertEquals($this->inbound->Headers(), 'No');
        $this->assertEquals($this->inbound->Headers('X-Spam-Status'), 'No');
        $this->assertEquals($this->inbound->Headers('X-Spam-Checker-Version'), 'SpamAssassin 3.3.1 (2010-03-16) onrs-ord-pm-inbound1.wildbit.com');
        $this->assertEquals($this->inbound->Headers('X-Spam-Score'), '-0.1');
        $this->assertEquals($this->inbound->Headers('X-Spam-Tests'), 'DKIM_SIGNED,DKIM_VALID,DKIM_VALID_AU,SPF_PASS');
        $this->assertEquals($this->inbound->Headers('Received-SPF'), 'pass');
        $this->assertEquals($this->inbound->Headers('MIME-Version'), '1.0');
        $this->assertEquals($this->inbound->Headers('Message-ID'), '<CAGXpo2WKfxHWZ5UFYCR3H_J9SNMG+5AXUovfEFL6DjWBJSyZaA@mail.gmail.com>');
    }

    public function testRecipients()
    {
        $recipients = $this->inbound->recipients();
        $this->assertEquals(count($recipients), 2);
        $this->assertEquals($recipients[0]->Email, '451d9b70cf9364d23ff6f9d51d870251569e+ahoy@inbound.postmarkapp.com');
        $this->assertEquals($recipients[0]->Name, FALSE);
        $this->assertEquals($recipients[1]->Email, '451d9b70cf9364d23ff025154f870251569e+ahoy@inbound.postmarkapp.com');
        $this->assertEquals($recipients[1]->Name, 'Ian Tofull');
    }

    public function testUndisclosedRecipients()
    {
        $undisclosed_recipients = $this->inbound->undisclosedRecipients();
        $this->assertEquals(count($undisclosed_recipients), 2);
        $this->assertEquals($undisclosed_recipients[0]->Email, 'sample.cc@emailDomain.com');
        $this->assertEquals($undisclosed_recipients[0]->Name, 'Full name');
        $this->assertEquals($undisclosed_recipients[1]->Email, 'another.cc@emailDomain.com');
        $this->assertEquals($undisclosed_recipients[1]->Name, 'Another Cc');
    }
}