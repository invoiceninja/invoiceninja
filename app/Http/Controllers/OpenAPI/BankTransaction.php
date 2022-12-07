<?php
/**
 * @OA\Schema(
 *   schema="BankTransaction",
 *   type="object",
 *       @OA\Property(property="id", type="string", example="AS3df3A", description="The bank integration hashed id"),
 *       @OA\Property(property="company_id", type="string", example="AS3df3A", description="The company hashed id"),
 *       @OA\Property(property="user_id", type="string", example="AS3df3A", description="The user hashed id"),
 *       @OA\Property(property="transaction_id", type="integer", example=343434, description="The id of the transaction rule"),
 *       @OA\Property(property="amount", type="number", example=10.00, description="The transaction amount"),
 *       @OA\Property(property="currency_id", type="string", example="1", description="The currency ID of the currency"),
 *       @OA\Property(property="account_type", type="string", example="creditCard", description="The account type"),
 *       @OA\Property(property="description", type="string", example="Potato purchases for kevin", description="The description of the transaction"),
 *       @OA\Property(property="category_id", type="integer", example=1, description="The category id"),
 *       @OA\Property(property="category_type", type="string", example="Expenses", description="The category description"),
 *       @OA\Property(property="base_type", type="string", example="CREDIT", description="Either CREDIT or DEBIT"),
 *       @OA\Property(property="date", type="string", example="2022-09-01", description="The date of the transaction"),
 *       @OA\Property(property="bank_account_id", type="integer", example="1", description="The ID number of the bank account"),
 * )
 */
