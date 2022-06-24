<?php
/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2022. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\Helpers\Mail;

use App\Models\User;
use App\Utils\TempFile;
use Dacastro4\LaravelGmail\Facade\LaravelGmail;
use Dacastro4\LaravelGmail\Services\Message\Mail;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mailer\Transport\AbstractTransport;
use Symfony\Component\Mime\MessageConverter;
/**
 * GmailTransport.
 */
class GmailTransport extends AbstractTransport
{
    /**
     * The Gmail instance.
     *
     * @var Mail
     */
    public $gmail;

    public $body;

    /**
     * Create a new Gmail transport instance.
     *
     * @param Mail $gmail
     * @param string $token
     */
    public function __construct()
    {
        parent::__construct();
        $this->gmail = new Mail;
        $this->body = new \Google\Service\Gmail\Message();

    }

    protected function doSend(SentMessage $message): void
    {
        nlog("in Do Send");
        $message = MessageConverter::toEmail($message->getOriginalMessage());

        // $token = $message->getHeaders()->get('GmailToken')->getValue();
        // $message->getHeaders()->remove('GmailToken');

nlog($message->getHeaders()->get('Tag')->getValue());

        $token = '{"access_token":"ya29.a0ARrdaM_XgDGugpxwbHBCDQJgfOvuDfX_6d_PwC-g7e3TUMmym7aquhkVkLvpp92V3bq9LIP-mur289nITVkadeea5UhI667f8KMi836cdZFdYfYwm9yFTshUNozvegkNRtXIrD2LzzAZrIXH7kr1NilP5zyV6w","expires_in":3598,"scope":"openid https:\/\/www.googleapis.com\/auth\/userinfo.profile https:\/\/www.googleapis.com\/auth\/gmail.send https:\/\/www.googleapis.com\/auth\/userinfo.email","token_type":"Bearer","id_token":"eyJhbGciOiJSUzI1NiIsImtpZCI6IjJiMDllNzQ0ZDU4Yzk5NTVkNGYyNDBiNmE5MmY3YjM3ZmVhZDJmZjgiLCJ0eXAiOiJKV1QifQ.eyJpc3MiOiJodHRwczovL2FjY291bnRzLmdvb2dsZS5jb20iLCJhenAiOiIzMjA4NDc1MzU1ODQtNHZvcHZ0OHY5MDY0aHM3a2plM2h0b25yMHZscHVvcm4uYXBwcy5nb29nbGV1c2VyY29udGVudC5jb20iLCJhdWQiOiIzMjA4NDc1MzU1ODQtNHZvcHZ0OHY5MDY0aHM3a2plM2h0b25yMHZscHVvcm4uYXBwcy5nb29nbGV1c2VyY29udGVudC5jb20iLCJzdWIiOiIxMDgyMDM3MTY1NjYwMjI5MjQ1OTMiLCJlbWFpbCI6InR1cmJvMTI0QGdtYWlsLmNvbSIsImVtYWlsX3ZlcmlmaWVkIjp0cnVlLCJhdF9oYXNoIjoiRjdzT1lvRUw0SU1qN1lGbUtvY0YydyIsIm5hbWUiOiJEYXZpZCBCb21iYSIsInBpY3R1cmUiOiJodHRwczovL2xoMy5nb29nbGV1c2VyY29udGVudC5jb20vYS0vQU9oMTRHaDMxSVFfYXBNME9GLWRKSzEwMnF1OFpIc1YtMHpyV2U2MTdzbEpiTW89czk2LWMiLCJnaXZlbl9uYW1lIjoiRGF2aWQiLCJmYW1pbHlfbmFtZSI6IkJvbWJhIiwibG9jYWxlIjoiZW4tR0IiLCJpYXQiOjE2NTYwNjI4NzUsImV4cCI6MTY1NjA2NjQ3NX0.ZeRLJ9jQA8bhVmyaGOrrk-stxsM4VU8fACmwWL6PNXAoFMbswRvrqj0LUnFk2aaswkHOXAG-BowKRfd2RoZ9SE_JlqFNBzaV09XrhtsdSkos7YrOIme2vu2qLT7fsYpkiwwcc9Dvv_TXJx0WX9sm-XrhPc86AWBJ9n2qpTed2hE_RZW4UnbjgxM2l7mnNXWFWK0uod4GAHHewhvQuz13Qk1Mf5ySxCdnNawzM5uKHso5RC3TH-q4aDIsIA4afTIxQx3qbHbvqzEYgWLukWSKXpU1F7Afwbok83Kh7_SXQVNAkOnmlnuEseG2YzpdtkTColuMTndPxD0Gt5A4WRuq7A","created":1656062875,"refresh_token":"1\/\/0dIt2gfW1if-aCgYIARAAGA0SNwF-L9Ir9GOXDuIyv6dIR-gw9ciugTefnUinn09-wjIhU4V0mC5G6x8mA0qzyV1fWhE6PbZI99Q"}';

        // $this->beforeSendPerformed($message);
        
        $this->gmail->using($token);
        $this->gmail->to( collect($message->getTo())->map(function ($email) {
                return ['email' => $email->getAddress(), 'type' => 'to'];
            }));

        $this->gmail->from( collect($message->getFrom())->map(function ($email) {
                return ['email' => $email->getAddress(), 'type' => 'from'];
            }));

        $this->gmail->subject($message->getSubject());
        $this->gmail->message($message->getHtmlBody());
        $this->gmail->cc($message->getCc());




        if(is_array($message->getBcc()))
            $this->gmail->bcc(array_keys($message->getBcc()));

nlog("c");
nlog($message->getAttachments());
nlog("c1");
// nlog($message->getAttachments());

//         foreach ($message->getAttachments() as $child) 
//         {

// nlog($child);

//             if($child->getContentType() != 'text/plain')
//             {

//                 $this->gmail->attach(TempFile::filePath($child->getBody(), $child->getHeaders()->get('Content-Type')->getParameter('name') ));
            
//             }

//         } 

nlog("d");
        $this->gmail->send();
        // $this->gmail->service->users_messages->send('me', $this->body,[]);
        
    }
 
     public function __toString(): string
    {
        return 'gmail';
    }

}
