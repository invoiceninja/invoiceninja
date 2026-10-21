<?php

/**
 * @OA\Schema(
 *   schema="Product",
 *   type="object",
 *       @OA\Property(property="id", type="string", example="Opnel5aKBz", description="The product hashed id"),
 *       @OA\Property(property="price_tiers", type="array", @OA\Items(type="object"), description="Quantity based price tiers. Each tier: {min_quantity, price} or {min_quantity, discount}"),
 *       @OA\Property(property="price_tiers_mode", type="string", example="all_units", description="How price tiers are resolved: all_units (whole quantity billed at the matched tier) or progressive (each quantity bracket billed at its own tier rate)"),
 * )
 */
