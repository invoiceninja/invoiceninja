<?php

/**
 *     @OA\Parameter(
 *         name="X-API-SECRET",
 *         in="header",
 *         description="The API secret as defined by the .env variable API_SECRET, only needed for self hosted users, and only required on the login route if the .env parameter has been set.",
 *         required=false,
 *			 @OA\Schema(
 *           type="string",
 *           example="password"
 *         )
 *     ),
 *
 *     @OA\Parameter(
 *         name="X-Requested-With",
 *         in="header",
 *         description="Used to send the XMLHttpRequest header",
 *         required=true,
 *			 @OA\Schema(
 *           type="string",
 *           example="XMLHttpRequest",
 *           readOnly=true
 *         )
 *     ),
 *
 *     @OA\Parameter(
 *         name="X-API-TOKEN",
 *         in="header",
 *         description="The API token to be used for authentication",
 *         required=true,
 *			 @OA\Schema(
 *           type="string",
 *           example="TOKEN"
 *         )
 *     ),
 *     @OA\Parameter(
 *         name="X-API-PASSWORD",
 *         in="header",
 *         description="The login password when challenged on certain protected routes",
 *         required=false,
 *			 @OA\Schema(
 *           type="string",
 *           example="supersecretpassword"
 *         )
 *     ),
 *
 *     @OA\Parameter(
 *         name="include",
 *         in="query",
 *         description="Includes child relationships in the response, format is comma separated. Check each model for the list of associated includes",
 *         required=false,
 *			 @OA\Schema(
 *           type="string",
 *           example=""
 *         )
 *     ),
 *
 *     @OA\Parameter(
 *         name="include_static",
 *         in="query",
 *         description="Returns static variables",
 *         required=false,
 *			 @OA\Schema(
 *           type="string",
 *           example="include_static=true",
 *         )
 *     ),
 *
 *     @OA\Parameter(
 *         name="clear_cache",
 *         in="query",
 *         description="Clears the static cache",
 *         required=false,
 *			 @OA\Schema(
 *           type="string",
 *           example="clear_cache=true"
 *         )
 *     ),
 *
 *     @OA\Parameter(
 *         name="index",
 *         in="query",
 *         description="Replaces the default response index from data to a user specific string",
 *         required=false,
 *			 @OA\Schema(
 *           type="string",
 *           example="user"
 *         )
 *     ),
 *
 *     @OA\Parameter(
 *         name="api_version",
 *         in="query",
 *         description="The API version",
 *         required=false,
 *			 @OA\Schema(
 *           type="number",
 *           example="user"
 *         )
 *     ),
 */
