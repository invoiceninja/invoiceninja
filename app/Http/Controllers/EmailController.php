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

namespace App\Http\Controllers;

use App\Events\Credit\CreditWasEmailed;
use App\Events\Quote\QuoteWasEmailed;
use App\Http\Requests\Email\SendEmailRequest;
use App\Jobs\Entity\EmailEntity;
use App\Jobs\PurchaseOrder\PurchaseOrderEmail;
use App\Models\Credit;
use App\Models\Invoice;
use App\Models\PurchaseOrder;
use App\Models\Quote;
use App\Models\RecurringInvoice;
use App\Services\Email\Email;
use App\Services\Email\EmailObject;
use App\Transformers\CreditTransformer;
use App\Transformers\InvoiceTransformer;
use App\Transformers\PurchaseOrderTransformer;
use App\Transformers\QuoteTransformer;
use App\Transformers\RecurringInvoiceTransformer;
use App\Utils\Ninja;
use App\Utils\Traits\MakesHash;
use Illuminate\Http\Response;
use Illuminate\Mail\Mailables\Address;

class EmailController extends BaseController
{
    use MakesHash;

    protected $entity_type = Invoice::class;

    protected $entity_transformer = InvoiceTransformer::class;

    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Returns a template filled with entity variables.
     *
     * @param SendEmailRequest $request
     * @return Response
     *
     * @OA\Post(
     *      path="/api/v1/emails",
     *      operationId="sendEmailTemplate",
     *      tags={"emails"},
     *      summary="Sends an email for an entity",
     *      description="Sends an email for an entity",
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
        $entity_obj = $entity::withTrashed()->with('invitations')->find($request->input('entity_id'));
        $subject = $request->has('subject') ? $request->input('subject') : '';
        $body = $request->has('body') ? $request->input('body') : '';
        $template = str_replace('email_template_', '', $request->input('template'));

        $data = [
            'subject' => $subject,
            'body' => $body,
        ];

        $mo = new EmailObject;
        $mo->subject = strlen($subject) > 3 ? $subject : null;
        $mo->body = strlen($body) > 3 ? $body : null;
        $mo->entity_id = $request->input('entity_id');
        $mo->template = $request->input('template'); //full template name in use
        $mo->entity_class = $this->resolveClass($entity);
        $mo->email_template_body = $request->input('template');
        $mo->email_template_subject = str_replace("template", "subject", $request->input('template'));

        if ($request->has('cc_email')) {
            $mo->cc[] = new Address($request->cc_email);
        }

        // if ($entity == 'purchaseOrder' || $entity == 'purchase_order' || $template == 'purchase_order' || $entity == 'App\Models\PurchaseOrder') {
        //     return $this->sendPurchaseOrder($entity_obj, $data, $template);
        // }

        $entity_obj->invitations->each(function ($invitation) use ($data, $entity_obj, $template, $mo) {
            if (! $invitation->contact->trashed() && $invitation->contact->email) {
                $entity_obj->service()->markSent()->save();

                // EmailEntity::dispatch($invitation->fresh(), $invitation->company, $template, $data);

                $mo->invitation_id = $invitation->id;

                Email::dispatch($mo, $invitation->company);
            }
        });

        $entity_obj = $entity_obj->fresh();
        $entity_obj->last_sent_date = now();
        $entity_obj->save();

        /*Only notify the admin ONCE, not once per contact/invite*/
        if ($entity_obj instanceof Invoice) {
            $this->entity_type = Invoice::class;
            $this->entity_transformer = InvoiceTransformer::class;

            if ($entity_obj->invitations->count() >= 1) {
                $entity_obj->entityEmailEvent($entity_obj->invitations->first(), 'invoice', $template);
            }
        }

        if ($entity_obj instanceof Quote) {
            $this->entity_type = Quote::class;
            $this->entity_transformer = QuoteTransformer::class;

            if ($entity_obj->invitations->count() >= 1) {
                event(new QuoteWasEmailed($entity_obj->invitations->first(), $entity_obj->company, Ninja::eventVars(auth()->user() ? auth()->user()->id : null), 'quote'));
            }
        }

        if ($entity_obj instanceof Credit) {
            $this->entity_type = Credit::class;
            $this->entity_transformer = CreditTransformer::class;

            if ($entity_obj->invitations->count() >= 1) {
                event(new CreditWasEmailed($entity_obj->invitations->first(), $entity_obj->company, Ninja::eventVars(auth()->user() ? auth()->user()->id : null), 'credit'));
            }
        }

        if ($entity_obj instanceof RecurringInvoice) {
            $this->entity_type = RecurringInvoice::class;
            $this->entity_transformer = RecurringInvoiceTransformer::class;
        }

        if ($entity_obj instanceof PurchaseOrder) {
            $this->entity_type = PurchaseOrder::class;
            $this->entity_transformer = PurchaseOrderTransformer::class;
        }

        // @phpstan-ignore-next-line
        return $this->itemResponse($entity_obj->fresh());
    }

    private function sendPurchaseOrder($entity_obj, $data, $template)
    {
        $this->entity_type = PurchaseOrder::class;

        $this->entity_transformer = PurchaseOrderTransformer::class;

        $data['template'] = $template;
        
        PurchaseOrderEmail::dispatch($entity_obj, $entity_obj->company, $data);
        
        return $this->itemResponse($entity_obj);
    }

    private function resolveClass(string $entity): string
    {
        match ($entity) {
            'invoice' => $class = Invoice::class,
            'App\Models\Invoice' => $class = Invoice::class,
            'credit' => $class = Credit::class,
            'App\Models\Credit' => $class = Credit::class,
            'quote' => $class = Quote::class,
            'App\Models\Quote' => $class = Quote::class,
            'purchase_order' => $class = PurchaseOrder::class,
            'purchaseOrder' => $class = PurchaseOrder::class,
            'App\Models\PurchaseOrder' => $class = PurchaseOrder::class,
            default => $class = Invoice::class,
        };

        return $class;
    }
}
