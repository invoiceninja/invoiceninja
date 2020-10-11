<?php
/**
 * @OA\Schema(
 *   schema="ValidationError",
 *   type="object",
 *       @OA\Property(property="message", type="string", example="The given data was invalid.", description="The error message"),
 *       @OA\Property(
 *       	property="errors",
 *        	type="object",
 *         	@OA\Property(
 *          	property="value",
 *          	type="array",
 *              	@OA\Items(
 *                  type="string",
 *                  ),
 *          ),
 *      ),
 * )
 */
