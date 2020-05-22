<?php
/**
 * @OA\Schema(
 *   schema="PaymentTerm",
 *   type="object",
 *       @OA\Property(property="num_days", type="integer", example="1", description="The payment term length in days"),
 *       @OA\Property(property="name", type="string", example="NET 1", description="The payment term length in string format"),
 *       @OA\Property(property="created_at", type="number", format="integer", example="134341234234", description="Timestamp"),
 *       @OA\Property(property="updated_at", type="number", format="integer", example="134341234234", description="Timestamp"),
 *       @OA\Property(property="archived_at", type="number", format="integer", example="134341234234", description="Timestamp"),
 * )
 */
