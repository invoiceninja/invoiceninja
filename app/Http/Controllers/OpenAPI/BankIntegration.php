<?php
/**
 * @OA\Schema(
 *   schema="BankIntegration",
 *   type="object",
 *       @OA\Property(property="id", type="string", example="AS3df3A", description="The bank integration hashed id"),
 *       @OA\Property(property="company_id", type="string", example="AS3df3A", description="The company hashed id"),
 *       @OA\Property(property="user_id", type="string", example="AS3df3A", description="The user hashed id"),
 *       @OA\Property(property="provider_bank_name", type="string", example="Chase Bank", description="The providers bank name"),
 *       @OA\Property(property="bank_account_id", type="integer", example="1233434", description="The bank account id"),
 *       @OA\Property(property="bank_account_name", type="string", example="My Checking Acc", description="The name of the account"),
 *       @OA\Property(property="bank_account_number", type="string", example="111 234 2332", description="The account number"),
 *       @OA\Property(property="bank_account_status", type="string", example="ACTIVE", description="The status of the bank account"),
 *       @OA\Property(property="bank_account_type", type="string", example="CREDITCARD", description="The type of account"),
 *       @OA\Property(property="balance", type="number", example="1000000", description="The current bank balance if available"),
 *       @OA\Property(property="currency", type="string", example="USD", description="iso_3166_3 code"),
 * )
 */
