 <?php
/**
 * @OA\Schema(
 *   schema="CompanyUser",
 *   allOf={
 *     @OA\Schema(
 *       @OA\Property(property="permissions", type="string", example="[create_invoice]", description="The company user permissions"),
 *       @OA\Property(property="settings", type="object", example="The local shop", description="The company name"),
 *       @OA\Property(property="is_owner", type="boolean", example=true, description="Determines whether the user owns this company"),
 *       @OA\Property(property="is_locked", type="boolean", example=true, description="Determines whether the users access to this company has been locked"),
 *       @OA\Property(property="updated_at", type="integer", example="1231232312321", description="The last time the record was modified"),
 *       @OA\Property(property="deleted_at", type="integer", example="12312312321", description="Timestamp when the user was archived"),
 *     ),
 *     @OA\Schema(ref="#/components/schemas/Company"),
 *     @OA\Schema(ref="#/components/schemas/User"),
 *     @OA\Schema(ref="#/components/schemas/Account"),
 *     @OA\Schema(ref="#/components/schemas/CompanyToken"),
 *   }
 * )
 */

/**
 * 
 */