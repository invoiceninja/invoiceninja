<?php
/**
 * @OA\Schema(
 *   schema="GenericReportSchema",
 *   type="object",
 *       @OA\Property(property="date_range", type="string", example="last7", description="The string representation of the date range of data to be returned"),
 *       @OA\Property(property="date_key", type="string", example="created_at", description="The date column to search between."),
 *       @OA\Property(property="start_date", type="string", example="2000-10-31", description="The start date to search between"),
 *       @OA\Property(property="end_date", type="string", example="2", description="The end date to search between"),
 *       @OA\Property(
 *          property="report_keys",
 *          type="array",
 *                 @OA\Items(
 *                     type="string",
 *                     description="Array of Keys to export",
 *                     example="['name','date']",
 *                 ),
 *       ),
 *
 * )
 */
