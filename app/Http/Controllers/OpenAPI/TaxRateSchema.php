<?php
/**
 * @OA\Schema(
 *   schema="TaxRate",
 *   type="object",
 *       @OA\Property(property="id", type="string", example="Opnel5aKBz", description="Thie hashed id of the tax"),
 *       @OA\Property(property="name", type="string", example="GST", description="The tax name"),
 *       @OA\Property(property="rate", type="number", example="10", description="The tax rate"),
 *       @OA\Property(property="is_deleted", type="boolean", example=true, description="Boolean flag determining if the tax has been deleted"),
 * )
 */
