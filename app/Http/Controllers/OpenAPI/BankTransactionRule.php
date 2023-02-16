<?php
/**
 * @OA\Schema(
 *   schema="BankTransactionRule",
 *   type="object",
 *       @OA\Property(property="id", type="string", example="AS3df3A", description="The bank transaction rules hashed id"),
 *       @OA\Property(property="company_id", type="string", example="AS3df3A", description="The company hashed id"),
 *       @OA\Property(property="user_id", type="string", example="AS3df3A", description="The user hashed id"),
 *       @OA\Property(property="name", type="string", example="Rule 1", description="The name of the transaction"),
 *       @OA\Property(
 *          property="rules",
 *          type="array",
 *          description="A mapped collection of the sub rules for the BankTransactionRule",
 *          @OA\Items(
 *              ref="#/components/schemas/BTRules",
 *          ),
 *       ),
 *       @OA\Property(property="auto_convert", type="boolean", example=true, description="Flags whether the rule converts the transaction automatically"),
 *       @OA\Property(property="matches_on_all", type="boolean", example=true, description="Flags whether all subrules are required for the match"),
 *       @OA\Property(property="applies_to", type="string", example="CREDIT", description="Flags whether the rule applies to a CREDIT or DEBIT"),
 *       @OA\Property(property="client_id", type="string", example="AS3df3A", description="The client hashed id"),
 *       @OA\Property(property="vendor_id", type="string", example="AS3df3A", description="The vendor hashed id"),
 *       @OA\Property(property="category_id", type="string", example="AS3df3A", description="The category hashed id"),
 * )
 */
