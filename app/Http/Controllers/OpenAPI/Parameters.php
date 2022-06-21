<?php
/**
 *     @OA\Parameter(
 *         name="X-Api-Secret",
 *         in="header",
 *         description="The API secret as defined by the .env variable API_SECRET",
 *         required=true,
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
 *         name="X-Api-Token",
 *         in="header",
 *         description="The API token to be used for authentication",
 *         required=true,
 *			 @OA\Schema(
 *           type="string",
 *           example="HcRvs0oCvYbY5g3RzgBZrSBOChCiq8u4AL0ieuFN5gn4wUV14t0clVhfPc5OX99q"
 *         )
 *     ),
 *     @OA\Parameter(
 *         name="X-Api-Password",
 *         in="header",
 *         description="The login password when challenged",
 *         required=true,
 *			 @OA\Schema(
 *           type="string",
 *           example="supersecretpassword"
 *         )
 *     ),
 *
 *     @OA\Parameter(
 *         name="include",
 *         in="query",
 *         description="Includes child relationships in the response, format is comma separated",
 *			 @OA\Schema(
 *           type="string",
 *           example="clients,invoices"
 *         )
 *     ),
 *
 *     @OA\Parameter(
 *         name="include_static",
 *         in="query",
 *         description="Returns static variables",
 *			 @OA\Schema(
 *           type="string",
 *           example="include_static=true"
 *         )
 *     ),
 *
 *     @OA\Parameter(
 *         name="clear_cache",
 *         in="query",
 *         description="Clears the static cache",
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
 *			 @OA\Schema(
 *           type="number",
 *           example="user"
 *         )
 *     ),
 */
