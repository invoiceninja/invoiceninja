<?php
/**
 * @OA\Schema(
 *   schema="FeesAndLimits",
 *   type="object",
 *       @OA\Property(property="min_limit", type="string", example="2", description="The minimum amount accepted for this gateway"),
 *       @OA\Property(property="max_limit", type="string", example="2", description="The maximum amount accepted for this gateway"),
 *       @OA\Property(property="fee_amount", type="number", format="float", example="2.0", description="The gateway fee amount"),
 *       @OA\Property(property="fee_percent", type="number", format="float", example="2.0", description="The gateway fee percentage"),
 *       @OA\Property(property="fee_tax_name1", type="string", example="GST", description="Fee tax name"),
 *       @OA\Property(property="fee_tax_name2", type="string", example="VAT", description="Fee tax name"),
 *       @OA\Property(property="fee_tax_name3", type="string", example="CA Sales Tax", description="Fee tax name"),
 *       @OA\Property(property="fee_tax_rate1", type="number", format="float", example="10.0", description="The tax rate"),
 *       @OA\Property(property="fee_tax_rate2", type="number", format="float", example="17.5", description="The tax rate"),
 *       @OA\Property(property="fee_tax_rate3", type="number", format="float", example="25.0", description="The tax rate"),
 *       @OA\Property(property="fee_cap", type="number", format="float", example="2.0", description="If set the fee amount will be no higher than this amount"),
 *       @OA\Property(property="adjust_fee_percent", type="boolean", example=true, description="Adjusts the fee to match the exact gateway fee."),
 * )
 */
