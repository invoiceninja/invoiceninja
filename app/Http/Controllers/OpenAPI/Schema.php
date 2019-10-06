<?php

/**
 * @OA\Schema(
 *   schema="Company",
 *   allOf={
 *     @OA\Schema(
 *       @OA\Property(property="id", type="string", example="WJxbojagwO", description="The company hash id"),
 *       @OA\Property(property="name", type="string", example="The local shop", description="The company name"),
 *       @OA\Property(property="logo", type="object", example="logo.png", description="The company logo - binary"),
 *       @OA\Property(property="logo_url", type="string", example="http://example.com/logo.png", description="The company logo url"),
 *     )
 *   }
 * )
 */

/**
 * @OA\Schema(
 *   schema="product_id",
 *   type="integer",
 *   format="int64",
 *   description="The unique identifier of a product in our catalog"
 * )
 */