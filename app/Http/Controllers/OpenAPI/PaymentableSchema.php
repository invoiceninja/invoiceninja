<?php
/**
 * @OA\Schema(
 *   schema="Paymentable",
 *   type="object",
 *       @OA\Property(property="id", type="string", example="AS3df3A", description="The paymentable hashed id"),
 *       @OA\Property(property="invoice_id", type="string", example="AS3df3A", description="The invoice hashed id"),
 *       @OA\Property(property="credit_id", type="string", example="AS3df3A", description="The credit hashed id"),
 *       @OA\Property(property="refunded", type="number", format="float", example="10.00", description="The amount that has been refunded for this payment"),
 *       @OA\Property(property="amount", type="number", format="float", example="10.00", description="The amount that has been applied to the payment"),
 *       @OA\Property(property="updated_at", type="number", format="integer", example="1434342123", description="Timestamp"),
 *       @OA\Property(property="created_at", type="number", format="integer", example="1434342123", description="Timestamp"),*
 * )
 */
