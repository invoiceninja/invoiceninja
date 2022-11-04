<?php
/**
 * @OA\Schema(
 *   schema="TaskStatus",
 *   type="object",
 *       @OA\Property(property="name", type="string", example="Backlog", description="The task status name"),
 *       @OA\Property(property="created_at", type="number", format="integer", example="134341234234", description="Timestamp"),
 *       @OA\Property(property="is_deleted", type="boolean", example=true, description="A boolean flag determining if the task status has been deleted"),
 *       @OA\Property(property="updated_at", type="number", format="integer", example="134341234234", description="Timestamp"),
 *       @OA\Property(property="archived_at", type="number", format="integer", example="134341234234", description="Timestamp"),
 * )
 */
