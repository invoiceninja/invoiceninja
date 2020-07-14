<?php
/**
 * Invoice Ninja (https://invoiceninja.com)
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2020. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://opensource.org/licenses/AAL
 */

namespace App\Http\Controllers;

use App\Helpers\Email\InvoiceEmail;
use App\Http\Requests\Email\SendEmailRequest;
use App\Jobs\Invoice\EmailInvoice;
use App\Jobs\Mail\EntitySentMailer;
use App\Models\Credit;
use App\Models\Invoice;
use App\Models\Quote;
use App\Notifications\SendGenericNotification;
use App\Transformers\CreditTransformer;
use App\Transformers\InvoiceTransformer;
use App\Transformers\QuoteTransformer;
use App\Utils\Traits\MakesHash;

class EmailController extends BaseController
{
    use MakesHash;

    protected $entity_type = Invoice::class ;

    protected $entity_transformer = InvoiceTransformer::class ;

    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Returns a template filled with entity variables
     *
     * @return \Illuminate\Http\Response
     *
     * @OA\Post(
     *      path="/api/v1/emails",
     *      operationId="sendEmailTemplate",
     *      tags={"emails"},
     *      summary="Sends an email for an entity",
     *      description="Sends an email for an entity",
     *      @OA\Parameter(ref="#/components/parameters/X-Api-Secret"),
     *      @OA\Parameter(ref="#/components/parameters/X-Requested-With"),
     *      @OA\RequestBody(
     *         description="The template subject and body",
     *         required=true,
     *         @OA\MediaType(
     *             mediaType="application/json",
     *             @OA\Schema(
     *                 type="object",
     *                 @OA\Property(
     *                     property="subject",
     *                     description="The email subject",
     *                     type="string",
     *                 ),
     *                 @OA\Property(
     *                     property="body",
     *                     description="The email body",
     *                     type="string",
     *                 ),
     *                 @OA\Property(
     *                     property="entity",
     *                     description="The entity name",
     *                     type="string",
     *                 ),
     *                 @OA\Property(
     *                     property="entity_id",
     *                     description="The entity_id",
     *                     type="string",
     *                 ),
     *                 @OA\Property(
     *                     property="template",
     *                     description="The template required",
     *                     type="string",
     *                 ),
     *             )
     *         )
     *      ),
     *      @OA\Response(
     *          response=200,
     *          description="success",
     *          @OA\Header(header="X-MINIMUM-CLIENT-VERSION", ref="#/components/headers/X-MINIMUM-CLIENT-VERSION"),
     *          @OA\Header(header="X-RateLimit-Remaining", ref="#/components/headers/X-RateLimit-Remaining"),
     *          @OA\Header(header="X-RateLimit-Limit", ref="#/components/headers/X-RateLimit-Limit"),
     *          @OA\JsonContent(ref="#/components/schemas/Template"),
     *       ),
     *       @OA\Response(
     *          response=422,
     *          description="Validation error",
     *          @OA\JsonContent(ref="#/components/schemas/ValidationError"),

     *       ),
     *       @OA\Response(
     *           response="default",
     *           description="Unexpected Error",
     *           @OA\JsonContent(ref="#/components/schemas/Error"),
     *       ),
     *     )
     */
    public function send(SendEmailRequest $request)
    {
        $entity = $request->input('entity');
        $entity_obj = $entity::find($request->input('entity_id'));
        $subject = $request->input('subject');
        $body = $request->input('body');
        $entity_string = strtolower(class_basename($entity_obj));

        $entity_obj->invitations->each(function ($invitation) use ($subject, $body, $entity_string, $entity_obj) {

            if ($invitation->contact->send_email && $invitation->contact->email) {

                $when = now()->addSeconds(1);

                $invitation->contact->notify((new SendGenericNotification($invitation, $entity_string, $subject, $body))->delay($when));

            }
            
        });

        /*Only notify the admin ONCE, not once per contact/invite*/
        $invitation = $entity_obj->invitations->first();

        EntitySentMailer::dispatch($invitation, $entity_string, $entity_obj->user, $invitation->company); 

        
        if ($this instanceof Invoice) {
            $this->entity_type = Invoice::class ;
            $this->entity_transformer = InvoiceTransformer::class ;
        }

        if ($this instanceof Quote) {
            $this->entity_type = Quote::class ;
            $this->entity_transformer = QuoteTransformer::class ;
        }

        if ($this instanceof Credit) {
            $this->entity_type = Credit::class ;
            $this->entity_transformer = CreditTransformer::class ;
        }

        $entity_obj->service()->markSent()->save();

        return $this->itemResponse($entity_obj);
    }
}
