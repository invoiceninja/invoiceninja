<?php
/**
 * @OA\Schema(
 *   schema="BTRules",
 *   type="object",
 *       @OA\Property(property="data_key", type="string", example="description,amount", description="The key to search"),
 *       @OA\Property(property="operator", type="string", example=">", description="The operator flag of the search"),
 *       @OA\Property(property="value", type="string" ,example="bob", description="The value to search for"),
 * )
 */
