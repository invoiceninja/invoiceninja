<?php
/**
 * @OA\Schema(
 *   schema="Payment",
 *   type="object",
 *       @OA\Property(property="id", type="string", example="Opnel5aKBz", description="The payment hashed id"),
 *       @OA\Property(property="client_id", type="string", example="Opnel5aKBz", description="The client hashed id"),
 *       @OA\Property(property="invitation_id", type="string", example="Opnel5aKBz", description="The invitation hashed id"),
 *       @OA\Property(property="client_contact_id", type="string", example="Opnel5aKBz", description="The client contact hashed id"),
 *       @OA\Property(property="user_id", type="string", example="Opnel5aKBz", description="The user hashed id"),
 *       @OA\Property(property="type_id", type="string", example="1", description="The Payment Type ID"),
 *       @OA\Property(property="date", type="string", example="1-1-2014", description="The Payment date"),
 *       @OA\Property(property="transaction_reference", type="string", example="xcsSxcs124asd", description="The transaction reference as defined by the payment gateway"),
 *       @OA\Property(property="assigned_user_id", type="string", example="Opnel5aKBz", description="The assigned user hashed id"),
 *       @OA\Property(property="private_notes", type="string", example="The payment was refunded due to error", description="The private notes of the payment"),
 *       @OA\Property(property="is_manual", type="boolean", example=true, description="Flags whether the payment was made manually or processed via a gateway"),
 *       @OA\Property(property="is_deleted", type="boolean", example=true, description="Defines if the payment has been deleted"),
 *       @OA\Property(property="amount", type="number", example=10.00, description="The amount of this payment"),
 *       @OA\Property(property="refunded", type="number", example=10.00, description="The refunded amount of this payment"),
 *       @OA\Property(property="updated_at", type="number", format="integer", example="1434342123", description="Timestamp"),
 *       @OA\Property(property="archived_at", type="number", format="integer", example="1434342123", description="Timestamp"),
 *       @OA\Property(property="company_gateway_id", type="string", example="3", description="The company gateway id"),
 *       @OA\Property(property="paymentables",ref="#/components/schemas/Paymentable"),
 *       @OA\Property(
 *       	property="invoices",
 *        	type="array",
 *        	description="",
 *        	@OA\Items(
 *           	ref="#/components/schemas/InvoicePaymentable",
 *          ),
 *       ),
 *       @OA\Property(
 *       	property="credits",
 *        	type="array",
 *        	description="",
 *        	@OA\Items(
 *           	ref="#/components/schemas/CreditPaymentable",
 *          ),
 *       ),
 *
 * )
 */
