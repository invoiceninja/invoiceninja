<?php
/**
 * @OA\Schema(
 *   schema="Design",
 *   type="object",
 *       @OA\Property(property="id", type="string", example="AS3df3A", description="The design hashed id"),
 *       @OA\Property(property="name", type="string", example="Beauty", description="The design name"),
 *       @OA\Property(property="design", type="string", example="<html></html>", description="The design HTML"),
 *       @OA\Property(property="is_custom", type="boolean", example=true, description="Flag to determine if the design is a custom user design"),
 *       @OA\Property(property="is_active", type="boolean", example=true, description="Flag to determine if the design is available for use"),
 *       @OA\Property(property="is_deleted", type="boolean", example=true, description="Flag to determine if the design is deleted"),
 *       @OA\Property(property="created_at", type="number", format="integer", example="134341234234", description="Timestamp"),
 *       @OA\Property(property="updated_at", type="number", format="integer", example="134341234234", description="Timestamp"),
 *       @OA\Property(property="deleted_at", type="number", format="integer", example="134341234234", description="Timestamp"),
 * )
 */
