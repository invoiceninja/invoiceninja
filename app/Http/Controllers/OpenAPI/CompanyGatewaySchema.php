<?php
/**
 * @OA\Schema(
 *   schema="CompanyGateway",
 *   type="object",
 *       @OA\Property(property="id", type="string", example="Opnel5aKBz", description="The hashed id of the company gateway"),
 *       @OA\Property(property="company_id", type="string", example="2", description="The company hashed id"),
 *       @OA\Property(property="gateway_key", type="string", example="2", description="The gateway key (hash)"),
 *       @OA\Property(property="accepted_credit_cards", type="integer", example="32", description="Bitmask representation of cards"),
 *       @OA\Property(property="require_billing_address", type="boolean", example=true, description="Determines if the the billing address is required prior to payment."),
 *       @OA\Property(property="require_shipping_address", type="boolean", example=true, description="Determines if the the billing address is required prior to payment."),
 *       @OA\Property(property="config", type="string", example="dfadsfdsafsafd", description="The configuration map for the gateway"),
 *       @OA\Property(property="update_details", type="boolean", example=true, description="Determines if the client details should be updated."),
 *       @OA\Property(
 *       	property="fees_and_limits",
 *        	type="array",
 *        	description="A mapped collection of the fees and limits for the configured gateway",
 *        	@OA\Items(
 *           	ref="#/components/schemas/FeesAndLimits",
 *          ),
 *       ),
 * )
 */
