<?php
/**
 * @OA\Schema(
 *   schema="Company",
 *   type="object",
 *       @OA\Property(property="id", type="string", example="WJxbojagwO", description="The company hash id"),
 *       @OA\Property(property="size_id", type="string", example="1", description="The company size ID"),
 *       @OA\Property(property="industry_id", type="string", example="1", description="The company industry ID"),
 *       @OA\Property(property="enabled_tax_rates", type="integer", example="1", description="Number of taxes rates used per entity"),
 *       @OA\Property(property="fill_products", type="boolean", example=true, description="Toggles filling a product description based on product key"),
 *       @OA\Property(property="convert_products", type="boolean", example=true, description="___________"),
 *       @OA\Property(property="update_products", type="boolean", example=true, description="Toggles updating a product description which description changes"),
 *       @OA\Property(property="custom_surcharge_taxes1", type="boolean", example=true, description="Toggles charging taxes on custom surcharge amounts"),
 *       @OA\Property(property="custom_surcharge_taxes2", type="boolean", example=true, description="Toggles charging taxes on custom surcharge amounts"),
 *       @OA\Property(property="custom_surcharge_taxes3", type="boolean", example=true, description="Toggles charging taxes on custom surcharge amounts"),
 *       @OA\Property(property="custom_surcharge_taxes4", type="boolean", example=true, description="Toggles charging taxes on custom surcharge amounts"),
 *       @OA\Property(property="logo", type="object", example="logo.png", description="The company logo - binary"),
 *       @OA\Property(property="settings",ref="#/components/schemas/CompanySettings"),
 * )
 */

