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

use App\Utils\Traits\MakesHash;
use League\CommonMark\CommonMarkConverter;

class TemplateController extends BaseController
{
    use MakesHash;

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
     *      path="/api/v1/templates",
     *      operationId="getShowTemplate",
     *      tags={"templates"},
     *      summary="Returns a entity template with the template variables replaced with the Entities",
     *      description="Returns a entity template with the template variables replaced with the Entities",
     *      @OA\Parameter(ref="#/components/parameters/X-Api-Secret"),
     *      @OA\Parameter(ref="#/components/parameters/X-Requested-With"),
     *      @OA\Parameter(
     *          name="entity",
     *          in="path",
     *          description="The Entity (invoice,quote,recurring_invoice)",
     *          example="invoice",
     *          required=true,
     *          @OA\Schema(
     *              type="string",
     *              format="string",
     *          ),
     *      ),
     *      @OA\Parameter(
     *          name="entity_id",
     *          in="path",
     *          description="The Entity ID",
     *          example="X9f87dkf",
     *          required=true,
     *          @OA\Schema(
     *              type="string",
     *              format="string",
     *          ),
     *      ),
     *      @OA\RequestBody(
     *         description="The template subject and body",
     *         required=true,
     *         @OA\MediaType(
     *             mediaType="application/json",
     *             @OA\Schema(
     *                 type="object",
     *                 @OA\Property(
     *                     property="subject",
     *                     description="The email template subject",
     *                     type="string",
     *                 ),
     *                 @OA\Property(
     *                     property="body",
     *                     description="The email template body",
     *                     type="string",
     *                 ),
     *             )
     *         )
     *      ),
     *      @OA\Response(
     *          response=200,
     *          description="The template response",
     *          @OA\Header(header="X-API-Version", ref="#/components/headers/X-API-Version"),
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
    public function show()
    {
        if (request()->has('entity') && request()->has('entity_id')) {
            $class = 'App\Models\\'.ucfirst(request()->input('entity'));
            $entity_obj = $class::whereId($this->decodePrimaryKey(request()->input('entity_id')))->company()->first();
        }

        $subject = request()->input('subject') ?: '';
        $body = request()->input('body') ?: '';

        $converter = new CommonMarkConverter([
            //'html_input' => 'strip',
            'allow_unsafe_links' => false,
        ]);

        $data = [
            'subject' => request()->input('subject'),
            'body' => $converter->convertToHtml($body),
        ];

        return response()->json($data, 200);
    }
}
