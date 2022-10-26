<?php
/**
 * @OA\Schema(
 *   schema="User",
 *   type="object",
 *       @OA\Property(property="id", type="string", example="Opnel5aKBz", description="The hashed id of the user"),
 *       @OA\Property(property="first_name", type="string", example="Brad", description="The first name of the user"),
 *       @OA\Property(property="last_name", type="string", example="Pitt", description="The last name of the user"),
 *       @OA\Property(property="email", type="string", example="brad@pitt.com", description="The users email address"),
 *       @OA\Property(property="phone", type="string", example="555-1233-23232", description="The users phone number"),
 *       @OA\Property(property="signature", type="string", example="Have a nice day!", description="The users sign off signature"),
 *       @OA\Property(property="avatar", type="string", example="https://url.to.your/avatar.png", description="The users avatar"),
 *       @OA\Property(property="accepted_terms_version", type="string", example="1.0.1", description="The version of the invoice ninja terms that has been accepted by the user"),
 *       @OA\Property(property="oauth_user_id", type="string", example="jkhasdf789as6f675sdf768sdfs", description="The provider id of the oauth entity"),
 *       @OA\Property(property="oauth_provider_id", type="string", example="google", description="The oauth entity id"),
 * )
 */
