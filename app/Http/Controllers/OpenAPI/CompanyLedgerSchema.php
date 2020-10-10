<?php
/**
 * @OA\Schema(
 *   schema="CompanyLedger",
 *   type="object",
 *       @OA\Property(property="entity_id", type="string", example="AS3df3A", description="This field will reference one of the following entity hashed ID payment_id, invoice_id or credit_id"),
 *       @OA\Property(property="notes", type="string", example="Credit note for invoice #3212", description="The notes which reference this entry of the ledger"),
 *       @OA\Property(property="balance", type="number", format="float", example="10.00", description="The client balance"),
 *       @OA\Property(property="adjustment", type="number", format="float", example="10.00", description="The amount the client balance is adjusted by"),
 *       @OA\Property(property="updated_at", type="number", format="integer", example="1434342123", description="Timestamp"),
 *       @OA\Property(property="created_at", type="number", format="integer", example="1434342123", description="Timestamp"),
 * )
 */
