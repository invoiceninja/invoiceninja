<?php
/**
 * @OA\Schema(
 *   schema="ExpenseCategory",
 *   type="object",
 *       @OA\Property(property="id", type="string", example="Opnel5aKBz", description="The expense hashed id"),
 *       @OA\Property(property="name", type="string", example="Accounting", description="The expense category name"),
 *       @OA\Property(property="user_id", type="string", example="XS987sD", description="The user hashed id"),
 *       @OA\Property(property="is_deleted", type="boolean", example=true, description="Flag determining whether the expense category has been deleted"),
 *       @OA\Property(property="updated_at", type="integer", example="2", description="The updated at timestamp"),
 *       @OA\Property(property="created_at", type="integer", example="2", description="The created at timestamp"),
 * )
 */
