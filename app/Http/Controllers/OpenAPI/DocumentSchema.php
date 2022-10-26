<?php
/**
 * @OA\Schema(
 *   schema="Document",
 *   type="object",
 *       @OA\Property(property="id", type="string", example="AS3df3A", description="The document hashed id"),
 *       @OA\Property(property="user_id", type="string", example="", description="The user hashed id"),
 *       @OA\Property(property="assigned_user_id", type="string", example="", description="The assigned user hashed id"),
 *       @OA\Property(property="project_id", type="string", example="", description="The project associated with this document"),
 *       @OA\Property(property="vendor_id", type="string", example="", description="The vendor associated with this documents"),
 *       @OA\Property(property="name", type="string", example="Beauty", description="The document name"),
 *       @OA\Property(property="url", type="string", example="Beauty", description="The document url"),
 *       @OA\Property(property="preview", type="string", example="Beauty", description="The document preview url"),
 *       @OA\Property(property="type", type="string", example="Beauty", description="The document type"),
 *       @OA\Property(property="disk", type="string", example="Beauty", description="The document disk"),
 *       @OA\Property(property="hash", type="string", example="Beauty", description="The document hashed"),
 *       @OA\Property(property="is_deleted", type="boolean", example=true, description="Flag to determine if the document is deleted"),
 *       @OA\Property(property="is_default", type="boolean", example=true, description="Flag to determine if the document is a default doc"),
 *       @OA\Property(property="created_at", type="number", format="integer", example="134341234234", description="Timestamp"),
 *       @OA\Property(property="updated_at", type="number", format="integer", example="134341234234", description="Timestamp"),
 *       @OA\Property(property="deleted_at", type="number", format="integer", example="134341234234", description="Timestamp"),
 * )
 */
