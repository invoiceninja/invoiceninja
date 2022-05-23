<?php


/**
 * @OA\Schema(
 *   schema="TaskSchedulerSchema",
 *   type="object",
 *
 *      *     @OA\Property(property="paused",type="bool",example="false",description="The scheduler paused state"),
 *     @OA\Property(property="repeat_every",type="string",example="DAY",description="Accepted values (DAY,WEEK,MONTH,3MONTHS,YEAR)"),
 *     @OA\Property(property="start_from",type="integer",example="1652898504",description="Timestamp when we should start the scheduler, default is today"),
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


/**
 * @OA\Schema(
 *   schema="UpdateTaskSchedulerSchema",
 *   type="object",
 *
 *      @OA\Property(property="paused",type="bool",example="false",description="The scheduler paused state"),
 *      @OA\Property(property="archived",type="bool",example="false",description="The scheduler archived state"),
 *     *     @OA\Property(property="repeat_every",type="string",example="DAY",description="Accepted values (DAY,WEEK,MONTH,3MONTHS,YEAR)"),
 *     @OA\Property(property="start_from",type="integer",example="1652898504",description="Timestamp when we should start the scheduler, default is today"),
 *
 * )
 */

/**
 * @OA\Schema(
 *   schema="UpdateJobForASchedulerSchema",
 *   type="object",
 *     @OA\Property(property="job",type="string",example="create_client_report",description="Set action name, action names can be found in ScheduledJob Model"),
 *
 * )
 */

