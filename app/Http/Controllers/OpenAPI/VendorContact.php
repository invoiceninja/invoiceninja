<?php
/**
 * @OA\Schema(
 *   schema="VendorContact",
 *   type="object",
 *       @OA\Property(property="id", type="string", example="Opnel5aKBz", description="The hashed id of the vendor contact"),
 *       @OA\Property(property="user_id", type="string", example="Opnel5aKBz", description="The hashed id of the user id"),
 *       @OA\Property(property="company_id", type="string", example="Opnel5aKBz", description="The hashed id of the company"),
 *       @OA\Property(property="vendor_id", type="string", example="Opnel5aKBz", description="The hashed id of the vendor"),
 *       @OA\Property(property="first_name", type="string", example="Harry", description="The first name of the contact"),
 *       @OA\Property(property="last_name", type="string", example="Windsor", description="The last name of the contact"),
 *       @OA\Property(property="phone", type="string", example="555-123-1234", description="The contacts phone number"),
 *       @OA\Property(property="custom_value1", type="string", example="2022-10-10", description="A custom value"),
 *       @OA\Property(property="custom_value2", type="string", example="$1000", description="A custom value"),
 *       @OA\Property(property="custom_value3", type="string", example="", description="A custom value"),
 *       @OA\Property(property="custom_value4", type="string", example="", description="A custom value"),
 *       @OA\Property(property="email", type="string", example="harry@windsor.com", description="The contact email address"),
 *       @OA\Property(property="is_primary", type="boolean", example=true, description="Boolean flag determining if the contact is the primary contact for the vendor"),
 *       @OA\Property(property="created_at", type="number", format="integer", example="134341234234", description="Timestamp"),
 *       @OA\Property(property="updated_at", type="number", format="integer", example="134341234234", description="Timestamp"),
 *       @OA\Property(property="deleted_at", type="number", format="integer", example="134341234234", description="Timestamp"),
 * )
 */
