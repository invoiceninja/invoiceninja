<?php
/**
 * @OA\Schema(
 *   schema="TaskStatus",
 *   type="object",
 *       @OA\Property(property="name", type="string", example="Backlog", description="The task status name"),
 *       @OA\Property(property="created_at", type="number", format="integer", example="134341234234", description="Timestamp"),
 *       @OA\Property(property="is_deleted", type="boolean", example=true, description="______"),
 *       @OA\Property(property="updated_at", type="number", format="integer", example="134341234234", description="Timestamp"),
 *       @OA\Property(property="archived_at", type="number", format="integer", example="134341234234", description="Timestamp"),
 * )
 */
