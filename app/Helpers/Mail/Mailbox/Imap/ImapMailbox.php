<?php
/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2023. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\Helpers\Mail\Mailbox\Imap;

use Ddeboer\Imap\MessageInterface;
use Ddeboer\Imap\Server;
use Ddeboer\Imap\SearchExpression;
use Ddeboer\Imap\Search\Date\Since;

class ImapMailbox
{
    private $server;
    public $connection;
    public function __construct(string $server, string $port, string $user, string $password)
    {
        $this->server = new Server($server, $port != '' ? $port : null);

        $this->connection = $this->server->authenticate($user, $password);
    }


    public function getUnprocessedEmails()
    {
        $mailbox = $this->connection->getMailbox('INBOX');

        $search = new SearchExpression();

        // not older than 30days
        $today = new \DateTimeImmutable();
        $thirtyDaysAgo = $today->sub(new \DateInterval('P30D'));
        $search->addCondition(new Since($thirtyDaysAgo));

        return $mailbox->getMessages($search);
    }

    public function moveProcessed(MessageInterface $mail)
    {
        return $mail->move($this->connection->getMailbox('PROCESSED'));
    }

    public function moveFailed(MessageInterface $mail)
    {
        return $mail->move($this->connection->getMailbox('FAILED'));
    }
}
