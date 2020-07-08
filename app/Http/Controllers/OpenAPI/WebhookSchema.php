<?php
/**
 * @OA\Schema(
 *   schema="Webhook",
 *   type="object",
 *       @OA\Property(property="id", type="string", example="AS3df3A", description="The subscription hashed id"),
 *       @OA\Property(property="event_id", type="string", example="AS3df3A", description="The subscription event id"),
 *       @OA\Property(property="target_url", type="string", example="AS3df3A", description="The api endpoint"),
 *       @OA\Property(property="format", type="string", example="JSON", description="JSON or UBL"),
 * )
 */
