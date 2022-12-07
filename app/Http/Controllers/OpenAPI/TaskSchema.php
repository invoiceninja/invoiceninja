<?php
/**
 * @OA\Schema(
 *   schema="Task",
 *   type="object",
 *       @OA\Property(property="id", type="string", example="Opnel5aKBz", description="The hashed id of the task"),
 *       @OA\Property(property="user_id", type="string", example="Opnel5aKBz", description="The hashed id of the user who created the task"),
 *       @OA\Property(property="assigned_user_id", type="string", example="Opnel5aKBz", description="The assigned user of the task"),
 *       @OA\Property(property="company_id", type="string", example="Opnel5aKBz", description="The hashed id of the company"),
 *       @OA\Property(property="client_id", type="string", example="Opnel5aKBz", description="The hashed if of the client"),
 *       @OA\Property(property="invoice_id", type="string", example="Opnel5aKBz", description="The hashed id of the invoice associated with the task"),
 *       @OA\Property(property="project_id", type="string", example="Opnel5aKBz", description="The hashed id of the project associated with the task"),
 *       @OA\Property(property="number", type="string", example="TASK-123", description="The number of the task"),
 *       @OA\Property(property="time_log", type="string", example="[[1,2],[3,4]]", description="An array of unix time stamps defining the start and end times of the task"),
 *       @OA\Property(property="is_running", type="boolean", example=true, description="Determines if the task is still running"),
 *       @OA\Property(property="is_deleted", type="boolean", example=true, description="Boolean flag determining if the task has been deleted"),
 *       @OA\Property(property="task_status_id", type="string", example="Opnel5aKBz", description="The hashed id of the task status"),
 *       @OA\Property(property="description", type="string", example="A wonder task to work on", description="The task description"),
 *       @OA\Property(property="duration", type="integer", example="", description="The task duration"),
 *       @OA\Property(property="task_status_order", type="integer", example="4", description="The order of the task"),
 *       @OA\Property(property="custom_value1", type="string", example="2022-10-10", description="A custom value"),
 *       @OA\Property(property="custom_value2", type="string", example="$1100", description="A custom value"),
 *       @OA\Property(property="custom_value3", type="string", example="I need help", description="A custom value"),
 *       @OA\Property(property="custom_value4", type="string", example="INV-3343", description="A custom value"),
 *       @OA\Property(property="created_at", type="number", format="integer", example="1434342123", description="Timestamp"),
 *       @OA\Property(property="updated_at", type="number", format="integer", example="1434342123", description="Timestamp"),
 *       @OA\Property(property="archived_at", type="number", format="integer", example="1434342123", description="Timestamp"),
 * )
 */
