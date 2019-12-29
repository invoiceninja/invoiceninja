 <?php
/**
 * @OA\Schema(
 *   schema="CompanyUser",
 *   type="object",
 *       @OA\Property(property="permissions", type="string", example="[create_invoice]", description="The company user permissions"),
 *       @OA\Property(property="settings", type="object", example="The local shop", description="The company name"),
 *       @OA\Property(property="is_owner", type="boolean", example=true, description="Determines whether the user owns this company"),
 *       @OA\Property(property="is_admin", type="boolean", example=true, description="Determines whether the user is the admin of this company"),
 *       @OA\Property(property="is_locked", type="boolean", example=true, description="Determines whether the users access to this company has been locked"),
 *       @OA\Property(property="updated_at", type="integer", example="1231232312321", description="The last time the record was modified"),
 *       @OA\Property(property="deleted_at", type="integer", example="12312312321", description="Timestamp when the user was archived"),
 *       @OA\Property(property="account",ref="#/components/schemas/Account"),
 *       @OA\Property(property="company", ref="#/components/schemas/Company"),
 *       @OA\Property(property="user",ref="#/components/schemas/User"),
 *       @OA\Property(property="token",ref="#/components/schemas/CompanyToken"),
 * )
 */
