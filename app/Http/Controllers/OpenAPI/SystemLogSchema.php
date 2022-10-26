<?php
/**
 * @OA\Schema(
 *   schema="SystemLog",
 *   type="object",
 *       @OA\Property(property="id", type="string", example="AS3df3A", description="The account hashed id"),
 *       @OA\Property(property="company_id", type="string", example="AS3df3A", description="The company hashed id"),
 *       @OA\Property(property="user_id", type="string", example="AS3df3A", description="The user_id hashed id"),
 *       @OA\Property(property="client_id", type="string", example="AS3df3A", description="The client_id hashed id"),
 *       @OA\Property(property="event_id", type="integer", example=1, description="The Log Type ID"),
 *       @OA\Property(property="category_id", type="integer", example=1, description="The Category Type ID"),
 *       @OA\Property(property="type_id", type="integer", example=1, description="The Type Type ID"),
 *       @OA\Property(property="log", type="object", example="{'key':'value'}", description="The json object of the error"),
 *       @OA\Property(property="updated_at", type="string", example="2", description="Timestamp"),
 *       @OA\Property(property="created_at", type="string", example="2", description="Timestamp"),
 * )
 */
