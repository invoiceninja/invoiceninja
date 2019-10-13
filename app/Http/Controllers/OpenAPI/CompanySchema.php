<?php
/**
 * @OA\Schema(
 *   schema="Company",
 *   type="object",
 *       @OA\Property(property="id", type="string", example="WJxbojagwO", description="The company hash id"),
 *       @OA\Property(property="name", type="string", example="The local shop", description="The company name"),
 *       @OA\Property(property="logo", type="object", example="logo.png", description="The company logo - binary"),
 *       @OA\Property(property="settings",ref="#/components/schemas/CompanySettings"),
 * )
 */