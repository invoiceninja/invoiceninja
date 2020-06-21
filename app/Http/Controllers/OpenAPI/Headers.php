<?php
/**
 * @OA\Header(
 *     header="X-MINIMUM-CLIENT-VERSION",
 *     description="The API version",
 *     @OA\Schema( type="number" )
 * ),
 *
 * @OA\Header(
 *     header="X-RateLimit-Remaining",
 *     description="The number of requests left for the time window.",
 *     @OA\Schema( type="integer" )
 * ),
 *
 * @OA\Header(
 *     header="X-RateLimit-Limit",
 *     description="The total number of requests in a given time window.",
 *     @OA\Schema( type="integer" )
 * ),
 */
